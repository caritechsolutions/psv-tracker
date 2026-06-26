<?php
declare(strict_types=1);

/**
 * Session guard for the /app rider area — a fourth, fully separate auth domain.
 * Its own cookie name (PSVRIDERSESS) scoped to /app/, so it shares nothing with
 * the admin (PHPSESSID), owner (PSVOWNERSESS), or driver (token) auth. An admin,
 * owner, or driver credential can never satisfy require_rider_*(), and a rider
 * cookie reaches only /app/.
 */

function rider_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('PSVRIDERSESS');
        session_set_cookie_params([
            'path'     => '/app/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/** Returns the logged-in rider (id/email/name) or null. */
function current_rider(): ?array
{
    rider_session_start();
    if (empty($_SESSION['rider_id'])) {
        return null;
    }
    return [
        'id'           => (int) $_SESSION['rider_id'],
        'email'        => (string) ($_SESSION['rider_email'] ?? ''),
        'display_name' => (string) ($_SESSION['rider_name'] ?? ''),
    ];
}

/** Guard for HTML pages: redirects to the rider login when not signed in. */
function require_rider_page(): array
{
    $rider = current_rider();
    if ($rider === null) {
        header('Location: login.php');
        exit;
    }
    return $rider;
}

/** Guard for JSON endpoints: 401 JSON when not signed in. */
function require_rider_json(): array
{
    $rider = current_rider();
    if ($rider === null) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'unauthenticated']);
        exit;
    }
    return $rider;
}

/** Log a rider in (call after verifying credentials). */
function rider_login(array $rider): void
{
    rider_session_start();
    session_regenerate_id(true);
    $_SESSION['rider_id']    = (int) $rider['id'];
    $_SESSION['rider_email'] = (string) $rider['email'];
    $_SESSION['rider_name']  = (string) ($rider['display_name'] ?? '');
}

// --- CSRF (rider session) ---
function rider_csrf_token(): string
{
    rider_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf'];
}

function rider_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(rider_csrf_token(), ENT_QUOTES) . '">';
}

function rider_csrf_verify(): void
{
    rider_session_start();
    $sent = $_POST['csrf'] ?? '';
    $have = (string) ($_SESSION['csrf'] ?? '');
    if ($have === '' || !is_string($sent) || !hash_equals($have, $sent)) {
        http_response_code(400);
        header('Content-Type: text/plain');
        echo 'Bad or missing CSRF token. Reload the page and try again.';
        exit;
    }
}
