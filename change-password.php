<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_login(); $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $password = request_string($_POST, 'password'); $confirmation = request_string($_POST, 'password_confirmation');
    if (!password_meets_policy($password)) $error = 'Use a password between 12 and 72 bytes.';
    elseif ($password !== $confirmation) $error = 'The passwords do not match.';
    else {
        $pdo = db();
        $newPasswordHash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET password_hash=?,must_change_password=0 WHERE id=?')->execute([$newPasswordHash, $user['id']]);
            audit_admin_action((int)$user['id'], 'password_changed', 'user', (int)$user['id']);
            $pdo->commit();
            session_regenerate_id(true);
            $_SESSION['auth_password_hash'] = $newPasswordHash;
            refresh_current_user(); redirect(default_landing_page());
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'The password could not be saved.';
        }
    }
}
require __DIR__ . '/app/layout.php';
page_start('Settings', $user);
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Account</p>
        <h1>Settings</h1>
        <p class="page-subtitle">Manage the appearance of this browser and your account password.</p>
    </div>
</div>
<div class="settings-screen">
    <section class="settings-panel">
        <div class="settings-panel-head">
            <div><p class="eyebrow">Appearance</p><h2>Theme</h2><p>Choose the theme you want to use on this browser.</p></div>
        </div>
        <div class="theme-choice-group" role="group" aria-label="Theme">
            <button class="theme-choice" type="button" data-theme-choice="light" aria-pressed="false"><span class="theme-choice-swatch theme-choice-swatch-light" aria-hidden="true"></span><span>Light</span></button>
            <button class="theme-choice" type="button" data-theme-choice="dark" aria-pressed="false"><span class="theme-choice-swatch theme-choice-swatch-dark" aria-hidden="true"></span><span>Dark</span></button>
        </div>
        <p class="field-help" data-theme-status>Light theme is active.</p>
    </section>
    <section class="settings-panel">
        <div class="settings-panel-head">
            <div><p class="eyebrow">Account security</p><h2>Change password</h2><p>Use at least 12 characters. Your new password will be active immediately.</p></div>
        </div>
        <?php if($error): ?><p class="auth-error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <form method="post" class="settings-password-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>New password<input name="password" type="password" minlength="12" maxlength="72" required autocomplete="new-password" placeholder="12 to 72 bytes"></label>
            <label>Confirm password<input name="password_confirmation" type="password" minlength="12" maxlength="72" required autocomplete="new-password" placeholder="Re-enter your password"></label>
            <div class="record-form-actions"><button class="button" data-confirm="Save this password?">Save password</button></div>
        </form>
    </section>
</div>
<?php page_end();
