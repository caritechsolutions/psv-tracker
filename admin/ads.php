<?php
declare(strict_types=1);
require __DIR__ . '/../api/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/ads_lib.php';
require __DIR__ . '/partials/layout.php';

$admin = require_admin_page();
$pdo   = db();

/** Validate an optional date string; '' -> null, valid 'Y-m-d' -> itself, bad -> false. */
function ads_clean_date(string $v)
{
    $v = trim($v);
    if ($v === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : false;
}

/** Stash a one-shot flash message and redirect back to the list (PRG). */
function ads_redirect(string $type, string $msg): void
{
    admin_session_start();
    $_SESSION['ads_flash'] = ['type' => $type, 'msg' => $msg];
    header('Location: ads.php');
    exit;
}

// ---- handle mutations (POST), then redirect ----
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE ads SET active = 1 - active WHERE id = ?')->execute([$id]);
        ads_redirect('ok', 'Ad updated.');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT image_file FROM ads WHERE id = ?');
        $stmt->execute([$id]);
        $file = $stmt->fetchColumn();
        $pdo->prepare('DELETE FROM ads WHERE id = ?')->execute([$id]);
        if ($file !== false) {
            ads_delete_image((string) $file);
        }
        ads_redirect('ok', 'Ad deleted.');
    }

    if ($action === 'create' || $action === 'update') {
        $title  = trim($_POST['title'] ?? '');
        $url    = trim($_POST['click_url'] ?? '');
        $weight = max(0, min(65535, (int) ($_POST['weight'] ?? 100)));
        $active = isset($_POST['active']) ? 1 : 0;
        $starts = ads_clean_date($_POST['starts_on'] ?? '');
        $ends   = ads_clean_date($_POST['ends_on'] ?? '');

        if ($title === '') {
            ads_redirect('err', 'Title is required.');
        }
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            ads_redirect('err', 'Click-through URL is not a valid URL.');
        }
        if ($starts === false || $ends === false) {
            ads_redirect('err', 'Dates must be in YYYY-MM-DD format.');
        }
        if ($starts !== null && $ends !== null && $starts > $ends) {
            ads_redirect('err', 'Start date must be on or before the end date.');
        }
        $clickValue = $url === '' ? null : $url;

        if ($action === 'create') {
            $newImage = ads_store_uploaded_image($_FILES['image'] ?? [], $err);
            if ($newImage === null) {
                ads_redirect('err', $err ?? 'Image upload failed.');
            }
            $pdo->prepare(
                'INSERT INTO ads (title, image_file, click_url, weight, active, starts_on, ends_on)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$title, $newImage, $clickValue, $weight, $active, $starts, $ends]);
            ads_redirect('ok', 'Ad created.');
        }

        // update
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT image_file FROM ads WHERE id = ?');
        $stmt->execute([$id]);
        $oldImage = $stmt->fetchColumn();
        if ($oldImage === false) {
            ads_redirect('err', 'Ad not found.');
        }

        // A new image is optional on edit; only replace if one was uploaded.
        $replace = isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $newImage = null;
        if ($replace) {
            $newImage = ads_store_uploaded_image($_FILES['image'], $err);
            if ($newImage === null) {
                ads_redirect('err', $err ?? 'Image upload failed.');
            }
        }

        if ($newImage !== null) {
            $pdo->prepare(
                'UPDATE ads SET title=?, image_file=?, click_url=?, weight=?, active=?, starts_on=?, ends_on=? WHERE id=?'
            )->execute([$title, $newImage, $clickValue, $weight, $active, $starts, $ends, $id]);
            ads_delete_image((string) $oldImage); // drop the replaced file
        } else {
            $pdo->prepare(
                'UPDATE ads SET title=?, click_url=?, weight=?, active=?, starts_on=?, ends_on=? WHERE id=?'
            )->execute([$title, $clickValue, $weight, $active, $starts, $ends, $id]);
        }
        ads_redirect('ok', 'Ad saved.');
    }

    ads_redirect('err', 'Unknown action.');
}

// ---- render ----
admin_session_start();
$flash = $_SESSION['ads_flash'] ?? null;
unset($_SESSION['ads_flash']);

// Editing an existing ad?
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM ads WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

// Same order the public endpoint serves, so admin matches the rider rotation.
$ads = $pdo->query('SELECT * FROM ads ORDER BY weight ASC, id ASC')->fetchAll();

$h = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES);
$today = (new DateTimeImmutable())->format('Y-m-d');

