<?php
require __DIR__ . '/app/bootstrap.php';

if (!app_is_setup()) redirect('setup.php');
if (current_user()) redirect('index.php');

$error = '';
$values = [
    'full_name' => trim(request_string($_POST, 'full_name')),
    'email' => strtolower(trim(request_string($_POST, 'email'))),
    'username' => strtolower(trim(request_string($_POST, 'username'))),
    'role_id' => request_string($_POST, 'role_id'),
];
$allowedEmailDomains = ['stratastaff.com', 'stratastaffglobal.com', 'jamesons.com.au'];
$emailPattern = '.+@(' . implode('|', array_map(static fn(string $domain): string => preg_quote($domain, '/'), $allowedEmailDomains)) . ')';
$roles = db()->query("SELECT id,name FROM roles WHERE slug <> 'super-admin' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = $values['full_name'];
    $email = $values['email'];
    $username = $values['username'];
    $password = request_string($_POST, 'password');
    $confirmation = request_string($_POST, 'password_confirmation');
    $roleId = (int) request_string($_POST, 'role_id');
    $validRole = db()->prepare("SELECT 1 FROM roles WHERE id=? AND slug <> 'super-admin'");
    $validRole->execute([$roleId]);
    $emailDomain = substr(strrchr($email, '@') ?: '', 1);
    if ($name === '' || strlen($name) > 150) {
        $error = 'Enter your full name, using no more than 150 characters.';
    } elseif (!preg_match('/^[a-z0-9._-]{3,80}$/', $username)) {
        $error = 'Username must be 3 to 80 characters and use only letters, numbers, dots, underscores, or hyphens. Spaces and @ are not allowed.';
    } elseif (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid company email address.';
    } elseif (!in_array($emailDomain, $allowedEmailDomains, true)) {
        $error = 'Use a company email ending in ' . implode(', ', array_map(static fn(string $domain): string => '@' . $domain, $allowedEmailDomains)) . '.';
    } elseif (strlen($password) < 12 || strlen($password) > 1024) {
        $error = 'Password must be between 12 and 1024 characters.';
    } elseif ($password !== $confirmation) {
        $error = 'The passwords do not match.';
    } elseif (!$validRole->fetchColumn()) {
        $error = 'Select a valid requested role.';
    } else {
        $existing = db()->prepare('SELECT username,email FROM users WHERE LOWER(username)=? OR LOWER(email)=?');
        $existing->execute([$username, $email]);
        foreach ($existing->fetchAll() as $account) {
            if (strtolower((string) $account['username']) === $username) {
                $error = 'That username is already registered. Choose another username, or contact the Super Admin about the existing account.';
                break;
            }
            if (strtolower((string) $account['email']) === $email) {
                $error = 'That email address is already registered. Contact the Super Admin to activate or update the existing account.';
            }
        }
        if ($error === '') {
            try {
                db()->prepare("INSERT INTO users(role_id,username,full_name,email,password_hash,must_change_password,is_active,approval_status) VALUES(?,?,?,?,?,0,0,'pending')")->execute([$roleId, $username, $name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                redirect('register.php?submitted=1');
            } catch (PDOException $exception) {
                if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                    $error = 'That username or email was registered by another submission. Contact the Super Admin about the existing account.';
                } else {
                    system_log('error', 'registration_failed', 'Account registration could not be saved.', ['code' => $exception->getCode()]);
                    $error = 'Your registration could not be saved. Please try again or contact the Super Admin.';
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Register</title><link rel="icon" href="assets/favicon.svg"><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="auth-page"><main class="auth-shell"><section class="auth-card"><h1>Register</h1>
<?php if (isset($_GET['submitted'])): ?>
    <p class="action-notice" role="status">Thank you. Your registration is waiting for Super Admin approval. Please wait a few minutes, then try logging in.</p><p><a class="button button-secondary" href="login.php">Return to log in</a></p>
<?php else: ?>
    <?php if ($error): ?><p class="auth-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>Full name<input name="full_name" maxlength="150" value="<?= e($values['full_name']) ?>" required autocomplete="name"></label>
        <label>Company email<input name="email" type="email" maxlength="190" pattern="<?= e($emailPattern) ?>" value="<?= e($values['email']) ?>" required autocomplete="email" aria-describedby="registration-email-help"></label>
        <p class="form-help" id="registration-email-help">Allowed domains: <?= e(implode(', ', array_map(static fn(string $domain): string => '@' . $domain, $allowedEmailDomains))) ?>.</p>
        <label>Username<input name="username" maxlength="80" pattern="[A-Za-z0-9._\-]{3,80}" value="<?= e($values['username']) ?>" required autocomplete="username" aria-describedby="registration-username-help"></label>
        <p class="form-help" id="registration-username-help">3–80 characters. Use letters, numbers, dots, underscores, or hyphens; no spaces or @.</p>
        <label>Password<input name="password" type="password" minlength="12" maxlength="1024" required autocomplete="new-password" aria-describedby="registration-password-help"></label>
        <p class="form-help" id="registration-password-help">Use at least 12 characters. Enter your password again if the form reports an error.</p>
        <label>Confirm password<input name="password_confirmation" type="password" minlength="12" maxlength="1024" required autocomplete="new-password"></label>
        <label>Requested role<select name="role_id" required><option value="">Select a role</option><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>"<?= (string) $role['id'] === $values['role_id'] ? ' selected' : '' ?>><?= e($role['name']) ?></option><?php endforeach; ?></select></label>
        <div class="auth-actions"><button class="button">Submit registration</button><a class="button button-secondary" href="login.php">Return to log in</a></div>
    </form>
<?php endif; ?>
</section></main></body></html>
