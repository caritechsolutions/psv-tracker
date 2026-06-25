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
