<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';

$admin = require_permission('manage_users');
if (($admin['role_slug'] ?? '') !== 'super-admin') {
    http_response_code(403);
    exit('Super Admin access required.');
}

$pdo = db();
$disabledAtSql = app_schema_has('users', 'disabled_at') ? 'disabled_at' : 'NULL';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pdo->beginTransaction();
    $action = request_string($_POST, 'action');
    $targetId = (int) ($_POST['id'] ?? 0);

    if ($action === 'approval') {
        $status = request_string($_POST, 'status');
        if ($targetId < 1 || !in_array($status, ['approved', 'rejected'], true)) {
            http_response_code(400);
            exit('Choose a valid account action.');
        }
        $targetQuery = $pdo->prepare('SELECT approval_status,' . $disabledAtSql . ' AS disabled_at,is_active FROM users WHERE id=? FOR UPDATE');
        $targetQuery->execute([$targetId]);
        $target = $targetQuery->fetch();
        if (!$target || $target['approval_status'] !== 'pending') {
            http_response_code(409);
            exit('This registration is no longer awaiting approval.');
        }
        $approved = $status === 'approved';
        [$activeStateSql, $activeStateParams] = user_active_state_update_sql($approved, $target['disabled_at'] !== null ? (string) $target['disabled_at'] : null);
        $pdo->prepare("UPDATE users SET approval_status=?, $activeStateSql WHERE id=?")
            ->execute(array_merge([$status], $activeStateParams, [$targetId]));
        audit_admin_action((int) $admin['id'], 'user_registration_' . $status, 'user', $targetId, ['before'=>$target,'after'=>['approval_status'=>$status,'is_active'=>$approved ? 1 : 0]]);
        $pdo->commit();
        redirect('pending-activations.php?notice=' . ($approved ? 'registration-approved' : 'registration-rejected'));
    }

    if ($action === 'activate') {
        if ($targetId < 1) {
            http_response_code(400);
            exit('Choose a valid account.');
        }
        $targetQuery = $pdo->prepare('SELECT approval_status,is_active,' . $disabledAtSql . ' AS disabled_at FROM users WHERE id=? FOR UPDATE');
        $targetQuery->execute([$targetId]);
        $target = $targetQuery->fetch();
        if (!$target || $target['approval_status'] !== 'approved' || (bool) $target['is_active']) {
            http_response_code(409);
            exit('This account is not waiting for reactivation.');
        }
        [$activeStateSql, $activeStateParams] = user_active_state_update_sql(true);
        $pdo->prepare("UPDATE users SET $activeStateSql WHERE id=?")
            ->execute(array_merge($activeStateParams, [$targetId]));
        audit_admin_action((int) $admin['id'], 'user_account_reactivated', 'user', $targetId, ['before'=>$target,'after'=>['is_active'=>1,'disabled_at'=>null]]);
        $pdo->commit();
        redirect('pending-activations.php?notice=account-activated');
    }

    http_response_code(400);
    exit('Choose a valid account action.');
}

$pendingRegistrations = $pdo->query("SELECT u.id,u.full_name,u.email,r.name role_name,u.created_at FROM users u JOIN roles r ON r.id=u.role_id WHERE u.approval_status='pending' ORDER BY u.created_at,u.id")->fetchAll();
$accountDisabledAtSql = $disabledAtSql === 'NULL' ? 'NULL' : 'u.disabled_at';
$inactiveAccounts = $pdo->query("SELECT u.id,u.full_name,u.email,r.name role_name,$accountDisabledAtSql AS disabled_at FROM users u JOIN roles r ON r.id=u.role_id WHERE u.approval_status='approved' AND u.is_active=0 ORDER BY disabled_at,u.full_name")->fetchAll();
$notice = match (request_string($_GET, 'notice')) {
    'registration-approved' => 'Registration approved and account activated.',
    'registration-rejected' => 'Registration rejected.',
    'account-activated' => 'Account reactivated.',
    default => '',
};

page_start('Pending activations', $admin);
?>
<div class="pending-activations-screen">
    <?php if ($notice): ?><p class="action-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
    <section class="access-panel" aria-labelledby="registration-queue-title">
        <div class="access-section-head">
            <div><h2 id="registration-queue-title">Registrations awaiting approval</h2><p>Review new account requests before granting access.</p></div>
            <span><?= count($pendingRegistrations) ?> pending</span>
        </div>
        <div class="table-wrap">
            <table class="ticket-table">
                <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Requested role</th><th scope="col">Requested</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($pendingRegistrations as $account): ?>
                        <tr>
                            <td><?= e($account['full_name']) ?></td>
                            <td><?= e($account['email'] ?? '') ?></td>
                            <td><?= e($account['role_name']) ?></td>
                            <td><?= e(app_datetime_display($account['created_at'] ?? null)) ?></td>
                            <td class="pending-activation-actions">
                                <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="approval"><input type="hidden" name="id" value="<?= (int) $account['id'] ?>"><input type="hidden" name="status" value="approved"><button class="button" type="submit">Approve</button></form>
                                <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="approval"><input type="hidden" name="id" value="<?= (int) $account['id'] ?>"><input type="hidden" name="status" value="rejected"><button class="button button-secondary" type="submit">Reject</button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$pendingRegistrations): ?><tr><td colspan="5" class="muted">No registrations are waiting for approval.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="access-panel" aria-labelledby="inactive-accounts-title">
        <div class="access-section-head">
            <div><h2 id="inactive-accounts-title">Inactive accounts</h2><p>Reactivate approved accounts that should regain access.</p></div>
            <span><?= count($inactiveAccounts) ?> inactive</span>
        </div>
        <div class="table-wrap">
            <table class="ticket-table">
                <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Disabled</th><th scope="col">Action</th></tr></thead>
                <tbody>
                    <?php foreach ($inactiveAccounts as $account): ?>
                        <tr>
                            <td><?= e($account['full_name']) ?></td>
                            <td><?= e($account['email'] ?? '') ?></td>
                            <td><?= e($account['role_name']) ?></td>
                            <td><?= e(app_datetime_display($account['disabled_at'] ?? null)) ?></td>
                            <td><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?= (int) $account['id'] ?>"><button class="button" type="submit">Reactivate</button></form></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$inactiveAccounts): ?><tr><td colspan="5" class="muted">No approved accounts are inactive.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php page_end(); ?>
