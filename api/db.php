<?php
declare(strict_types=1);

/**
 * Shared helpers for the PSV capture endpoints.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $cfg = require __DIR__ . '/config.php';
        $dsn = "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset={$cfg['db_charset']}";
        $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function json_response(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_response(405, ['ok' => false, 'error' => 'method_not_allowed']);
    }
}

function bearer_token(): ?string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization']
        ?? $headers['authorization']
        ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (is_string($auth) && preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
        return trim($m[1]);
    }
    return null;
}

/**
 * Authenticate the driver by bearer token. The token is looked up in
 * driver_tokens (joined to drivers, driver must be active). Halts with 401 on
 * failure. Returns the driver row (id, name, status) on success — same shape
 * and same 401 behaviour as before, so sign-on/ping/sign-off are unaffected.
 */
function authenticate_driver(): array
{
    $token = bearer_token();
    if ($token === null) {
        json_response(401, ['ok' => false, 'error' => 'missing_token']);
    }
    $stmt = db()->prepare(
        'SELECT d.id, d.name, d.status
           FROM driver_tokens t
           JOIN drivers d ON d.id = t.driver_id
          WHERE t.token = ? LIMIT 1'
    );
    $stmt->execute([$token]);
    $driver = $stmt->fetch();
    if (!$driver || $driver['status'] !== 'active') {
        json_response(401, ['ok' => false, 'error' => 'invalid_token']);
    }
    db()->prepare('UPDATE driver_tokens SET last_used_at = NOW() WHERE token = ?')->execute([$token]);
    return $driver;
}

/**
 * Accepts an ISO-8601 string, epoch seconds, or epoch milliseconds and
 * returns a MySQL DATETIME string. Falls back to "now" on anything unparseable.
 */
function normalize_ts($value): string
{
    if (is_numeric($value)) {
        $v = (int) $value;
        if ($v > 9999999999) {       // looks like milliseconds
            $v = (int) ($v / 1000);
        }
        return date('Y-m-d H:i:s', $v);
    }
    $ts = strtotime((string) $value);
    return $ts ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
}

/** Read a global setting (string), or $default if it isn't set. */
function setting_get(string $key, ?string $default = null): ?string
{
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string) $value;
}

/** Create or update a global setting. */
function setting_set(string $key, string $value): void
{
    db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    )->execute([$key, $value]);
}
