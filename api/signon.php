<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

// POST /signon.php
// Auth:  Authorization: Bearer <api_token>
// Body:  { "vehicle_id": 1, "route_id": 1 }
// Returns the shift_id the app then sends with every ping.

require_post();
$driver = authenticate_driver();
$body   = read_json_body();

$vehicle_id = isset($body['vehicle_id']) ? (int) $body['vehicle_id'] : 0;
$route_id   = isset($body['route_id'])   ? (int) $body['route_id']   : 0;

if ($vehicle_id <= 0 || $route_id <= 0) {
    json_response(422, ['ok' => false, 'error' => 'vehicle_id and route_id are required']);
}

$pdo = db();

$chk = $pdo->prepare('SELECT 1 FROM vehicles WHERE id = ? AND status = "active"');
$chk->execute([$vehicle_id]);
if (!$chk->fetchColumn()) {
    json_response(422, ['ok' => false, 'error' => 'unknown_vehicle']);
}

$chk = $pdo->prepare('SELECT 1 FROM routes WHERE id = ?');
$chk->execute([$route_id]);
if (!$chk->fetchColumn()) {
    json_response(422, ['ok' => false, 'error' => 'unknown_route']);
}

// The driver must have been granted access to this vehicle. Checked before the
// auto-close below so a rejected sign-on has no side effects on the driver's
// current open shift.
$chk = $pdo->prepare('SELECT 1 FROM driver_vehicle_access WHERE driver_id = ? AND vehicle_id = ? LIMIT 1');
$chk->execute([$driver['id'], $vehicle_id]);
if (!$chk->fetchColumn()) {
    json_response(403, ['ok' => false, 'error' => 'vehicle_not_permitted']);
}

// Close any shift this driver left open (e.g. app crashed / forgot to sign off).
$pdo->prepare('UPDATE shifts SET status = "closed", ended_at = NOW() WHERE driver_id = ? AND status = "open"')
    ->execute([$driver['id']]);

$ins = $pdo->prepare('INSERT INTO shifts (driver_id, vehicle_id, route_id, status) VALUES (?, ?, ?, "open")');
$ins->execute([$driver['id'], $vehicle_id, $route_id]);

json_response(200, [
    'ok'       => true,
    'shift_id' => (int) $pdo->lastInsertId(),
    'driver'   => $driver['name'],
]);
