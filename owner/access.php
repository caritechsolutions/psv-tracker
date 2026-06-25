<?php
declare(strict_types=1);
require __DIR__ . '/../api/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/partials/layout.php';

$owner   = require_owner_page();
$ownerId = (int) $owner['id'];   // <-- session owner ONLY
$pdo     = db();

/** Ownership gate for a driver. */
function access_driver_owned(PDO $pdo, int $id, int $ownerId): bool
{
    $s = $pdo->prepare('SELECT 1 FROM drivers WHERE id = ? AND owner_id = ?');
    $s->execute([$id, $ownerId]);
    return (bool) $s->fetchColumn();
}

function access_redirect(string $url, string $type, string $msg): void
{
    owner_session_start();
    $_SESSION['access_flash'] = ['type' => $type, 'msg' => $msg];
    header('Location: ' . $url);
    exit;
}

// ---- save access ----
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    owner_csrf_verify();
    $driverId = (int) ($_POST['driver_id'] ?? 0);

    // The driver must belong to the session owner.
    if (!access_driver_owned($pdo, $driverId, $ownerId)) {
        access_redirect('drivers.php', 'err', 'Driver not found.');
    }

    // The only vehicles that may be granted are THIS owner's vehicles. Anything
    // submitted that isn't the owner's (e.g. another owner's vehicle id) is
    // dropped here — request ids never widen scope.
    $ownVehicles = $pdo->prepare('SELECT id FROM vehicles WHERE owner_id = ?');
    $ownVehicles->execute([$ownerId]);
    $allowed = array_map('intval', $ownVehicles->fetchAll(PDO::FETCH_COLUMN));

    $submitted = $_POST['vehicle_ids'] ?? [];
    if (!is_array($submitted)) {
        $submitted = [];
    }
    $submitted = array_map('intval', $submitted);
    $valid = array_values(array_intersect($submitted, $allowed));

    // Replace this driver's grants with the validated set.
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM driver_vehicle_access WHERE driver_id = ?')->execute([$driverId]);
    if ($valid) {
        $ins = $pdo->prepare('INSERT INTO driver_vehicle_access (driver_id, vehicle_id) VALUES (?, ?)');
        foreach ($valid as $vid) {
            $ins->execute([$driverId, $vid]);
        }
    }
    $pdo->commit();

    access_redirect('access.php?driver=' . $driverId, 'ok', 'Vehicle access updated.');
}

// ---- render ----
$driverId = (int) ($_GET['driver'] ?? 0);
$ds = $pdo->prepare('SELECT id, name, username FROM drivers WHERE id = ? AND owner_id = ?');
$ds->execute([$driverId, $ownerId]);
$driver = $ds->fetch();
if (!$driver) {
    access_redirect('drivers.php', 'err', 'Driver not found.');
}

owner_session_start();
$flash = $_SESSION['access_flash'] ?? null;
unset($_SESSION['access_flash']);

$vs = $pdo->prepare('SELECT id, registration, label, status FROM vehicles WHERE owner_id = ? ORDER BY registration');
$vs->execute([$ownerId]);
$vehicles = $vs->fetchAll();

$gs = $pdo->prepare('SELECT vehicle_id FROM driver_vehicle_access WHERE driver_id = ?');
$gs->execute([(int) $driver['id']]);
$granted = array_fill_keys(array_map('intval', $gs->fetchAll(PDO::FETCH_COLUMN)), true);

$h = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES);

owner_layout_start(['owner' => $owner, 'title' => 'Vehicle access', 'active' => 'drivers']);
?>
<div class="owner-content">
  <p><a class="back-link" href="drivers.php">&larr; Back to drivers</a></p>
  <h2>Vehicle access &mdash; <?= $h($driver['name']) ?></h2>
  <p class="muted">Tick the vehicles <strong><?= $h($driver['username']) ?></strong> may sign on to. Only your vehicles are shown.</p>

  <?php if ($flash): ?>
    <div class="flash <?= $flash['type'] === 'ok' ? 'flash-ok' : 'flash-err' ?>"><?= $h($flash['msg']) ?></div>
  <?php endif; ?>

  <?php if (!$vehicles): ?>
    <p class="muted">You have no vehicles yet. Vehicles are assigned to you by the administrator.</p>
  <?php else: ?>
    <form class="o-form" method="post" action="access.php">
      <?= owner_csrf_field() ?>
      <input type="hidden" name="action" value="set_access">
      <input type="hidden" name="driver_id" value="<?= (int) $driver['id'] ?>">
      <ul class="access-list">
        <?php foreach ($vehicles as $v): ?>
          <li>
            <label class="check">
              <input type="checkbox" name="vehicle_ids[]" value="<?= (int) $v['id'] ?>"<?= isset($granted[(int) $v['id']]) ? ' checked' : '' ?>>
              <span class="reg"><?= $h($v['registration']) ?></span>
              <?php if ($v['label']): ?><span class="label"><?= $h($v['label']) ?></span><?php endif; ?>
              <?php if ($v['status'] !== 'active'): ?><span class="badge badge-off">inactive</span><?php endif; ?>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="form-actions">
        <button type="submit" class="btn">Save access</button>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php
owner_layout_end();
