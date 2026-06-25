<?php
/** Vehicles tab. Expects $pdo and $h from fleet.php. */
declare(strict_types=1);

$editing = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM vehicles WHERE id = ?');
    $s->execute([(int) $_GET['edit']]);
    $editing = $s->fetch() ?: null;
}
$owners   = $pdo->query('SELECT id, name FROM owners ORDER BY name')->fetchAll();
$vehicles = $pdo->query(
    'SELECT v.*, o.name AS owner_name
       FROM vehicles v LEFT JOIN owners o ON o.id = v.owner_id
      ORDER BY v.registration'
)->fetchAll();
?>
<form class="ads-form" method="post" action="fleet.php?tab=vehicles">
  <?= csrf_field() ?>
  <input type="hidden" name="entity" value="vehicle">
  <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
  <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
  <h3><?= $editing ? 'Edit vehicle' : 'New vehicle' ?></h3>

  <label>Registration
    <input name="registration" maxlength="20" required value="<?= $h($editing['registration'] ?? '') ?>">
  </label>
  <label>Label
    <input name="label" maxlength="80" value="<?= $h($editing['label'] ?? '') ?>">
  </label>
  <div class="form-row">
    <label>Capacity
      <input name="capacity" type="number" min="0" max="65535" value="<?= $h($editing['capacity'] ?? '') ?>">
    </label>
    <label>Status
      <select name="status">
        <option value="active"<?= (!$editing || $editing['status'] === 'active') ? ' selected' : '' ?>>active</option>
        <option value="inactive"<?= ($editing && $editing['status'] === 'inactive') ? ' selected' : '' ?>>inactive</option>
      </select>
    </label>
    <label>Owner
      <select name="owner_id">
        <option value="">&mdash; none &mdash;</option>
        <?php foreach ($owners as $o): ?>
          <option value="<?= (int) $o['id'] ?>"<?= ($editing && (int) $editing['owner_id'] === (int) $o['id']) ? ' selected' : '' ?>><?= $h($o['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn"><?= $editing ? 'Save changes' : 'Create vehicle' ?></button>
    <?php if ($editing): ?><a class="btn-cancel" href="fleet.php?tab=vehicles">Cancel</a><?php endif; ?>
  </div>
</form>

<table class="ads-table">
  <thead><tr><th>Registration</th><th>Label</th><th>Capacity</th><th>Owner</th><th>Status</th><th></th></tr></thead>
  <tbody>
    <?php if (!$vehicles): ?><tr><td colspan="6" class="empty">No vehicles yet.</td></tr><?php endif; ?>
    <?php foreach ($vehicles as $v): ?>
      <tr>
        <td><?= $h($v['registration']) ?></td>
        <td><?= $h($v['label']) ?: '&mdash;' ?></td>
        <td><?= $v['capacity'] !== null ? (int) $v['capacity'] : '&mdash;' ?></td>
        <td><?= $v['owner_name'] ? $h($v['owner_name']) : '&mdash;' ?></td>
        <td><span class="badge <?= $v['status'] === 'active' ? 'badge-on' : 'badge-off' ?>"><?= $h($v['status']) ?></span></td>
        <td class="row-actions">
          <a class="btn-sm" href="fleet.php?tab=vehicles&edit=<?= (int) $v['id'] ?>">Edit</a>
          <form method="post" action="fleet.php?tab=vehicles" class="inline" onsubmit="return confirm('Delete this vehicle?');">
            <?= csrf_field() ?>
            <input type="hidden" name="entity" value="vehicle">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
            <button type="submit" class="btn-sm btn-danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
