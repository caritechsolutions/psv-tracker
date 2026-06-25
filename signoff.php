<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

// POST /signoff.php
// Auth:  Authorization: Bearer <api_token>
// Body:  { "shift_id": 12 }   // optional; if omitted, closes whatever is open for this driver

require_post();
$driver = authenticate_driver();
$body   = read_json_body();

$shift_id = isset($body['shift_id']) ? (int) $body['shift_id'] : 0;
$pdo = db();

if ($shift_id > 0) {
    $stmt = $pdo->prepare('UPDATE shifts SET status = "closed", ended_at = NOW() WHERE id = ? AND driver_id = ? AND status = "open"');
    $stmt->execute([$shift_id, $driver['id']]);
} else {
    $stmt = $pdo->prepare('UPDATE shifts SET status = "closed", ended_at = NOW() WHERE driver_id = ? AND status = "open"');
    $stmt->execute([$driver['id']]);
}

json_response(200, ['ok' => true, 'closed' => $stmt->rowCount()]);
