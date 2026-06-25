<?php
/** Routes tab. Expects $pdo and $h from fleet.php. */
declare(strict_types=1);

$editing = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM routes WHERE id = ?');
    $s->execute([(int) $_GET['edit']]);
    $editing = $s->fetch() ?: null;
}
$routes = $pdo->query('SELECT * FROM routes ORDER BY route_number')->fetchAll();
?>
<form class="ads-form" method="post" action="fleet.php?tab=routes">
  <?= csrf_field() ?>
  <input type="hidden" name="entity" value="route">
  <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
  <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
  <h3><?= $editing ? 'Edit route' : 'New route' ?></h3>

  <div class="form-row">
    <label>Route number
      <input name="route_number" maxlength="20" required value="<?= $h($editing['route_number'] ?? '') ?>">
    </label>
    <label>Name
      <input name="name" maxlength="160" required value="<?= $h($editing['name'] ?? '') ?>">
    </label>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn"><?= $editing ? 'Save changes' : 'Create route' ?></button>
    <?php if ($editing): ?><a class="btn-cancel" href="fleet.php?tab=routes">Cancel</a><?php endif; ?>
  </div>
</form>

<table class="ads-table">
  <thead><tr><th>Number</th><th>Name</th><th></th></tr></thead>
  <tbody>
    <?php if (!$routes): ?><tr><td colspan="3" class="empty">No routes yet.</td></tr><?php endif; ?>
    <?php foreach ($routes as $r): ?>
      <tr>
        <td><?= $h($r['route_number']) ?></td>
        <td><?= $h($r['name']) ?></td>
        <td class="row-actions">
          <a class="btn-sm" href="fleet.php?tab=routes&edit=<?= (int) $r['id'] ?>">Edit</a>
          <form method="post" action="fleet.php?tab=routes" class="inline" onsubmit="return confirm('Delete this route?');">
            <?= csrf_field() ?>
            <input type="hidden" name="entity" value="route">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button type="submit" class="btn-sm btn-danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
