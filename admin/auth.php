<?php
declare(strict_types=1);

/**
 * Shared session guard for the /admin portal.
 * Include this, then call require_admin_page() (HTML pages) or
 * require_admin_json() (JSON endpoints) at the top of each protected file.
 */

function admin_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/** Returns the logged-in admin (id/username/name) or null. */
function current_admin(): ?array
{
    admin_session_start();
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    return [
        'id'       => (int) $_SESSION['admin_id'],
        'username' => (string) ($_SESSION['admin_username'] ?? ''),
        'name'     => (string) ($_SESSION['admin_name'] ?? ''),
    ];
}

/** Guard for HTML pages: redirects to the login page when not signed in. */
function require_admin_page(): array
{
    $admin = current_admin();
    if ($admin === null) {
        header('Location: login.php');
        exit;
    }
    return $admin;
}

/** Guard for JSON endpoints: returns 401 JSON when not signed in. */
function require_admin_json(): array
{
    $admin = current_admin();
    if ($admin === null) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'unauthenticated']);
        exit;
    }
    return $admin;
}

/**
 * CSRF protection for state-changing admin POSTs. Token lives in the session;
 * csrf_field() drops it into a form, csrf_verify() checks it on submit.
 */
function csrf_token(): string
{
    admin_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/** Halts with 400 if the submitted token doesn't match the session token. */
function csrf_verify(): void
{
    admin_session_start();
    $sent = $_POST['csrf'] ?? '';
    $have = (string) ($_SESSION['csrf'] ?? '');
    if ($have === '' || !is_string($sent) || !hash_equals($have, $sent)) {
        http_response_code(400);
        header('Content-Type: text/plain');
        echo 'Bad or missing CSRF token. Reload the page and try again.';
        exit;
    }
}
