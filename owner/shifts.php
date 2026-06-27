<?php
declare(strict_types=1);
require __DIR__ . '/../api/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/../api/shiftlog.php';
require __DIR__ . '/partials/layout.php';

$owner   = require_owner_page();
$ownerId = (int) $owner['id'];   // <-- session owner ONLY
$pdo     = db();

const OWNER_SHIFTS_PER_PAGE = 50;
$page   = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * OWNER_SHIFTS_PER_PAGE;

$total  = shiftlog_count($pdo, $ownerId);
$shifts = shiftlog_fetch($pdo, $ownerId, OWNER_SHIFTS_PER_PAGE, $offset);
$events = shiftlog_events($pdo, array_map(static fn ($r) => (int) $r['id'], $shifts));
$pages  = (int) max(1, ceil($total / OWNER_SHIFTS_PER_PAGE));

$h = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES);

owner_layout_start(['owner' => $owner, 'title' => 'Shifts', 'active' => 'shifts']);
?>
<div class="owner-content">
  <h2>Shift log</h2>
  <p class="muted">Sign-ons for your vehicles, most recent first. Expand a speeding flag to see the events.</p>

  <table class="o-table">
    <thead><tr><th>Driver</th><th>Vehicle</th><th>Route</th><th>Start</th><th>End</th><th>Duration</th><th>Speeding</th></tr></thead>
    <tbody>
      <?php if (!$shifts): ?><tr><td colspan="7" class="empty">No shifts yet.</td></tr><?php endif; ?>
      <?php foreach ($shifts as $s): $evs = $events[(int) $s['id']] ?? []; ?>
        <tr>
          <td><?= $h($s['driver_name']) ?></td>
          <td><?= $h($s['registration']) ?></td>
          <td><?= $h($s['route_number']) ?> — <?= $h($s['route_name']) ?></td>
          <td><?= $h($s['started_at']) ?></td>
          <td><?= $s['ended_at'] ? $h($s['ended_at']) : '<span class="muted">—</span>' ?></td>
          <td><?= $h(shiftlog_duration($s['dur_seconds'])) ?></td>
          <td>
            <?php if (!$evs): ?><span class="muted">—</span>
            <?php else: ?>
              <details class="speed-details">
                <summary><span class="badge badge-warn">&#9888; <?= count($evs) ?></span></summary>
                <ul class="speed-list">
                  <?php foreach ($evs as $e): ?>
                    <li><?= $h($e['started_at']) ?> &middot; <?= $h(shiftlog_duration($e['dur_seconds'], 'ongoing')) ?>
                      &middot; peak <strong><?= (int) $e['peak_speed_kmh'] ?></strong> km/h (limit <?= (int) $e['speed_limit_kmh'] ?>)</li>
                  <?php endforeach; ?>
                </ul>
              </details>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($pages > 1): ?>
    <div class="pager">
      <?php if ($page > 1): ?><a href="shifts.php?page=<?= $page - 1 ?>">&larr; Newer</a><?php endif; ?>
      <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
      <?php if ($page < $pages): ?><a href="shifts.php?page=<?= $page + 1 ?>">Older &rarr;</a><?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php
owner_layout_end();
