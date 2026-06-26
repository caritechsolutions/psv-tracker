<?php
declare(strict_types=1);
require __DIR__ . '/../api/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/partials/layout.php';

$rider = require_rider_page();
$riderId = (int) $rider['id'];
$pdo = db();

$balance = (int) $pdo->query('SELECT COALESCE(SUM(point_awarded),0) FROM rides WHERE rider_id = ' . $riderId)->fetchColumn();

// Ride history with the rider's own rating (if any) for each ride.
$hist = $pdo->prepare(
    'SELECT r.id, r.checked_in_at, r.point_awarded,
            v.registration, v.label,
            rt.route_number, rt.name AS route_name,
            rg.id AS rating_id, rg.vehicle_stars, rg.driver_stars
       FROM rides r
       JOIN vehicles v  ON v.id = r.vehicle_id
       JOIN shifts   s  ON s.id = r.shift_id
       JOIN routes   rt ON rt.id = s.route_id
       LEFT JOIN ratings rg ON rg.ride_id = r.id
      WHERE r.rider_id = ?
      ORDER BY r.checked_in_at DESC'
);
$hist->execute([$riderId]);
$rides = $hist->fetchAll();

$who = $rider['display_name'] !== '' ? $rider['display_name'] : $rider['email'];
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);
$stars = static fn (int $n): string => str_repeat('★', $n) . str_repeat('☆', 5 - $n);

rider_layout_start(['title' => 'My account — PSV Tracker', 'nav' => '<a href="index.php">Map</a>']);
?>
<div class="rider-content">
  <div class="account-box">
    <h1>My account</h1>
    <p>Signed in as <strong><?= $h($who) ?></strong></p>

    <div class="points">
      <span class="points-num"><?= $balance ?></span>
      <span class="points-label">point<?= $balance === 1 ? '' : 's' ?></span>
    </div>

    <h2>Ride history</h2>
    <?php if (!$rides): ?>
      <p class="muted">No rides yet. Check in to a van on the map to earn your first point.</p>
    <?php else: ?>
      <ul class="ride-list">
        <?php foreach ($rides as $r): ?>
          <li data-ride="<?= (int) $r['id'] ?>">
            <div class="ride-main">
              <span class="reg"><?= $h($r['registration']) ?></span>
              <?php if ($r['label']): ?><span class="label"><?= $h($r['label']) ?></span><?php endif; ?>
              <?php if ((int) $r['point_awarded'] === 1): ?><span class="pt">+1</span><?php endif; ?>
            </div>
            <div class="ride-meta">Route <?= $h($r['route_number']) ?> — <?= $h($r['route_name']) ?> · <?= $h($r['checked_in_at']) ?></div>

            <div class="rate-slot">
              <?php if ($r['rating_id'] !== null): ?>
                <div class="rated">You rated · Vehicle <span class="stars"><?= $stars((int) $r['vehicle_stars']) ?></span> · Driver <span class="stars"><?= $stars((int) $r['driver_stars']) ?></span></div>
              <?php else: ?>
                <form class="rate-form" data-ride="<?= (int) $r['id'] ?>">
                  <div class="rate-row">
                    <label>Vehicle
                      <select class="rate-vehicle" required>
                        <option value="">—</option>
                        <?php for ($i = 1; $i <= 5; $i++): ?><option value="<?= $i ?>"><?= $i ?> ★</option><?php endfor; ?>
                      </select>
                    </label>
                    <label>Driver
                      <select class="rate-driver" required>
                        <option value="">—</option>
                        <?php for ($i = 1; $i <= 5; $i++): ?><option value="<?= $i ?>"><?= $i ?> ★</option><?php endfor; ?>
                      </select>
                    </label>
                  </div>
                  <input class="rate-comment" type="text" maxlength="500" placeholder="Comment (optional)">
                  <button class="rate-submit" type="button">Submit rating</button>
                  <span class="rate-msg"></span>
                </form>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="post" action="logout.php">
      <?= rider_csrf_field() ?>
      <button type="submit" class="btn-secondary">Log out</button>
    </form>
  </div>
</div>
<?php
rider_layout_end([
    'scripts' => '<script>window.RIDER_CSRF=' . json_encode(rider_csrf_token()) . ';</script>'
        . '<script src="assets/rider-account.js"></script>',
]);
