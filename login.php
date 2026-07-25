<?php
require __DIR__ . '/app/bootstrap.php';
if (!app_is_setup()) redirect('setup.php');
if (current_user()) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $q = db()->prepare('SELECT id,password_hash,must_change_password FROM users WHERE username = ? AND is_active = 1');
    $q->execute([strtolower(trim($_POST['username'] ?? ''))]);
    $user = $q->fetch();
    if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
        session_regenerate_id(true); $_SESSION['user_id'] = $user['id'];
        db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
        redirect($user['must_change_password'] ? 'change-password.php' : 'index.php');
    }
    $error = 'Invalid username or password.';
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in</title><link rel="icon" href="assets/favicon.svg"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/css/app.css"></head><body class="auth-page"><main class="auth-card"><div class="auth-logos"><img class="auth-strata-logo" src="assets/stratastaff-logo.png" alt="Strata Staff Global"><span class="auth-logo-divider"></span><img class="auth-jamesons-logo" src="assets/jamesons-logo.svg" alt="Jamesons Strata Management"></div><p class="eyebrow">CNG / Jamesons Ticketing System</p><h1>Sign in</h1><p class="page-subtitle">CNG / Jamesons account ticketing portal</p><?php if($error):?><p class="auth-error"><?=e($error)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><label>Username<input name="username" required autocomplete="username"></label><label>Password<input name="password" type="password" required autocomplete="current-password"></label><button class="button">Sign in</button></form></main></body></html>
