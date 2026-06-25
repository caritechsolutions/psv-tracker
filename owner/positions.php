<?php
declare(strict_types=1);
require __DIR__ . '/../api/db.php';
require __DIR__ . '/auth.php';

// GET /owner/positions.php — owner-scoped live feed.
// Same latest-per-open-shift + received_at freshness as admin/positions.php,
// but restricted to THIS owner's vehicles. The owner id is taken from the
// session only and bound into the SQL (WHERE v.owner_id = ?). There is no
// client-supplied owner id and no broad feed to filter client-side, so an owner
// is structurally unable to see another owner's vehicles.

const OWNER_POSITION_FRESHNESS_SECONDS = 120;

$owner   = require_owner_json();
$ownerId = (int) $owner['id'];           // <-- from the owner session ONLY

$pdo = db();
$server_time = (string) $pdo->query('SELECT NOW()')->fetchColumn();

$sql =
    'SELECT
        s.id              AS shift_id,
        v.registration    AS registration,
        r.route_number    AS route_number,
        r.name            AS route_name,
        d.name            AS driver_name,
        p.lat, p.lng, p.speed, p.heading, p.seat_status,
        p.recorded_at, p.received_at
     FROM shifts s
     JOIN vehicles v ON v.id = s.vehicle_id
     JOIN routes   r ON r.id = s.route_id
     JOIN drivers  d ON d.id = s.driver_id
     JOIN positions p ON p.id = (
         SELECT p2.id FROM positions p2
         WHERE p2.shift_id = s.id
         ORDER BY p2.id DESC
         LIMIT 1
     )
     WHERE s.status = "open"
       AND v.owner_id = ?
       AND p.received_at >= (NOW() - INTERVAL ' . (int) OWNER_POSITION_FRESHNESS_SECONDS . ' SECOND)
     ORDER BY v.registration';

$stmt = $pdo->prepare($sql);
$stmt->execute([$ownerId]);
$rows = $stmt->fetchAll();

$vehicles = array_map(static function (array $row): array {
    return [
        'shift_id'     => (int) $row['shift_id'],
        'registration' => $row['registration'],
        'route_number' => $row['route_number'],
        'route_name'   => $row['route_name'],
        'driver_name'  => $row['driver_name'],
        'lat'          => (float) $row['lat'],
        'lng'          => (float) $row['lng'],
        'speed'        => $row['speed']   !== null ? (float) $row['speed']   : null,
        'heading'      => $row['heading'] !== null ? (int) $row['heading']   : null,
        'seat_status'  => $row['seat_status'],
        'recorded_at'  => $row['recorded_at'],
        'received_at'  => $row['received_at'],
    ];
}, $rows);

json_response(200, [
    'ok'                => true,
    'server_time'       => $server_time,
    'freshness_seconds' => OWNER_POSITION_FRESHNESS_SECONDS,
    'vehicles'          => $vehicles,
]);