admin_layout_start([
    'admin'  => $admin,
    'active' => 'ads',
    'title'  => 'Ads',
]);
?>
<div class="ads-page">
  <div class="ads-head">
    <h2>Ads</h2>
    <a class="btn-link" href="ads-preview.php" target="_blank" rel="noopener">
      <i class="ti ti-player-play" aria-hidden="true"></i> Preview rotation
    </a>
  </div>

  <?php if ($flash): ?>
    <div class="flash <?= $flash['type'] === 'ok' ? 'flash-ok' : 'flash-err' ?>"><?= $h($flash['msg']) ?></div>
  <?php endif; ?>

  <form class="ads-form" method="post" action="ads.php" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
    <h3><?= $editing ? 'Edit ad' : 'New ad' ?></h3>

    <label>Title
      <input name="title" maxlength="160" required value="<?= $h($editing['title'] ?? '') ?>">
    </label>

    <label>Image<?= $editing ? ' (leave empty to keep current)' : '' ?>
      <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"<?= $editing ? '' : ' required' ?>>
    </label>
    <small class="hint">JPEG, PNG, GIF or WebP &middot; max 2&nbsp;MB &middot; <?= ADS_MIN_W ?>&times;<?= ADS_MIN_H ?> to <?= ADS_MAX_W ?>&times;<?= ADS_MAX_H ?> px</small>
    <?php if ($editing): ?>
      <div class="current-img"><img src="<?= $h(ads_image_url($editing['image_file'])) ?>" alt="current"></div>
    <?php endif; ?>

    <label>Click-through URL (optional)
      <input name="click_url" type="url" placeholder="https://example.com" value="<?= $h($editing['click_url'] ?? '') ?>">
    </label>

    <div class="form-row">
      <label>Weight
        <input name="weight" type="number" min="0" max="65535" value="<?= (int) ($editing['weight'] ?? 100) ?>">
      </label>
      <label>Starts on
        <input name="starts_on" type="date" value="<?= $h($editing['starts_on'] ?? '') ?>">
      </label>
      <label>Ends on
        <input name="ends_on" type="date" value="<?= $h($editing['ends_on'] ?? '') ?>">
      </label>
    </div>

    <label class="check">
      <input type="checkbox" name="active" value="1" <?= (!$editing || (int) $editing['active'] === 1) ? 'checked' : '' ?>>
      Active
    </label>

    <div class="form-actions">
      <button type="submit" class="btn"><?= $editing ? 'Save changes' : 'Create ad' ?></button>
      <?php if ($editing): ?><a class="btn-cancel" href="ads.php">Cancel</a><?php endif; ?>
    </div>
  </form>

  <table class="ads-table">
    <thead>
      <tr><th>Preview</th><th>Title</th><th>Weight</th><th>Window</th><th>Active</th><th></th></tr>
    </thead>
    <tbody>
      <?php if (!$ads): ?>
        <tr><td colspan="6" class="empty">No ads yet. Create one above.</td></tr>
      <?php endif; ?>
      <?php foreach ($ads as $ad):
          $expired = $ad['ends_on'] !== null && $ad['ends_on'] < $today;
          $pending = $ad['starts_on'] !== null && $ad['starts_on'] > $today;
          $live = (int) $ad['active'] === 1 && !$expired && !$pending;
      ?>
        <tr>
          <td><img class="thumb" src="<?= $h(ads_image_url($ad['image_file'])) ?>" alt="<?= $h($ad['title']) ?>"></td>
          <td>
            <?= $h($ad['title']) ?>
            <?php if ($ad['click_url']): ?><br><small class="hint"><?= $h($ad['click_url']) ?></small><?php endif; ?>
          </td>
          <td><?= (int) $ad['weight'] ?></td>
          <td>
            <small>
              <?= $ad['starts_on'] ? $h($ad['starts_on']) : '&mdash;' ?> &rarr; <?= $ad['ends_on'] ? $h($ad['ends_on']) : '&mdash;' ?>
              <?php if ($expired): ?><br><span class="badge badge-off">expired</span>
              <?php elseif ($pending): ?><br><span class="badge badge-off">pending</span><?php endif; ?>
            </small>
          </td>
          <td>
            <form method="post" action="ads.php" class="inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int) $ad['id'] ?>">
              <button type="submit" class="badge <?= $live ? 'badge-on' : 'badge-off' ?>" title="Toggle active">
                <?= (int) $ad['active'] === 1 ? ($live ? 'live' : 'on') : 'off' ?>
              </button>
            </form>
          </td>
          <td class="row-actions">
            <a class="btn-sm" href="ads.php?edit=<?= (int) $ad['id'] ?>">Edit</a>
            <form method="post" action="ads.php" class="inline" onsubmit="return confirm('Delete this ad and its image?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $ad['id'] ?>">
              <button type="submit" class="btn-sm btn-danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
admin_layout_end();
