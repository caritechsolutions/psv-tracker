<?php
declare(strict_types=1);

/**
 * CLI-only helper to create (or reset the password of) an admin portal user.
 * Run on the server:  php /opt/psv-tracker/bin/create-admin.php
 *
 * It prompts for a username and password and stores a bcrypt hash in
 * admin_users — no credentials are ever passed on the command line or kept
 * in the repo. Reuses api/db.php so it talks to the same database as the API.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit("Forbidden: this script can only be run from the command line.\n");
}

require __DIR__ . '/../api/db.php';

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    $line = fgets(STDIN);
    return $line === false ? '' : trim($line);
}

function prompt_hidden(string $label): string
{
    fwrite(STDOUT, $label);
    $hasTty = stream_isatty(STDIN);
    if ($hasTty) {
        shell_exec('stty -echo 2>/dev/null');
    }
    $line = fgets(STDIN);
    if ($hasTty) {
        shell_exec('stty echo 2>/dev/null');
        fwrite(STDOUT, "\n");
    }
    return $line === false ? '' : trim($line);
}

$username = prompt('Admin username: ');
if ($username === '') {
    fwrite(STDERR, "Aborted: username is required.\n");
    exit(1);
}

$name = prompt('Display name (optional): ');
$name = $name === '' ? null : $name;

$password = prompt_hidden('Password: ');
$confirm  = prompt_hidden('Confirm password: ');

if ($password === '') {
    fwrite(STDERR, "Aborted: password is required.\n");
    exit(1);
}
if ($password !== $confirm) {
    fwrite(STDERR, "Aborted: passwords do not match.\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Aborted: password must be at least 8 characters.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

// Upsert: creating a user with an existing username resets its password and
// re-activates the account. Username is UNIQUE so ON DUPLICATE KEY handles it.
$stmt = db()->prepare(
    'INSERT INTO admin_users (username, password_hash, name, status)
     VALUES (?, ?, ?, "active")
     ON DUPLICATE KEY UPDATE
       password_hash = VALUES(password_hash),
       name          = VALUES(name),
       status        = "active"'
);
$stmt->execute([$username, $hash, $name]);

// rowCount(): 1 = inserted, 2 = updated existing (MySQL/MariaDB convention).
$action = $stmt->rowCount() === 1 ? 'created' : 'updated';
fwrite(STDOUT, "Admin user '{$username}' {$action}.\n");
exit(0);
