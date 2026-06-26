<?php
declare(strict_types=1);

/**
 * Simple DB-backed brute-force limiter for the login endpoints. Keyed per
 * (scope, client IP): after RATE_LIMIT_MAX failed attempts within
 * RATE_LIMIT_WINDOW seconds, that bucket is blocked until the window (measured
 * from the first failure) elapses. A successful login clears the bucket.
 *
 * Requires db() from api/db.php.
 */

const RATE_LIMIT_MAX    = 5;     // failures allowed within the window
const RATE_LIMIT_WINDOW = 900;   // seconds (15 minutes)

/**
 * Best-effort real client IP. Behind Nginx Proxy Manager the client address
 * arrives in X-Forwarded-For (the proxy's own address is REMOTE_ADDR), so the
 * first XFF hop is the real client.
 *
 * TRUST ASSUMPTION: this trusts X-Forwarded-For, which is only safe because the
 * app is reached solely through the proxy. If the app were also directly
 * reachable, a client could spoof XFF to evade or poison the limiter — so the
 * listener must be exposed only via the proxy.
 */
function client_ip(): string
{
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($xff !== '') {
        $first = trim(explode(',', $xff)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
}

function rate_limit_bucket(string $scope, string $ip): string
{
    return substr($scope . '|' . $ip, 0, 160);
}

/** True if this (scope, ip) is currently over the limit within the window. */
function rate_limit_blocked(string $scope, string $ip): bool
{
    $stmt = db()->prepare('SELECT fail_count, UNIX_TIMESTAMP(first_failed_at) AS first_ts FROM auth_throttle WHERE bucket = ?');
    $stmt->execute([rate_limit_bucket($scope, $ip)]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    if (time() - (int) $row['first_ts'] > RATE_LIMIT_WINDOW) {
        return false; // window elapsed; the next failure resets the counter
    }
    return (int) $row['fail_count'] >= RATE_LIMIT_MAX;
}

/** Record a failed attempt; starts a fresh window if the old one elapsed. */
function rate_limit_fail(string $scope, string $ip): void
{
    $win = (int) RATE_LIMIT_WINDOW;
    db()->prepare(
        'INSERT INTO auth_throttle (bucket, fail_count, first_failed_at, last_failed_at)
         VALUES (?, 1, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
           fail_count      = IF(first_failed_at < NOW() - INTERVAL ' . $win . ' SECOND, 1, fail_count + 1),
           first_failed_at = IF(first_failed_at < NOW() - INTERVAL ' . $win . ' SECOND, NOW(), first_failed_at),
           last_failed_at  = NOW()'
    )->execute([rate_limit_bucket($scope, $ip)]);
}

/** Clear the bucket (call on a successful login). */
function rate_limit_clear(string $scope, string $ip): void
{
    db()->prepare('DELETE FROM auth_throttle WHERE bucket = ?')->execute([rate_limit_bucket($scope, $ip)]);
}
