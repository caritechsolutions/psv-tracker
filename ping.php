<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

// POST /ping.php
// Auth:  Authorization: Bearer <api_token>
// Body:  {
//   "shift_id": 12,
//   "lat": 13.0975, "lng": -59.6189,
//   "speed": 32.5, "heading": 270,
//   "seat_status": "available",
//   "recorded_at": "2026-06-24T14:03:11Z"   // optional; epoch s/ms also accepted
// }

require_post();
$driver = authenticate_driver();
$body   = read_json_body();

$shift_id = isset($body['shift_id']) ? (int) $body['shift_id'] : 0;
$lat = array_key_exists('lat', $body) ? (float) $body['lat'] : null;
$lng = array_key_exists('lng', $body) ? (float) $body['lng'] : null;

if ($shift_id <= 0 || $lat === null || $lng === null) {
    json_response(422, ['ok' => false, 'error' => 'shift_id, lat and lng are required']);
}
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    json_response(422, ['ok' => false, 'error' => 'lat/lng out of range']);
}

$pdo = db();

// The shift must exist, belong to this driver, and still be open.
$stmt = $pdo->prepare('SELECT 1 FROM shifts WHERE id = ? AND driver_id = ? AND status = "open" LIMIT 1');
$stmt->execute([$shift_id, $driver['id']]);
if (!$stmt->fetchColumn()) {
    json_response(409, ['ok' => false, 'error' => 'no_open_shift']);
}

$speed   = isset($body['speed'])   ? (float) $body['speed']   : null;
$heading = isset($body['heading']) ? (int) $body['heading']   : null;
if ($heading !== null) {
    $heading = (($heading % 360) + 360) % 360;
}

$seat = $body['seat_status'] ?? 'unknown';
if (!in_array($seat, ['available', 'full', 'unknown'], true)) {
    $seat = 'unknown';
}

$recorded_at = isset($body['recorded_at'])
    ? normalize_ts($body['recorded_at'])
    : date('Y-m-d H:i:s');

$ins = $pdo->prepare(
    'INSERT INTO positions (shift_id, lat, lng, speed, heading, seat_status, recorded_at)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$ins->execute([$shift_id, $lat, $lng, $speed, $heading, $seat, $recorded_at]);

json_response(201, ['ok' => true, 'position_id' => (int) $pdo->lastInsertId()]);
