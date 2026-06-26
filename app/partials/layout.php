<?php
declare(strict_types=1);

/**
 * Minimal, mobile-first rider-app layout (functions only — harmless if hit
 * directly, and denied at nginx). Independent of the admin/owner shells.
 */

const RIDER_LEAFLET_CSS =
    '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"' .
    ' integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">';

function rider_layout_start(array $opts): void
{
    $title = (string) ($opts['title'] ?? 'PSV Tracker');
    $head  = (string) ($opts['head'] ?? '');
    $nav   = (string) ($opts['nav'] ?? '');
    $h     = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= $h($title) ?></title>
  <?= $head ?>
  <link rel="stylesheet" href="assets/rider.css">
</head>
<body>
  <header class="rider-bar">
    <div class="brand">PSV&nbsp;Tracker <span class="tag">catch your van</span></div>
    <nav class="rider-nav"><?= $nav ?></nav>
  </header>
  <main class="rider-main">
<?php
}

function rider_layout_end(array $opts = []): void
{
    $scripts = (string) ($opts['scripts'] ?? '');
    ?>
  </main>
  <?= $scripts ?>
</body>
</html>
<?php
}
