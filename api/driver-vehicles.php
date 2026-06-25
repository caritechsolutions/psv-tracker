<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

// GET or POST /driver-vehicles.php
// Auth:  Authorization: Bearer <token>   (same token auth as sign-on/ping)
//
// The sign-on list for the driver app: the vehicles this driver has been granted
// access to (and that are active), plus the global list of routes. Returns only
// the authenticated driver's permitted vehicles; 401 on a missing/invalid token.
// Does not change sign-on/ping/sign-off.

$driver = authenticate_driver();   // 401 on missing/invalid; returns id/name/status
$pdo = db();

$stmt = $pdo->prepare(
    'SELECT v.id, v.registration, v.label, v.capacity
       FROM driver_vehicle_access a
       JOIN vehicles v ON v.id = a.vehicle_id
      WHERE a.driver_id = ? AND v.status = "active"
      ORDER BY v.registration'
);
$stmt->execute([(int) $driver['id']]);

$vehicles = array_map(static function (array $r): array {
    return [
        'id'           => (int) $r['id'],
        'registration' => $r['registration'],
        'label'        => $r['label'],                              // may be null
        'capacity'     => $r['capacity'] !== null ? (int) $r['capacity'] : null,
    ];
}, $stmt->fetchAll());

$routes = array_map(static function (array $r): array {
    return [
        'id'           => (int) $r['id'],
        'route_number' => $r['route_number'],
        'name'         => $r['name'],
    ];
}, $pdo->query('SELECT id, route_number, name FROM routes ORDER BY route_number')->fetchAll());

json_response(200, [
    'ok'       => true,
    'vehicles' => $vehicles,
    'routes'   => $routes,
]);
