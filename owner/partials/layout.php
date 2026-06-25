<?php
declare(strict_types=1);

/**
 * Minimal owner-portal layout (functions only — harmless if requested directly,
 * and denied at nginx). Deliberately independent of the admin shell.
 */

const OWNER_LEAFLET_CSS =
    '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"' .
    ' integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">';

function owner_layout_start(array $opts): void
{
    $owner = $opts['owner'] ?? ['name' => '', 'username' => ''];
    $title = (string) ($opts['title'] ?? 'Owner portal');
    $head  = (string) ($opts['head'] ?? '');
    $who   = ($owner['name'] ?? '') !== '' ? $owner['name'] : ($owner['username'] ?? '');
    $h     = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PSV Owner — <?= $h($title) ?></title>
  <?= $head ?>
  <link rel="stylesheet" href="assets/owner.css">
</head>
<body>
  <header class="owner-bar">
    <div class="brand">PSV Tracker <span class="sub">Owner portal</span></div>
    <div class="who">
      Signed in as <strong><?= $h((string) $who) ?></strong>
      <form method="post" action="logout.php" class="logout-form">
        <?= owner_csrf_field() ?>
        <button type="submit" class="logout-btn">Log out</button>
      </form>
    </div>
  </header>
  <main class="owner-main">
<?php
}

function owner_layout_end(array $opts = []): void
{
    $scripts = (string) ($opts['scripts'] ?? '');
    ?>
  </main>
  <?= $scripts ?>
</body>
</html>
<?php
}
