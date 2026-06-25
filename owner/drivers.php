<?php
declare(strict_types=1);
require __DIR__ . '/../api/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/partials/layout.php';

$owner   = require_owner_page();
$ownerId = (int) $owner['id'];   // <-- session owner ONLY; never from the request
$pdo     = db();

const OWNER_DRIVER_MIN_PW = 8;

/** Stash a one-shot flash and redirect back (optionally to an edit view). */
function odrivers_redirect(string $type, string $msg, ?int $edit = null): void
{
    owner_session_start();
    $_SESSION['drivers_flash'] = ['type' => $type, 'msg' => $msg];
    header('Location: drivers.php' . ($edit ? '?edit=' . $edit : ''));
    exit;
}

/** Ownership gate: does this driver belong to the session owner? */
function odriver_owned(PDO $pdo, int $id, int $ownerId): bool
{
    $s = $pdo->prepare('SELECT 1 FROM drivers WHERE id = ? AND owner_id = ?');
    $s->execute([$id, $ownerId]);
    return (bool) $s->fetchColumn();
}

// ---- mutations ----
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    owner_csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_status') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!odriver_owned($pdo, $id, $ownerId)) {
            odrivers_redirect('err', 'Driver not found.');
        }
        $pdo->prepare("UPDATE drivers SET status = IF(status='active','suspended','active') WHERE id = ? AND owner_id = ?")
            ->execute([$id, $ownerId]);
        odrivers_redirect('ok', 'Driver updated.');
    }

    if ($action === 'signout') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!odriver_owned($pdo, $id, $ownerId)) {
            odrivers_redirect('err', 'Driver not found.');
        }
        // Revoke all app sessions for this driver (scoped via the join to owner).
        $pdo->prepare('DELETE t FROM driver_tokens t JOIN drivers d ON d.id = t.driver_id WHERE t.driver_id = ? AND d.owner_id = ?')
            ->execute([$id, $ownerId]);
        odrivers_redirect('ok', 'Signed out of all devices.', $id);
    }

    if ($action === 'create' || $action === 'update') {
        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $status   = ($_POST['status'] ?? 'active') === 'suspended' ? 'suspended' : 'active';

        if ($name === '' || $username === '') {
            odrivers_redirect('err', 'Name and username are required.');
        }

        try {
            if ($action === 'create') {
                if (strlen($password) < OWNER_DRIVER_MIN_PW) {
                    odrivers_redirect('err', 'Password must be at least ' . OWNER_DRIVER_MIN_PW . ' characters.');
                }
                // owner_id is forced to the session owner — never taken from input.
                $pdo->prepare('INSERT INTO drivers (owner_id, name, username, password_hash, status) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$ownerId, $name, $username, password_hash($password, PASSWORD_DEFAULT), $status]);
                odrivers_redirect('ok', 'Driver created.');
            }

            // update — only if the driver belongs to this owner
            $id = (int) ($_POST['id'] ?? 0);
            if (!odriver_owned($pdo, $id, $ownerId)) {
                odrivers_redirect('err', 'Driver not found.');
            }
            if ($password !== '') {
                if (strlen($password) < OWNER_DRIVER_MIN_PW) {
                    odrivers_redirect('err', 'Password must be at least ' . OWNER_DRIVER_MIN_PW . ' characters.', $id);
                }
                $pdo->prepare('UPDATE drivers SET name=?, username=?, password_hash=?, status=? WHERE id=? AND owner_id=?')
                    ->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $status, $id, $ownerId]);
            } else {
                $pdo->prepare('UPDATE drivers SET name=?, username=?, status=? WHERE id=? AND owner_id=?')
                    ->execute([$name, $username, $status, $id, $ownerId]);
            }
            odrivers_redirect('ok', 'Driver saved.', $id);
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                odrivers_redirect('err', 'That username is already taken.');
            }
            throw $e;
        }
    }

    odrivers_redirect('err', 'Unknown action.');
}

// ---- render ----
owner_session_start();
$flash = $_SESSION['drivers_flash'] ?? null;
unset($_SESSION['drivers_flash']);

