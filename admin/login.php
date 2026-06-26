<?php
declare(strict_types=1);
require __DIR__ . '/../api/db.php';
require __DIR__ . '/../api/rate_limit.php';
require __DIR__ . '/auth.php';

admin_session_start();

// Already signed in — go straight to the portal.
if (current_admin() !== null) {
    header('Location: index.php');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $ip = client_ip();
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (rate_limit_blocked('admin_login', $ip)) {
        http_response_code(429);
        $error = 'Too many failed attempts. Please wait a few minutes and try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } else {
        $stmt = db()->prepare(
            'SELECT id, username, name, password_hash, status
             FROM admin_users WHERE username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        // Same generic message whether the user is unknown, disabled, or the
        // password is wrong — avoids leaking which usernames exist.
        if ($admin && $admin['status'] === 'active'
            && password_verify($password, $admin['password_hash'])) {
            rate_limit_clear('admin_login', $ip);
            session_regenerate_id(true);
            $_SESSION['admin_id']       = (int) $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name']     = $admin['name'] ?? '';
            db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?')
                ->execute([(int) $admin['id']]);
            header('Location: index.php');
            exit;
        }
        rate_limit_fail('admin_login', $ip);
        $error = 'Invalid username or password.';
    }
}

$username_value = htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PSV Admin — Sign in</title>
  <style>
    body { font-family: system-ui, sans-serif; background: #f4f5f7; margin: 0;
           display: flex; min-height: 100vh; align-items: center; justify-content: center; }
    .card { background: #fff; padding: 2rem; border-radius: 8px; width: 320px;
            box-shadow: 0 1px 4px rgba(0,0,0,.15); }
    h1 { font-size: 1.25rem; margin: 0 0 1rem; }
    label { display: block; font-size: .85rem; margin: .75rem 0 .25rem; color: #333; }
    input { width: 100%; padding: .5rem; box-sizing: border-box; border: 1px solid #ccc;
            border-radius: 4px; font-size: 1rem; }
    button { width: 100%; margin-top: 1.25rem; padding: .6rem; border: 0; border-radius: 4px;
             background: #1565c0; color: #fff; font-size: 1rem; cursor: pointer; }
    .error { background: #fdecea; color: #b71c1c; padding: .5rem .75rem; border-radius: 4px;
             font-size: .85rem; margin-bottom: .5rem; }
  </style>
</head>
<body>
  <form class="card" method="post" action="login.php">
    <h1>PSV Tracker — Admin</h1>
    <?php if ($error !== ''): ?>
      <div class="error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <label for="username">Username</label>
    <input id="username" name="username" autocomplete="username" autofocus
           value="<?= $username_value ?>">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password">
    <button type="submit">Sign in</button>
  </form>
</body>
</html>
