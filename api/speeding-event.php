<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

// POST /api/speeding-event.php — driver app reports a speeding event.
// Auth: Authorization: Bearer <token>  (same authenticate_driver() as sign-on/
// ping; this is a NEW file — sign-on/ping/sign-off are untouched).
//
//   { "action":"open", "shift_id":12, "peak_speed_kmh":78, "speed_limit_kmh":60,
//     "started_at":"2026-06-26T14:03:11Z" }   // started_at optional -> now
//        -> 201 { "ok":true, "event_id": N }
//
//   { "action":"close", "event_id":N, "peak_speed_kmh":81,
//     "ended_at":"2026-06-26T14:05:40Z" }     // both optional; ended_at -> now
//        -> 200 { "ok":true }
//
// driver_id/vehicle_id are taken from the shift, never the request. A dropped
// "close" simply leaves the event open-ended (ended_at NULL).

require_post();
$driver = authenticate_driver();
$body   = read_json_body();
$action = $body['action'] ?? '';
$pdo    = db();

if ($action === 'open') {
    $shift_id = isset($body['shift_id']) ? (int) $body['shift_id'] : 0;
    $peak     = isset($body['peak_speed_kmh'])  ? (int) $body['peak_speed_kmh']  : 0;
    $limit    = isset($body['speed_limit_kmh']) ? (int) $body['speed_limit_kmh'] : 0;

    if ($shift_id <= 0 || $peak <= 0 || $limit <= 0) {
        json_response(422, ['ok' => false, 'error' => 'shift_id, peak_speed_kmh and speed_limit_kmh are required']);
    }
    if ($peak > 65535 || $limit > 65535) {
        json_response(422, ['ok' => false, 'error' => 'speed out of range']);
    }

    // Shift must belong to this driver and still be open.
    $stmt = $pdo->prepare('SELECT vehicle_id FROM shifts WHERE id = ? AND driver_id = ? AND status = "open" LIMIT 1');
    $stmt->execute([$shift_id, (int) $driver['id']]);
    $vehicle_id = $stmt->fetchColumn();
    if ($vehicle_id === false) {
        json_response(409, ['ok' => false, 'error' => 'no_open_shift']);
    }

    $started_at = isset($body['started_at']) ? normalize_ts($body['started_at']) : date('Y-m-d H:i:s');

    $ins = $pdo->prepare(
        'INSERT INTO speeding_events (shift_id, vehicle_id, driver_id, started_at, peak_speed_kmh, speed_limit_kmh)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([$shift_id, (int) $vehicle_id, (int) $driver['id'], $started_at, $peak, $limit]);

    json_response(201, ['ok' => true, 'event_id' => (int) $pdo->lastInsertId()]);
}

if ($action === 'close') {
    $event_id = isset($body['event_id']) ? (int) $body['event_id'] : 0;
    if ($event_id <= 0) {
        json_response(422, ['ok' => false, 'error' => 'event_id is required']);
    }
    $ended_at = isset($body['ended_at']) ? normalize_ts($body['ended_at']) : date('Y-m-d H:i:s');
    $peak     = isset($body['peak_speed_kmh']) ? (int) $body['peak_speed_kmh'] : 0;

    // Scoped to this driver's own events. GREATEST keeps the highest reported peak.
    if ($peak > 0) {
        $stmt = $pdo->prepare('UPDATE speeding_events SET ended_at = ?, peak_speed_kmh = GREATEST(peak_speed_kmh, ?) WHERE id = ? AND driver_id = ?');
        $stmt->execute([$ended_at, $peak, $event_id, (int) $driver['id']]);
    } else {
        $stmt = $pdo->prepare('UPDATE speeding_events SET ended_at = ? WHERE id = ? AND driver_id = ?');
        $stmt->execute([$ended_at, $event_id, (int) $driver['id']]);
    }

    // rowCount() is 0 both when the event isn't theirs AND on a no-op re-close;
    // only the former is an error, so confirm existence before 404ing.
    if ($stmt->rowCount() === 0) {
        $chk = $pdo->prepare('SELECT 1 FROM speeding_events WHERE id = ? AND driver_id = ? LIMIT 1');
        $chk->execute([$event_id, (int) $driver['id']]);
        if (!$chk->fetchColumn()) {
            json_response(404, ['ok' => false, 'error' => 'event_not_found']);
        }
    }

    json_response(200, ['ok' => true]);
}

json_response(422, ['ok' => false, 'error' => 'unknown action']);
