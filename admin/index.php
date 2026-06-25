<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
$admin = require_admin_page();
$who = $admin['name'] !== '' ? $admin['name'] : $admin['username'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PSV Admin</title>
  <style>
    body { font-family: system-ui, sans-serif; margin: 0; }
    header { background: #1565c0; color: #fff; padding: .75rem 1rem;
             display: flex; justify-content: space-between; align-items: center; }
    header a { color: #fff; }
    main { padding: 1.5rem; }
  </style>
</head>
<body>
  <header>
    <strong>PSV Tracker — Admin</strong>
    <span>Signed in as <?= htmlspecialchars($who, ENT_QUOTES) ?> · <a href="logout.php">Log out</a></span>
  </header>
  <main>
    <p>You are signed in. The live vehicle map will appear here in the next step.</p>
  </main>
</body>
</html>
