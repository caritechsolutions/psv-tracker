<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require __DIR__ . '/partials/layout.php';

$rider = require_rider_page();
$who   = $rider['display_name'] !== '' ? $rider['display_name'] : $rider['email'];

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);
rider_layout_start(['title' => 'My account — PSV Tracker', 'nav' => '<a href="index.php">Map</a>']);
?>
<div class="rider-content">
  <div class="account-box">
    <h1>My account</h1>
    <p>Signed in as <strong><?= $h($who) ?></strong><?php if ($rider['display_name'] !== ''): ?> &middot; <span class="muted"><?= $h($rider['email']) ?></span><?php endif; ?></p>

    <p class="muted">Your points balance and ride history will appear here.</p>

    <form method="post" action="logout.php">
      <?= rider_csrf_field() ?>
      <button type="submit" class="btn-secondary">Log out</button>
    </form>
  </div>
</div>
<?php
rider_layout_end();
