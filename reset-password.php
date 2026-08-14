<?php
require __DIR__ . '/app/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$valid = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
$error = '';
$message = '';
if ($valid) {
    $q = db()->prepare('SELECT pr.id, pr.user_id FROM password_reset_tokens pr JOIN users u ON u.id = pr.user_id WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() AND u.is_active = 1');
    $q->execute([hash('sha256', $token)]);
    $reset = $q->fetch();
} else {
    $reset = false;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    if (!$reset) $error = 'This reset link is invalid or has expired.';
    elseif (strlen($password) < 12) $error = 'Use a password of at least 12 characters.';
    elseif ($password !== $confirmation) $error = 'The passwords do not match.';
    else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL')->execute([(int) $reset['id']]);
            if ($pdo->rowCount() !== 1) throw new RuntimeException('Reset token already used.');
            $pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), (int) $reset['user_id']]);
            $pdo->commit();
            $message = 'Your password has been reset. You can now sign in.';
            $reset = false;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'The password could not be reset. Please request a new link.';
        }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reset password</title><link rel="icon" href="assets/favicon.svg"><link rel="stylesheet" href="assets/css/app.css"></head><body class="auth-page"><main class="auth-card"><div class="auth-logos"><img class="auth-strata-logo" src="assets/stratastaff-logo.png" alt="Strata Staff Global"><span class="auth-logo-divider"></span><img class="auth-jamesons-logo" src="assets/jamesons-logo.svg" alt="Jamesons Strata Management"></div><h1>Reset password</h1><?php if ($message): ?><p class="notice"><?= e($message) ?></p><p><a href="login.php">Return to sign in</a></p><?php elseif ($error): ?><p class="auth-error"><?= e($error) ?></p><p><a href="forgot-password.php">Request a new link</a></p><?php elseif ($reset): ?><p class="page-subtitle">Choose a new password of at least 12 characters.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="token" value="<?= e($token) ?>"><label>New password<input name="password" type="password" minlength="12" required autocomplete="new-password"></label><label>Confirm password<input name="password_confirmation" type="password" minlength="12" required autocomplete="new-password"></label><button class="button">Save password</button></form><?php endif; ?></main></body></html>
