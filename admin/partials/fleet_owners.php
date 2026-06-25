<?php
/** Owners tab — minimal management so vehicles can be assigned an owner now.
    The full owner-facing portal is roadmap #6. Expects $pdo and $h. */
declare(strict_types=1);

$editing = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM owners WHERE id = ?');
    $s->execute([(int) $_GET['edit']]);
    $editing = $s->fetch() ?: null;
}
$owners = $pdo->query(
    'SELECT o.*, (SELECT COUNT(*) FROM vehicles v WHERE v.owner_id = o.id) AS vehicle_count
       FROM owners o ORDER BY o.name'
)->fetchAll();
?>
<form class="ads-form" method="post" action="fleet.php?tab=owners">
  <?= csrf_field() ?>
  <input type="hidden" name="entity" value="owner">
  <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
  <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
  <h3><?= $editing ? 'Edit owner' : 'New owner' ?></h3>

  <label>Name
    <input name="name" maxlength="160" required value="<?= $h($editing['name'] ?? '') ?>">
  </label>
  <div class="form-row">
    <label>Contact name
      <input name="contact_name" maxlength="160" value="<?= $h($editing['contact_name'] ?? '') ?>">
    </label>
    <label>Email
      <input name="email" type="email" maxlength="190" value="<?= $h($editing['email'] ?? '') ?>">
    </label>
    <label>Phone
      <input name="phone" maxlength="40" value="<?= $h($editing['phone'] ?? '') ?>">
    </label>
  </div>
  <label>Status
    <select name="status">
      <option value="active"<?= (!$editing || $editing['status'] === 'active') ? ' selected' : '' ?>>active</option>
      <option value="inactive"<?= ($editing && $editing['status'] === 'inactive') ? ' selected' : '' ?>>inactive</option>
    </select>
  </label>
  <div class="form-actions">
    <button type="submit" class="btn"><?= $editing ? 'Save changes' : 'Create owner' ?></button>
    <?php if ($editing): ?><a class="btn-cancel" href="fleet.php?tab=owners">Cancel</a><?php endif; ?>
  </div>
</form>

<table class="ads-table">
  <thead><tr><th>Name</th><th>Contact</th><th>Email</th><th>Phone</th><th>Vehicles</th><th>Status</th><th></th></tr></thead>
  <tbody>
    <?php if (!$owners): ?><tr><td colspan="7" class="empty">No owners yet.</td></tr><?php endif; ?>
    <?php foreach ($owners as $o): ?>
      <tr>
        <td><?= $h($o['name']) ?></td>
        <td><?= $h($o['contact_name']) ?: '&mdash;' ?></td>
        <td><?= $h($o['email']) ?: '&mdash;' ?></td>
        <td><?= $h($o['phone']) ?: '&mdash;' ?></td>
        <td><?= (int) $o['vehicle_count'] ?></td>
        <td><span class="badge <?= $o['status'] === 'active' ? 'badge-on' : 'badge-off' ?>"><?= $h($o['status']) ?></span></td>
        <td class="row-actions">
          <a class="btn-sm" href="fleet.php?tab=owners&edit=<?= (int) $o['id'] ?>">Edit</a>
          <form method="post" action="fleet.php?tab=owners" class="inline" onsubmit="return confirm('Delete this owner? Their vehicles will be left without an owner.');">
            <?= csrf_field() ?>
            <input type="hidden" name="entity" value="owner">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
            <button type="submit" class="btn-sm btn-danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
