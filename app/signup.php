<?php
declare(strict_types=1);
require __DIR__ . '/../api/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/partials/layout.php';

if (current_rider() !== null) {
    header('Location: account.php');
    exit;
}

$error = '';
$emailVal = '';
$nameVal = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    rider_csrf_verify();
    $email    = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $name     = trim($_POST['display_name'] ?? '');
    $emailVal = $email;
    $nameVal  = $name;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        try {
            $stmt = db()->prepare('INSERT INTO riders (email, password_hash, display_name) VALUES (?, ?, ?)');
            $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $name !== '' ? $name : null]);
            rider_login([
                'id'           => (int) db()->lastInsertId(),
                'email'        => $email,
                'display_name' => $name,
            ]);
            header('Location: account.php');
            exit;
        } catch (PDOException $e) {
            // Duplicate email (or any integrity error): stay generic so signup
            // can't be used to confirm which emails are registered.
            if ((int) $e->getCode() === 23000) {
                $error = "We couldn't create that account. If you already have one, try logging in.";
            } else {
                throw $e;
            }
        }
    }
}

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);
rider_layout_start(['title' => 'Sign up — PSV Tracker', 'nav' => '<a href="index.php">Map</a>']);
?>
<div class="rider-content">
  <form class="auth-card" method="post" action="signup.php">
    <?= rider_csrf_field() ?>
    <h1>Create your account</h1>
    <p class="sub">Earn points for the rides you take.</p>
    <?php if ($error !== ''): ?><div class="auth-error"><?= $h($error) ?></div><?php endif; ?>
    <label>Email
      <input name="email" type="email" autocomplete="email" required value="<?= $h($emailVal) ?>">
    </label>
    <label>Display name (optional)
      <input name="display_name" maxlength="120" value="<?= $h($nameVal) ?>">
    </label>
    <label>Password
      <input name="password" type="password" autocomplete="new-password" minlength="8" required>
    </label>
    <small class="hint">At least 8 characters.</small>
    <button type="submit">Create account</button>
    <p class="alt">Already have an account? <a href="login.php">Sign in</a></p>
  </form>
</div>
<?php
rider_layout_end();
