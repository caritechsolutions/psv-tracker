<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

// GET /api/public-positions.php — PUBLIC, unauthenticated, rider-facing feed.
// Latest position of every currently signed-on vehicle whose latest ping is
// within the freshness window. PRIVACY: this feed deliberately carries NO driver
// name or driver id — driver identity stays private to owner/admin. It also
// carries each vehicle's AGGREGATE rating (avg + count) for the public popup.
// Separate from admin/owner positions.php, which are left untouched.

const PUBLIC_POSITION_FRESHNESS_SECONDS = 120;

header('Access-Control-Allow-Origin: *'); // public read-only

$pdo = db();
$server_time = (string) $pdo->query('SELECT NOW()')->fetchColumn();

// One row per open shift: its latest position, kept only if fresh. No join to
// drivers — the public must not see who is driving. (Vehicle aggregate rating is
// wired in at the ratings checkpoint; until then it is reported as no ratings.)
$sql =
    'SELECT
        s.id           AS shift_id,
        v.registration AS registration,
        v.label        AS label,
        r.route_number AS route_number,
        r.name         AS route_name,
        p.lat, p.lng, p.speed, p.heading, p.seat_status,
        p.recorded_at, p.received_at
     FROM shifts s
     JOIN vehicles v ON v.id = s.vehicle_id
     JOIN routes   r ON r.id = s.route_id
     JOIN positions p ON p.id = (
         SELECT p2.id FROM positions p2
         WHERE p2.shift_id = s.id
         ORDER BY p2.id DESC
         LIMIT 1
     )
     WHERE s.status = "open"
       AND p.received_at >= (NOW() - INTERVAL ' . (int) PUBLIC_POSITION_FRESHNESS_SECONDS . ' SECOND)
     ORDER BY v.registration';

$rows = $pdo->query($sql)->fetchAll();

$vehicles = array_map(static function (array $row): array {
    return [
        'shift_id'     => (int) $row['shift_id'],
        'registration' => $row['registration'],
        'label'        => $row['label'],
        'route_number' => $row['route_number'],
        'route_name'   => $row['route_name'],
        'lat'          => (float) $row['lat'],
        'lng'          => (float) $row['lng'],
        'speed'        => $row['speed']   !== null ? (float) $row['speed']   : null,
        'heading'      => $row['heading'] !== null ? (int) $row['heading']   : null,
        'seat_status'  => $row['seat_status'],
        'recorded_at'  => $row['recorded_at'],
        'received_at'  => $row['received_at'],
        'rating'       => ['avg' => null, 'count' => 0],
    ];
}, $rows);

json_response(200, [
    'ok'                => true,
    'server_time'       => $server_time,
    'freshness_seconds' => PUBLIC_POSITION_FRESHNESS_SECONDS,
    'vehicles'          => $vehicles,
]);
