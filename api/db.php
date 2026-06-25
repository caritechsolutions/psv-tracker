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
 * Authenticate the driver by api_token. Halts with 401 on failure.
 * Returns the driver row on success.
 */
function authenticate_driver(): array
{
    $token = bearer_token();
    if ($token === null) {
        json_response(401, ['ok' => false, 'error' => 'missing_token']);
    }
    $stmt = db()->prepare('SELECT id, name, status FROM drivers WHERE api_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $driver = $stmt->fetch();
    if (!$driver || $driver['status'] !== 'active') {
        json_response(401, ['ok' => false, 'error' => 'invalid_token']);
    }
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
