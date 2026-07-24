<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_login(); $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $password = $_POST['password'] ?? '';
    if (strlen($password) < 12) $error = 'Use a password of at least 12 characters.';
    else { db()->prepare('UPDATE users SET password_hash=?,must_change_password=0 WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]); redirect('index.php'); }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><title>Change password</title></head><body><main><h1>Set your password</h1><?php if($error):?><p><?=e($error)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><label>New password <input name="password" type="password" minlength="12" required autocomplete="new-password"></label><button>Save password</button></form></main></body></html>

