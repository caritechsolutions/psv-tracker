<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

owner_session_start();

// Logout is a POST form (CSRF-protected) to prevent logout-CSRF.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    owner_csrf_verify();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
header('Location: login.php');
exit;
