<?php
declare(strict_types=1);
require __DIR__ . '/../api/db.php';
require __DIR__ . '/auth.php';

owner_session_start();

// Already signed in — go to the portal.
if (current_owner() !== null) {
    header('Location: index.php');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    owner_csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } else {
        $stmt = db()->prepare(
            'SELECT id, username, name, password_hash, status FROM owners WHERE username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $owner = $stmt->fetch();

        // One generic message whether the owner is unknown, inactive, has no
        // password set, or the password is wrong — no enumeration.
        if ($owner && $owner['status'] === 'active'
            && $owner['password_hash'] !== null
            && password_verify($password, $owner['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['owner_id']       = (int) $owner['id'];
            $_SESSION['owner_username'] = $owner['username'];
            $_SESSION['owner_name']     = $owner['name'] ?? '';
            header('Location: index.php');
            exit;
        }
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
  <title>PSV Owner — Sign in</title>
  <style>
    body { font-family: system-ui, sans-serif; background: #f4f5f7; margin: 0;
           display: flex; min-height: 100vh; align-items: center; justify-content: center; }
    .card { background: #fff; padding: 2rem; border-radius: 8px; width: 320px;
            box-shadow: 0 1px 4px rgba(0,0,0,.15); }
    h1 { font-size: 1.2rem; margin: 0 0 .25rem; }
    .sub { color: #5b6573; font-size: .85rem; margin: 0 0 1rem; }
    label { display: block; font-size: .85rem; margin: .75rem 0 .25rem; color: #333; }
    input { width: 100%; padding: .5rem; box-sizing: border-box; border: 1px solid #ccc;
            border-radius: 4px; font-size: 1rem; }
    button { width: 100%; margin-top: 1.25rem; padding: .6rem; border: 0; border-radius: 4px;
             background: #0d4ea0; color: #fff; font-size: 1rem; cursor: pointer; }
    .error { background: #fdecea; color: #b71c1c; padding: .5rem .75rem; border-radius: 4px;
             font-size: .85rem; margin-bottom: .5rem; }
  </style>
</head>
<body>
  <form class="card" method="post" action="login.php">
    <?= owner_csrf_field() ?>
    <h1>PSV Tracker</h1>
    <p class="sub">Owner portal</p>
    <?php if ($error !== ''): ?>
      <div class="error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <label for="username">Username</label>
    <input id="username" name="username" autocomplete="username" autofocus value="<?= $username_value ?>">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password">
    <button type="submit">Sign in</button>
  </form>
</body>
</html>
