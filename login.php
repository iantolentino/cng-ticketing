<?php
require __DIR__ . '/app/bootstrap.php';
if (!app_is_setup()) redirect('setup.php');
if (current_user()) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $login = strtolower(trim($_POST['username'] ?? ''));
    $q = db()->prepare('SELECT id,password_hash,must_change_password,is_active,approval_status FROM users WHERE LOWER(username)=? OR LOWER(email)=?');
    $q->execute([$login, $login]);
    $user = $q->fetch();
    if ($user && password_verify($_POST['password'] ?? '', $user['password_hash']) && $user['approval_status'] === 'pending') {
        $error = 'Your registration is awaiting Super Admin approval. Please wait a few minutes or contact the developer or Super Admin.';
    } elseif ($user && password_verify($_POST['password'] ?? '', $user['password_hash']) && !$user['is_active']) {
        $error = 'Your account is not activated yet. Please wait a few minutes or contact the Super Admin.';
    } elseif ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
        session_regenerate_id(true); $_SESSION['user_id'] = $user['id'];
        db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$user['id']]);
        refresh_current_user();
        redirect($user['must_change_password'] ? 'change-password.php' : default_landing_page());
    } else {
        $error = 'Invalid username or password.';
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in</title><link rel="icon" href="assets/favicon.svg"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap"><link rel="stylesheet" href="assets/css/app.css"></head><body class="auth-page"><main class="auth-shell"><section class="auth-intro"><p class="auth-eyebrow">CNG TICKETING</p><h1>Keep every request moving.</h1><p>Record, assign, and follow up on service requests from one clear workspace.</p><div class="auth-features"><span>Clear ownership</span><span>Progress updates</span><span>One shared record</span></div></section><section class="auth-card"><div class="auth-logos"><img class="auth-strata-logo" src="assets/stratastaff-logo.png" alt="Strata Staff Global"><span class="auth-logo-divider"></span><img class="auth-jamesons-logo" src="assets/jamesons-logo.svg" alt="Jamesons Strata Management"></div><p class="auth-eyebrow">ACCOUNT ACCESS</p><h2>Sign in</h2><p class="page-subtitle">Access the CNG / Jamesons ticketing portal.</p><?php if($error):?><p class="auth-error" role="alert"><?=e($error)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><label>Username<input name="username" required autocomplete="username"></label><label>Password<input name="password" type="password" required autocomplete="current-password"></label><button class="button">Sign in</button></form><p><a href="register.php">Request an account</a></p></section></main></body></html>
