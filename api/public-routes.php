<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

// GET /api/public-routes.php — PUBLIC, unauthenticated. The route list for the
// rider app's route filter.

header('Access-Control-Allow-Origin: *');

$routes = array_map(static function (array $r): array {
    return [
        'id'           => (int) $r['id'],
        'route_number' => $r['route_number'],
        'name'         => $r['name'],
    ];
}, db()->query('SELECT id, route_number, name FROM routes ORDER BY route_number')->fetchAll());

json_response(200, ['ok' => true, 'routes' => $routes]);
