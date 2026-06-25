<?php
declare(strict_types=1);

/**
 * Shared admin shell: top bar + left nav rail + content frame.
 * Functions only — including this file produces no output, so it's harmless
 * if requested directly. Each page:
 *
 *   require __DIR__ . '/auth.php';
 *   require __DIR__ . '/partials/layout.php';
 *   $admin = require_admin_page();
 *   admin_layout_start(['admin' => $admin, 'active' => 'map', 'title' => 'Live map']);
 *   ... page body ...
 *   admin_layout_end();
 */

const ADMIN_NAV = [
    'map'      => ['label' => 'Map',      'href' => 'index.php',    'icon' => 'ti-map-2'],
    'ads'      => ['label' => 'Ads',      'href' => 'ads.php',      'icon' => 'ti-speakerphone'],
    'fleet'    => ['label' => 'Fleet',    'href' => 'fleet.php',    'icon' => 'ti-bus'],
    'owners'   => ['label' => 'Owners',   'href' => 'owners.php',   'icon' => 'ti-users'],
    'reports'  => ['label' => 'Reports',  'href' => 'reports.php',  'icon' => 'ti-chart-bar'],
    'settings' => ['label' => 'Settings', 'href' => 'settings.php', 'icon' => 'ti-settings'],
];

// Tabler Icons webfont (pinned + SRI). The stylesheet pulls its font from the
// same CDN path, so this integrity check covers the icon glyphs too.
const ADMIN_ICONS_CSS = 'https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.44.0/dist/tabler-icons.min.css';
const ADMIN_ICONS_SRI = 'sha384-ccZHbezhtZWmNy0cg8odL0D/jFU5k5HIls9y78Qd6lWor7rpvFIZtK0fTFG4z456';

function admin_layout_start(array $opts): void
{
    $admin     = $opts['admin'] ?? ['name' => '', 'username' => ''];
    $active    = (string) ($opts['active'] ?? '');
    $title     = (string) ($opts['title'] ?? 'Admin');
    $fullBleed = !empty($opts['full_bleed']);
    $head      = (string) ($opts['head'] ?? '');
    $showCount = !empty($opts['show_count']);

    $who = ($admin['name'] ?? '') !== '' ? $admin['name'] : ($admin['username'] ?? '');
    $h   = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PSV Admin — <?= $h($title) ?></title>
  <link rel="stylesheet" href="<?= $h(ADMIN_ICONS_CSS) ?>" integrity="<?= $h(ADMIN_ICONS_SRI) ?>" crossorigin="anonymous">
  <link rel="stylesheet" href="assets/admin.css">
  <?= $head ?>
</head>
<body class="<?= $fullBleed ? 'full-bleed' : '' ?>">
  <header class="topbar">
    <div class="brand"><i class="ti ti-bus"></i><strong>PSV Tracker</strong></div>
    <div id="status" class="livecount"><?= $showCount ? 'Loading&hellip;' : '' ?></div>
    <div class="user">Signed in as <strong><?= $h((string) $who) ?></strong> &middot; <a href="logout.php">Log out</a></div>
  </header>
  <div class="shell">
    <nav class="rail" aria-label="Sections">
      <?php foreach (ADMIN_NAV as $key => $item): ?>
        <a class="nav-item<?= $key === $active ? ' active' : '' ?>" href="<?= $h($item['href']) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
          <i class="ti <?= $h($item['icon']) ?>" aria-hidden="true"></i><span><?= $h($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <main class="content<?= $fullBleed ? ' full-bleed' : '' ?>">
<?php
}

function admin_layout_end(array $opts = []): void
{
    $scripts = (string) ($opts['scripts'] ?? '');
    ?>
    </main>
  </div>
  <?= $scripts ?>
</body>
</html>
<?php
}

/** Standard body for not-yet-built sections. */
function admin_coming_soon(string $label, string $icon = 'ti-tools'): void
{
    $h = htmlspecialchars($label, ENT_QUOTES);
    $i = htmlspecialchars($icon, ENT_QUOTES);
    echo <<<HTML
    <div class="placeholder">
      <i class="ti {$i}" aria-hidden="true"></i>
      <h2>{$h}</h2>
      <p>Coming soon.</p>
    </div>
HTML;
}
