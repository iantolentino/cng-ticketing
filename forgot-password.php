<?php
require __DIR__ . '/app/bootstrap.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim(request_string($_POST, 'email')));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $q = db()->prepare('SELECT id, full_name, email FROM users WHERE LOWER(email) = ? AND is_active = 1 LIMIT 1');
        $q->execute([$email]);
        $user = $q->fetch();
        if ($user) {
            $baseUrl = rtrim(trim((string) ($config['app']['base_url'] ?? '')), '/');
            // Older live configs leave this blank. Use this site's trusted origin,
            // never the request Host header, to keep the code-only upgrade compatible.
            if ($baseUrl === '') $baseUrl = 'https://cng-tickets.stratastaff.com';
            $parts = parse_url($baseUrl);
            $validBaseUrl = is_array($parts)
                && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
                && !empty($parts['host'])
                && !isset($parts['user']) && !isset($parts['pass'])
                && !isset($parts['query']) && !isset($parts['fragment'])
                && preg_match('/\A[A-Za-z0-9.-]+(?::[0-9]{1,5})?\z/', (string) $parts['host']) === 1;
            if ($validBaseUrl) {
                $token = bin2hex(random_bytes(32));
                $pdo = db();
                $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ? OR expires_at < NOW()')->execute([(int) $user['id']]);
                $pdo->prepare('INSERT INTO password_reset_tokens(user_id, token_hash, expires_at) VALUES(?,?,DATE_ADD(NOW(), INTERVAL 1 HOUR))')->execute([(int) $user['id'], hash('sha256', $token)]);
                send_mail_to_addresses([$user['email']], 'Password reset request', "Hello {$user['full_name']},\n\nUse this link within 1 hour to reset your password:\n{$baseUrl}/reset-password.php?token={$token}\n\nIf you did not request this, you can ignore this email.");
            } else {
                error_log('CNG password reset email not sent: app.base_url must contain the application URL.');
            }
        }
    }
    $message = 'If an active account uses that email address, a password reset link has been sent.';
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Forgot password</title><link rel="icon" href="assets/favicon.svg"><link rel="stylesheet" href="assets/css/app.css"></head><body class="auth-page"><main class="auth-card"><div class="auth-logos"><img class="auth-strata-logo" src="assets/stratastaff-logo.png" alt="Strata Staff Global"><span class="auth-logo-divider"></span><img class="auth-jamesons-logo" src="assets/jamesons-logo.svg" alt="Jamesons Strata Management"></div><h1>Forgot password</h1><p class="page-subtitle">Enter your account email to receive a reset link.</p><?php if ($message): ?><p class="notice"><?= e($message) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Email<input name="email" type="email" required autocomplete="email"></label><button class="button">Send reset link</button></form><p><a href="login.php">Return to sign in</a></p></main></body></html>
