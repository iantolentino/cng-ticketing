<?php
require __DIR__ . '/app/bootstrap.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $q = db()->prepare('SELECT id, full_name, email FROM users WHERE LOWER(email) = ? AND is_active = 1 LIMIT 1');
        $q->execute([$email]);
        $user = $q->fetch();
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $pdo = db();
            $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ? OR expires_at < NOW()')->execute([(int) $user['id']]);
            $pdo->prepare('INSERT INTO password_reset_tokens(user_id, token_hash, expires_at) VALUES(?,?,DATE_ADD(NOW(), INTERVAL 1 HOUR))')->execute([(int) $user['id'], hash('sha256', $token)]);
            $baseUrl = trim((string) ($config['app']['base_url'] ?? ''));
            if ($baseUrl === '') {
                $baseUrl = rtrim((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\'), '/');
            }
            send_mail_to_addresses([$user['email']], 'Password reset request', "Hello {$user['full_name']},\n\nUse this link within 1 hour to reset your password:\n{$baseUrl}/reset-password.php?token={$token}\n\nIf you did not request this, you can ignore this email.");
        }
    }
    $message = 'If an active account uses that email address, a password reset link has been sent.';
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Forgot password</title><link rel="icon" href="assets/favicon.svg"><link rel="stylesheet" href="assets/css/app.css"></head><body class="auth-page"><main class="auth-card"><div class="auth-logos"><img class="auth-strata-logo" src="assets/stratastaff-logo.png" alt="Strata Staff Global"><span class="auth-logo-divider"></span><img class="auth-jamesons-logo" src="assets/jamesons-logo.svg" alt="Jamesons Strata Management"></div><h1>Forgot password</h1><p class="page-subtitle">Enter your account email to receive a reset link.</p><?php if ($message): ?><p class="notice"><?= e($message) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Email<input name="email" type="email" required autocomplete="email"></label><button class="button">Send reset link</button></form><p><a href="login.php">Return to sign in</a></p></main></body></html>
