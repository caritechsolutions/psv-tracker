<?php
declare(strict_types=1);
require __DIR__ . '/../api/db.php';
require __DIR__ . '/../api/rate_limit.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/partials/layout.php';

if (current_rider() !== null) {
    header('Location: account.php');
    exit;
}

$error = '';
$emailVal = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    rider_csrf_verify();
    $ip       = client_ip();
    $email    = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $emailVal = $email;

    if (rate_limit_blocked('rider_login', $ip)) {
        http_response_code(429);
        $error = 'Too many failed attempts. Please wait a few minutes and try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } else {
        $stmt = db()->prepare('SELECT id, email, password_hash, display_name, status FROM riders WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $rider = $stmt->fetch();

        // One generic message whether the email is unknown, the account is
        // disabled, or the password is wrong — no enumeration.
        if ($rider && $rider['status'] === 'active'
            && password_verify($password, $rider['password_hash'])) {
            rate_limit_clear('rider_login', $ip);
            rider_login($rider);
            header('Location: account.php');
            exit;
        }
        rate_limit_fail('rider_login', $ip);
        $error = 'Invalid email or password.';
    }
}

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);
rider_layout_start(['title' => 'Sign in — PSV Tracker', 'nav' => '<a href="index.php">Map</a>']);
?>
<div class="rider-content">
  <form class="auth-card" method="post" action="login.php">
    <?= rider_csrf_field() ?>
    <h1>Sign in</h1>
    <?php if ($error !== ''): ?><div class="auth-error"><?= $h($error) ?></div><?php endif; ?>
    <label>Email
      <input name="email" type="email" autocomplete="email" required autofocus value="<?= $h($emailVal) ?>">
    </label>
    <label>Password
      <input name="password" type="password" autocomplete="current-password" required>
    </label>
    <button type="submit">Sign in</button>
    <p class="alt">New here? <a href="signup.php">Create an account</a></p>
  </form>
</div>
<?php
rider_layout_end();
