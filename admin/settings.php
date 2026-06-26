<?php
declare(strict_types=1);
require __DIR__ . '/../api/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/partials/layout.php';

$admin = require_admin_page();

const SPEED_LIMIT_MIN = 1;
const SPEED_LIMIT_MAX = 300;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $raw = trim($_POST['speed_limit_kmh'] ?? '');
    admin_session_start();
    if (!ctype_digit($raw) || (int) $raw < SPEED_LIMIT_MIN || (int) $raw > SPEED_LIMIT_MAX) {
        $_SESSION['settings_flash'] = ['type' => 'err', 'msg' => 'Speed limit must be a whole number between ' . SPEED_LIMIT_MIN . ' and ' . SPEED_LIMIT_MAX . ' km/h.'];
    } else {
        setting_set('speed_limit_kmh', (string) (int) $raw);
        $_SESSION['settings_flash'] = ['type' => 'ok', 'msg' => 'Settings saved.'];
    }
    header('Location: settings.php');
    exit;
}

admin_session_start();
$flash = $_SESSION['settings_flash'] ?? null;
unset($_SESSION['settings_flash']);

$speedLimit = (int) setting_get('speed_limit_kmh', '60');
$h = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES);

admin_layout_start(['admin' => $admin, 'active' => 'settings', 'title' => 'Settings']);
?>
<div class="ads-page">
  <h2>Settings</h2>

  <?php if ($flash): ?>
    <div class="flash <?= $flash['type'] === 'ok' ? 'flash-ok' : 'flash-err' ?>"><?= $h($flash['msg']) ?></div>
  <?php endif; ?>

  <form class="ads-form" method="post" action="settings.php">
    <?= csrf_field() ?>
    <h3>Speed limit</h3>
    <label>Global speed limit (km/h)
      <input name="speed_limit_kmh" type="number" min="<?= SPEED_LIMIT_MIN ?>" max="<?= SPEED_LIMIT_MAX ?>" required value="<?= $h($speedLimit) ?>">
    </label>
    <small class="hint">Applies to all vehicles and routes. The driver app reads this at sign-on.</small>
    <div class="form-actions">
      <button type="submit" class="btn">Save settings</button>
    </div>
  </form>
</div>
<?php
admin_layout_end();
