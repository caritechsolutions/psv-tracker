<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

rider_session_start();

// CSRF-protected POST to prevent logout-CSRF.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    rider_csrf_verify();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
header('Location: index.php');
exit;
