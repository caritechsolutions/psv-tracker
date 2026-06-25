<?php
declare(strict_types=1);
require __DIR__ . '/../api/db.php';
require __DIR__ . '/auth.php';

// GET /admin/positions.php — authenticated JSON feed for the live map.
// Returns the most recent position of every currently-signed-on vehicle whose
// latest ping was received within POSITION_FRESHNESS_SECONDS. Freshness is
// measured against received_at (server-stamped, monotonic) rather than the
// device-supplied recorded_at, so a skewed device clock can't drop a live
// vehicle off the map or keep a stale one on it.

// How recent a shift's latest ping must be for it to count as "live".
const POSITION_FRESHNESS_SECONDS = 120;

require_admin_json();

$pdo = db();

// Server clock — the frontend computes "last seen" against this, never the
// browser clock, so display is consistent regardless of the viewer's machine.
$server_time = (string) $pdo->query('SELECT NOW()')->fetchColumn();

// One row per open shift: its latest position (highest id = most recently
// received), kept only if that ping is within the freshness window.
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
       AND p.received_at >= (NOW() - INTERVAL ' . (int) POSITION_FRESHNESS_SECONDS . ' SECOND)
     ORDER BY v.registration';

$rows = $pdo->query($sql)->fetchAll();

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
    'freshness_seconds' => POSITION_FRESHNESS_SECONDS,
    'vehicles'          => $vehicles,
]);
