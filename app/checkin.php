<?php
declare(strict_types=1);
require __DIR__ . '/../api/db.php';
require __DIR__ . '/auth.php';

// POST /app/checkin.php — rider checks in to the van they're boarding.
// Auth: rider session (require_rider_json). Body: { shift_id, lat, lng, csrf }.
//
// Server-side gate (the anti-farming check): the shift must be open and fresh,
// and the rider's GPS must be within CHECKIN_RADIUS_M of the vehicle's latest
// known position. A valid check-in creates one ride and awards one point;
// repeats for the same shift are idempotent. The rider's coordinates are used
// only to compute distance here and are never stored.

const CHECKIN_FRESHNESS_SECONDS = 120;  // matches the public feed window
const CHECKIN_RADIUS_M          = 250;  // tunable; deliberately a little generous

function checkin_fail(int $code, string $error, array $extra = []): void
{
    json_response($code, array_merge(['ok' => false, 'error' => $error], $extra));
}

/** Haversine distance in metres. */
function checkin_distance_m(float $aLat, float $aLng, float $bLat, float $bLng): float
{
    $R = 6371000.0;
    $dLat = deg2rad($bLat - $aLat);
    $dLng = deg2rad($bLng - $aLng);
    $s = sin($dLat / 2) ** 2 + cos(deg2rad($aLat)) * cos(deg2rad($bLat)) * sin($dLng / 2) ** 2;
    return 2 * $R * asin(min(1.0, sqrt($s)));
}

require_post();
$rider = require_rider_json();   // 401 JSON if not signed in
rider_csrf_verify();

// Form-encoded body (same channel as the CSRF token).
$shift_id = isset($_POST['shift_id']) ? (int) $_POST['shift_id'] : 0;
$lat      = (isset($_POST['lat']) && $_POST['lat'] !== '') ? (float) $_POST['lat'] : null;
$lng      = (isset($_POST['lng']) && $_POST['lng'] !== '') ? (float) $_POST['lng'] : null;

if ($shift_id <= 0 || $lat === null || $lng === null) {
    checkin_fail(422, 'shift_id, lat and lng are required');
}
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    checkin_fail(422, 'lat/lng out of range');
}

$pdo = db();

// The shift must be open, and its latest position fresh. Pull the same latest
// position the public feed uses.
$stmt = $pdo->prepare(
    'SELECT s.vehicle_id, p.lat AS vlat, p.lng AS vlng,
            (p.received_at >= (NOW() - INTERVAL ' . (int) CHECKIN_FRESHNESS_SECONDS . ' SECOND)) AS fresh
       FROM shifts s
       JOIN positions p ON p.id = (
           SELECT p2.id FROM positions p2 WHERE p2.shift_id = s.id ORDER BY p2.id DESC LIMIT 1
       )
      WHERE s.id = ? AND s.status = "open"
      LIMIT 1'
);
$stmt->execute([$shift_id]);
$shift = $stmt->fetch();

if (!$shift || (int) $shift['fresh'] !== 1) {
    checkin_fail(409, 'shift_unavailable');
}

$dist = checkin_distance_m($lat, $lng, (float) $shift['vlat'], (float) $shift['vlng']);
if ($dist > CHECKIN_RADIUS_M) {
    checkin_fail(403, 'too_far', ['distance_m' => (int) round($dist), 'radius_m' => CHECKIN_RADIUS_M]);
}

// Create the ride + award the point. Idempotent per (rider, shift).
try {
    $ins = $pdo->prepare(
        'INSERT INTO rides (rider_id, shift_id, vehicle_id, status, point_awarded)
         VALUES (?, ?, ?, "checked_in", 1)'
    );
    $ins->execute([(int) $rider['id'], $shift_id, (int) $shift['vehicle_id']]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {   // duplicate (rider, shift)
        json_response(200, ['ok' => true, 'status' => 'already_checked_in', 'point_awarded' => false]);
    }
    throw $e;
}

$balance = (int) $pdo->query('SELECT COALESCE(SUM(point_awarded),0) FROM rides WHERE rider_id = ' . (int) $rider['id'])->fetchColumn();

json_response(201, [
    'ok'            => true,
    'status'        => 'checked_in',
    'point_awarded' => true,
    'points'        => $balance,
]);