// Edit prefill is owner-scoped: requesting another owner's id yields nothing.
$editing = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare('SELECT id, name, username, status FROM drivers WHERE id = ? AND owner_id = ?');
    $s->execute([(int) $_GET['edit'], $ownerId]);
    $editing = $s->fetch() ?: null;
}

$drivers = $pdo->prepare(
    'SELECT d.id, d.name, d.username, d.status,
            (SELECT COUNT(*) FROM driver_tokens t WHERE t.driver_id = d.id) AS token_count
       FROM drivers d WHERE d.owner_id = ? ORDER BY d.name'
);
$drivers->execute([$ownerId]);
$drivers = $drivers->fetchAll();

$h = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES);

owner_layout_start(['owner' => $owner, 'title' => 'Drivers', 'active' => 'drivers']);
?>
<div class="owner-content">
  <h2>My drivers</h2>
  <p class="muted">Create a login for each of your drivers. They sign in to the driver app with the username and password you set here.</p>

  <?php if ($flash): ?>
    <div class="flash <?= $flash['type'] === 'ok' ? 'flash-ok' : 'flash-err' ?>"><?= $h($flash['msg']) ?></div>
  <?php endif; ?>

  <form class="o-form" method="post" action="drivers.php">
    <?= owner_csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
    <h3><?= $editing ? 'Edit driver' : 'New driver' ?></h3>

    <label>Name
      <input name="name" maxlength="120" required value="<?= $h($editing['name'] ?? '') ?>">
    </label>
    <label>Username
      <input name="username" maxlength="60" required autocomplete="off" value="<?= $h($editing['username'] ?? '') ?>">
    </label>
    <label>Password<?= $editing ? ' (leave blank to keep current)' : '' ?>
      <input name="password" type="password" autocomplete="new-password" minlength="<?= OWNER_DRIVER_MIN_PW ?>"<?= $editing ? '' : ' required' ?>>
    </label>
    <small class="hint">At least <?= OWNER_DRIVER_MIN_PW ?> characters.</small>
    <label>Status
      <select name="status">
        <option value="active"<?= (!$editing || $editing['status'] === 'active') ? ' selected' : '' ?>>active</option>
        <option value="suspended"<?= ($editing && $editing['status'] === 'suspended') ? ' selected' : '' ?>>suspended</option>
      </select>
    </label>
    <div class="form-actions">
      <button type="submit" class="btn"><?= $editing ? 'Save changes' : 'Create driver' ?></button>
      <?php if ($editing): ?><a class="btn-cancel" href="drivers.php">Cancel</a><?php endif; ?>
    </div>
  </form>

  <table class="o-table">
    <thead><tr><th>Name</th><th>Username</th><th>Status</th><th>App sessions</th><th></th></tr></thead>
    <tbody>
      <?php if (!$drivers): ?><tr><td colspan="5" class="empty">No drivers yet. Create one above.</td></tr><?php endif; ?>
      <?php foreach ($drivers as $d): ?>
        <tr>
          <td><?= $h($d['name']) ?></td>
          <td><?= $h($d['username']) ?></td>
          <td><span class="badge <?= $d['status'] === 'active' ? 'badge-on' : 'badge-off' ?>"><?= $h($d['status']) ?></span></td>
          <td><?= (int) $d['token_count'] ?></td>
          <td class="row-actions">
            <a class="btn-sm" href="access.php?driver=<?= (int) $d['id'] ?>">Access</a>
            <a class="btn-sm" href="drivers.php?edit=<?= (int) $d['id'] ?>">Edit</a>
            <form method="post" action="drivers.php" class="inline">
              <?= owner_csrf_field() ?>
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
              <button type="submit" class="btn-sm"><?= $d['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
            </form>
            <form method="post" action="drivers.php" class="inline" onsubmit="return confirm('Sign this driver out of all devices? They will need to log in again.');">
              <?= owner_csrf_field() ?>
              <input type="hidden" name="action" value="signout">
              <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
              <button type="submit" class="btn-sm"<?= (int) $d['token_count'] === 0 ? ' disabled' : '' ?>>Sign out everywhere</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
owner_layout_end();
