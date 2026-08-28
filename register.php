<?php
require __DIR__ . '/app/bootstrap.php';

if (!app_is_setup()) redirect('setup.php');
if (current_user()) redirect('index.php');

$error = '';
$roles = db()->query("SELECT id,name FROM roles WHERE slug <> 'super-admin' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim(request_string($_POST, 'full_name'));
    $email = strtolower(trim(request_string($_POST, 'email')));
    $username = strtolower(trim(request_string($_POST, 'username')));
    $password = request_string($_POST, 'password');
    $confirmation = request_string($_POST, 'password_confirmation');
    $roleId = (int) request_string($_POST, 'role_id');
    $validRole = db()->prepare("SELECT 1 FROM roles WHERE id=? AND slug <> 'super-admin'");
    $validRole->execute([$roleId]);
    $existing = db()->prepare('SELECT 1 FROM users WHERE username=? OR email=?');
    $existing->execute([$username, $email]);
    $validEmailDomain = (bool) preg_match('/@(stratastaff\.com|stratastaffglobal\.com|jamesons\.com\.au)$/', $email);
    if ($password !== $confirmation) {
        $error = 'The passwords do not match.';
    } elseif ($name === '' || strlen($name) > 150 || strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$validEmailDomain || !preg_match('/^[a-z0-9._-]{3,80}$/', $username) || strlen($password) < 12 || strlen($password) > 1024 || !$validRole->fetchColumn() || $existing->fetchColumn()) {
        $error = 'Enter valid details, a unique account, an approved company email domain, and a password between 12 and 1024 characters.';
    } else {
        db()->prepare("INSERT INTO users(role_id,username,full_name,email,password_hash,must_change_password,is_active,approval_status) VALUES(?,?,?,?,?,0,0,'pending')")->execute([$roleId, $username, $name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        redirect('register.php?submitted=1');
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
    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Full name<input name="full_name" maxlength="150" required autocomplete="name"></label><label>Company email<input name="email" type="email" maxlength="190" pattern=".+@(stratastaff\.com|stratastaffglobal\.com|jamesons\.com\.au)" required autocomplete="email"></label><p class="form-help">Allowed domains: @stratastaff.com, @stratastaffglobal.com, and @jamesons.com.au.</p><label>Username<input name="username" maxlength="80" pattern="[A-Za-z0-9._-]{3,80}" required autocomplete="username"></label><label>Password<input name="password" type="password" minlength="12" maxlength="1024" required autocomplete="new-password"></label><label>Confirm password<input name="password_confirmation" type="password" minlength="12" maxlength="1024" required autocomplete="new-password"></label><label>Requested role<select name="role_id" required><option value="">Select a role</option><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>"><?= e($role['name']) ?></option><?php endforeach; ?></select></label><div class="auth-actions"><button class="button">Submit registration</button><a class="button button-secondary" href="login.php">Return to log in</a></div></form>
<?php endif; ?>
</section></main></body></html>
