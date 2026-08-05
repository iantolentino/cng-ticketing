<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_login(); $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $password = $_POST['password'] ?? '';
    if (strlen($password) < 12) $error = 'Use a password of at least 12 characters.';
    else { db()->prepare('UPDATE users SET password_hash=?,must_change_password=0 WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]); refresh_current_user(); redirect(default_landing_page()); }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Set your password</title><link rel="icon" href="assets/favicon.svg"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/css/app.css"></head><body class="auth-page"><main class="auth-card"><div class="auth-logos"><img class="auth-strata-logo" src="assets/stratastaff-logo.png" alt="Strata Staff Global"><span class="auth-logo-divider"></span><img class="auth-jamesons-logo" src="assets/jamesons-logo.svg" alt="Jamesons Strata Management"></div><h1>Set your password</h1><p class="page-subtitle">Choose a secure password to continue.</p><?php if($error):?><p class="auth-error"><?=e($error)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><label>New password<input name="password" type="password" minlength="12" required autocomplete="new-password"></label><button class="button">Save password</button></form></main></body></html>
