<?php
declare(strict_types=1);

/**
 * Shared shift-log queries for the owner shift view and the admin Reports view.
 * Pass $ownerId to scope to one owner's vehicles' shifts; pass null for all
 * shifts (admin). LIMIT/OFFSET are server-computed ints, inlined safely; the
 * owner id is bound. Requires db() from api/db.php.
 */

function shiftlog_count(PDO $pdo, ?int $ownerId): int
{
    $where = $ownerId !== null ? 'WHERE v.owner_id = ?' : '';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM shifts s JOIN vehicles v ON v.id = s.vehicle_id $where");
    $stmt->execute($ownerId !== null ? [$ownerId] : []);
    return (int) $stmt->fetchColumn();
}

/** One page of shifts, most-recent first, with driver/vehicle/route, duration and speeding count. */
function shiftlog_fetch(PDO $pdo, ?int $ownerId, int $limit, int $offset): array
{
    $where = $ownerId !== null ? 'WHERE v.owner_id = ?' : '';
    $sql =
        'SELECT s.id, s.started_at, s.ended_at, s.status,
                d.name AS driver_name,
                v.registration, o.name AS owner_name,
                r.route_number, r.name AS route_name,
                TIMESTAMPDIFF(SECOND, s.started_at, s.ended_at) AS dur_seconds,
                (SELECT COUNT(*) FROM speeding_events se WHERE se.shift_id = s.id) AS speeding_count
           FROM shifts s
           JOIN drivers  d ON d.id = s.driver_id
           JOIN vehicles v ON v.id = s.vehicle_id
           JOIN routes   r ON r.id = s.route_id
           LEFT JOIN owners o ON o.id = v.owner_id
           ' . $where . '
          ORDER BY s.started_at DESC
          LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($ownerId !== null ? [$ownerId] : []);
    return $stmt->fetchAll();
}

/** Speeding events for the given shifts, grouped by shift_id. $shiftIds are already scoped. */
function shiftlog_events(PDO $pdo, array $shiftIds): array
{
    if (!$shiftIds) {
        return [];
    }
    $in = implode(',', array_fill(0, count($shiftIds), '?'));
    $stmt = $pdo->prepare(
        'SELECT id, shift_id, started_at, ended_at, peak_speed_kmh, speed_limit_kmh,
                TIMESTAMPDIFF(SECOND, started_at, ended_at) AS dur_seconds
           FROM speeding_events
          WHERE shift_id IN (' . $in . ')
          ORDER BY started_at'
    );
    $stmt->execute(array_map('intval', $shiftIds));
    $byShift = [];
    foreach ($stmt->fetchAll() as $e) {
        $byShift[(int) $e['shift_id']][] = $e;
    }
    return $byShift;
}

/** Human duration from a TIMESTAMPDIFF second count; null -> open/ongoing label. */
function shiftlog_duration($seconds, string $openLabel = 'in progress'): string
{
    if ($seconds === null) {
        return $openLabel;
    }
    $s = (int) $seconds;
    if ($s < 60) {
        return $s . 's';
    }
    $m = intdiv($s, 60);
    $h = intdiv($m, 60);
    $m %= 60;
    return $h > 0 ? ($h . 'h ' . $m . 'm') : ($m . 'm');
}
