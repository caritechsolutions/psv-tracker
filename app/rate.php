<?php
declare(strict_types=1);
require __DIR__ . '/../api/db.php';
require __DIR__ . '/auth.php';

// POST /app/rate.php — rider rates one of their own rides.
// Auth: rider session (require_rider_json). Body (form-encoded): ride_id,
// vehicle_stars, driver_stars, optional comment, csrf.
//
// vehicle_id and driver_id are captured server-side from the ride's shift, never
// trusted from the request. One rating per ride (unique ride_id). The vehicle
// score is public (map); the driver score is private (owner/admin only).

function rate_fail(int $code, string $error): void
{
    json_response($code, ['ok' => false, 'error' => $error]);
}

require_post();
$rider = require_rider_json();
rider_csrf_verify();

$ride_id = isset($_POST['ride_id']) ? (int) $_POST['ride_id'] : 0;
$vstars  = isset($_POST['vehicle_stars']) ? (int) $_POST['vehicle_stars'] : 0;
$dstars  = isset($_POST['driver_stars']) ? (int) $_POST['driver_stars'] : 0;
$comment = trim((string) ($_POST['comment'] ?? ''));
if ($comment === '') {
    $comment = null;
} elseif (mb_strlen($comment) > 500) {
    $comment = mb_substr($comment, 0, 500);
}

if ($ride_id <= 0) {
    rate_fail(422, 'ride_id is required');
}
if ($vstars < 1 || $vstars > 5 || $dstars < 1 || $dstars > 5) {
    rate_fail(422, 'stars must be between 1 and 5');
}

$pdo = db();

// The ride must belong to this rider. vehicle_id + driver_id come from the ride's
// shift, not the request.
$stmt = $pdo->prepare(
    'SELECT r.vehicle_id, s.driver_id
       FROM rides r
       JOIN shifts s ON s.id = r.shift_id
      WHERE r.id = ? AND r.rider_id = ?'
);
$stmt->execute([$ride_id, (int) $rider['id']]);
$ride = $stmt->fetch();
if (!$ride) {
    rate_fail(404, 'ride_not_found');   // not theirs / doesn't exist — generic
}

try {
    $ins = $pdo->prepare(
        'INSERT INTO ratings (ride_id, rider_id, vehicle_id, driver_id, vehicle_stars, driver_stars, comment)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([
        $ride_id, (int) $rider['id'], (int) $ride['vehicle_id'], (int) $ride['driver_id'],
        $vstars, $dstars, $comment,
    ]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {   // already rated (unique ride_id)
        json_response(200, ['ok' => true, 'status' => 'already_rated']);
    }
    throw $e;
}

json_response(201, ['ok' => true, 'status' => 'rated']);
