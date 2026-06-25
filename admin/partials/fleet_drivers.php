<?php
/** Drivers tab. Expects $pdo and $h from fleet.php. Password hashes are never selected. */
declare(strict_types=1);

$editing = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare('SELECT id, name, username, status FROM drivers WHERE id = ?');
    $s->execute([(int) $_GET['edit']]);
    $editing = $s->fetch() ?: null;
}
$drivers = $pdo->query(
    'SELECT d.id, d.name, d.username, d.status,
            (SELECT COUNT(*) FROM driver_tokens t WHERE t.driver_id = d.id) AS token_count
       FROM drivers d ORDER BY d.name'
)->fetchAll();

$tokens = [];
if ($editing) {
    $s = $pdo->prepare('SELECT id, token, label, created_at, last_used_at FROM driver_tokens WHERE driver_id = ? ORDER BY created_at DESC');
    $s->execute([(int) $editing['id']]);
    $tokens = $s->fetchAll();
}
?>
<form class="ads-form" method="post" action="fleet.php?tab=drivers">
  <?= csrf_field() ?>
  <input type="hidden" name="entity" value="driver">
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
    <input name="password" type="password" autocomplete="new-password"<?= $editing ? '' : ' required' ?>>
  </label>
  <label>Status
    <select name="status">
      <option value="active"<?= (!$editing || $editing['status'] === 'active') ? ' selected' : '' ?>>active</option>
      <option value="suspended"<?= ($editing && $editing['status'] === 'suspended') ? ' selected' : '' ?>>suspended</option>
    </select>
  </label>
  <div class="form-actions">
    <button type="submit" class="btn"><?= $editing ? 'Save changes' : 'Create driver' ?></button>
    <?php if ($editing): ?><a class="btn-cancel" href="fleet.php?tab=drivers">Cancel</a><?php endif; ?>
  </div>
</form>

<?php if ($editing): ?>
  <div class="token-panel">
    <div class="ads-head">
      <h3>App tokens &mdash; <?= $h($editing['name']) ?></h3>
      <form method="post" action="fleet.php?tab=drivers" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="entity" value="driver">
        <input type="hidden" name="action" value="token_generate">
        <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
        <button type="submit" class="btn-sm">Generate new token</button>
      </form>
    </div>
    <p class="hint">The driver's app exchanges username + password for one of these tokens. Treat them like passwords.</p>
    <table class="ads-table token-list">
      <thead><tr><th>Token</th><th>Label</th><th>Created</th><th>Last used</th><th></th></tr></thead>
      <tbody>
        <?php if (!$tokens): ?><tr><td colspan="5" class="empty">No tokens yet. Generate one for the app.</td></tr><?php endif; ?>
        <?php foreach ($tokens as $t): ?>
          <tr>
            <td><code class="token"><?= $h($t['token']) ?></code></td>
            <td><?= $h($t['label']) ?: '&mdash;' ?></td>
            <td><small><?= $h($t['created_at']) ?></small></td>
            <td><small><?= $t['last_used_at'] ? $h($t['last_used_at']) : 'never' ?></small></td>
            <td class="row-actions">
              <form method="post" action="fleet.php?tab=drivers" class="inline" onsubmit="return confirm('Revoke this token? The app using it will stop working.');">
                <?= csrf_field() ?>
                <input type="hidden" name="entity" value="driver">
                <input type="hidden" name="action" value="token_revoke">
                <input type="hidden" name="token_id" value="<?= (int) $t['id'] ?>">
                <button type="submit" class="btn-sm btn-danger">Revoke</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<table class="ads-table">
  <thead><tr><th>Name</th><th>Username</th><th>Status</th><th>Tokens</th><th></th></tr></thead>
  <tbody>
    <?php if (!$drivers): ?><tr><td colspan="5" class="empty">No drivers yet.</td></tr><?php endif; ?>
    <?php foreach ($drivers as $d): ?>
      <tr>
        <td><?= $h($d['name']) ?></td>
        <td><?= $h($d['username']) ?></td>
        <td><span class="badge <?= $d['status'] === 'active' ? 'badge-on' : 'badge-off' ?>"><?= $h($d['status']) ?></span></td>
        <td><?= (int) $d['token_count'] ?></td>
        <td class="row-actions">
          <a class="btn-sm" href="fleet.php?tab=drivers&edit=<?= (int) $d['id'] ?>">Edit</a>
          <form method="post" action="fleet.php?tab=drivers" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="entity" value="driver">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
            <button type="submit" class="btn-sm"><?= $d['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
