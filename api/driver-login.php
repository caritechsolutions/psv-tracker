<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/rate_limit.php';

// POST /driver-login.php — the Android app calls this to exchange credentials
// for a capture token.
// Body:  { "username": "...", "password": "..." }
// Returns: { "ok": true, "token": "<64-hex>", "driver": "Name" }
//
// On success an issued row is inserted into driver_tokens; the returned token
// is what the app then sends as `Authorization: Bearer <token>` to sign-on/ping.

require_post();
$body = read_json_body();

$username = trim((string) ($body['username'] ?? ''));
$password = (string) ($body['password'] ?? '');

if ($username === '' || $password === '') {
    json_response(422, ['ok' => false, 'error' => 'username and password are required']);
}

$ip = client_ip();
if (rate_limit_blocked('driver_login', $ip)) {
    json_response(429, ['ok' => false, 'error' => 'too_many_attempts']);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, name, password_hash, status FROM drivers WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$driver = $stmt->fetch();

// One generic failure for unknown user / disabled / no password set / wrong
// password — never reveal which, to avoid username enumeration.
if (!$driver
    || $driver['status'] !== 'active'
    || $driver['password_hash'] === null
    || !password_verify($password, $driver['password_hash'])) {
    rate_limit_fail('driver_login', $ip);
    json_response(401, ['ok' => false, 'error' => 'invalid_credentials']);
}

rate_limit_clear('driver_login', $ip);

$token = bin2hex(random_bytes(32)); // 64 hex chars, fits VARCHAR(64)
$pdo->prepare('INSERT INTO driver_tokens (driver_id, token, label) VALUES (?, ?, ?)')
    ->execute([(int) $driver['id'], $token, 'app login']);

json_response(200, [
    'ok'     => true,
    'token'  => $token,
    'driver' => $driver['name'],
]);
