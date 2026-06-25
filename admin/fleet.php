<?php
declare(strict_types=1);
require __DIR__ . '/../api/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/fleet_lib.php';
require __DIR__ . '/partials/layout.php';

$admin = require_admin_page();
$pdo   = db();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    fleet_handle_post($pdo); // mutates then redirects (exits)
}

admin_session_start();
$flash = $_SESSION['fleet_flash'] ?? null;
unset($_SESSION['fleet_flash']);

$tab = $_GET['tab'] ?? 'vehicles';
if (!in_array($tab, FLEET_TABS, true)) {
    $tab = 'vehicles';
}

$h = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES);
$tabLabels = ['vehicles' => 'Vehicles', 'drivers' => 'Drivers', 'routes' => 'Routes', 'owners' => 'Owners'];

admin_layout_start(['admin' => $admin, 'active' => 'fleet', 'title' => 'Fleet']);
?>
<div class="fleet-page">
  <h2>Fleet</h2>
  <nav class="fleet-tabs">
    <?php foreach ($tabLabels as $key => $label): ?>
      <a class="<?= $key === $tab ? 'active' : '' ?>" href="fleet.php?tab=<?= $key ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if ($flash): ?>
    <div class="flash <?= $flash['type'] === 'ok' ? 'flash-ok' : 'flash-err' ?>"><?= $h($flash['msg']) ?></div>
  <?php endif; ?>

  <?php require __DIR__ . '/partials/fleet_' . $tab . '.php'; // $tab is whitelisted above ?>
</div>
<?php
admin_layout_end();
