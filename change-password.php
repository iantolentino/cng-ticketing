<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_login(); $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $password = $_POST['password'] ?? ''; $confirmation = $_POST['password_confirmation'] ?? '';
    if (strlen($password) < 12) $error = 'Use a password of at least 12 characters.';
    elseif ($password !== $confirmation) $error = 'The passwords do not match.';
    else { db()->prepare('UPDATE users SET password_hash=?,must_change_password=0 WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]); refresh_current_user(); redirect(default_landing_page()); }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Set your password</title><link rel="stylesheet" href="assets/css/app.css"></head><body class="auth-page"><main class="auth-card"><h1>Set your password</h1><p class="page-subtitle">Choose a secure password to continue.</p><?php if($error):?><p class="auth-error"><?=e($error)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><label>New password<input name="password" type="password" minlength="12" required autocomplete="new-password"></label><label>Confirm password<input name="password_confirmation" type="password" minlength="12" required autocomplete="new-password"></label><button class="button">Save password</button></form></main></body></html>
