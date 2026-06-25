<?php
declare(strict_types=1);

/**
 * Session guard for the /owner portal — completely separate from the admin
 * session. It uses its own cookie name (PSVOWNERSESS) scoped to /owner/, so an
 * admin cookie can never satisfy require_owner() and vice-versa: they are
 * independent session stores, not two keys in one session.
 */

function owner_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('PSVOWNERSESS');
        session_set_cookie_params([
            'path'     => '/owner/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/** Returns the logged-in owner (id/username/name) or null. */
function current_owner(): ?array
{
    owner_session_start();
    if (empty($_SESSION['owner_id'])) {
        return null;
    }
    return [
        'id'       => (int) $_SESSION['owner_id'],
        'username' => (string) ($_SESSION['owner_username'] ?? ''),
        'name'     => (string) ($_SESSION['owner_name'] ?? ''),
    ];
}

/** Guard for HTML pages: redirects to the owner login when not signed in. */
function require_owner_page(): array
{
    $owner = current_owner();
    if ($owner === null) {
        header('Location: login.php');
        exit;
    }
    return $owner;
}

/** Guard for JSON endpoints: 401 JSON when not signed in. */
function require_owner_json(): array
{
    $owner = current_owner();
    if ($owner === null) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'unauthenticated']);
        exit;
    }
    return $owner;
}

// --- CSRF (owner session) ---
function owner_csrf_token(): string
{
    owner_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf'];
}

function owner_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(owner_csrf_token(), ENT_QUOTES) . '">';
}

function owner_csrf_verify(): void
{
    owner_session_start();
    $sent = $_POST['csrf'] ?? '';
    $have = (string) ($_SESSION['csrf'] ?? '');
    if ($have === '' || !is_string($sent) || !hash_equals($have, $sent)) {
        http_response_code(400);
        header('Content-Type: text/plain');
        echo 'Bad or missing CSRF token. Reload the page and try again.';
        exit;
    }
}
