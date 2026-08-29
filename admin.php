<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';
$user = require_permission('manage_roles');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'generate_api_token') {
        $pdo->exec('UPDATE api_tokens SET revoked_at=NOW() WHERE revoked_at IS NULL');
        $token = bin2hex(random_bytes(32));
        $pdo->prepare('INSERT INTO api_tokens(token,token_hash,created_by) VALUES(NULL,?,?)')->execute([hash('sha256', $token), $user['id']]);
        audit_admin_action((int) $user['id'], 'api_token_generated', 'api_token');
        redirect('admin.php?api_token=' . urlencode($token));
    }
    if ($action === 'permission') {
        if (($user['role_slug'] ?? '') !== 'super-admin') { http_response_code(403); exit('Super Admin access required.'); }
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $permissionId = (int) ($_POST['permission_id'] ?? 0);
        $valid = $pdo->prepare('SELECT (SELECT COUNT(*) FROM roles WHERE id = ?) + (SELECT COUNT(*) FROM permissions WHERE id = ?)');
        $valid->execute([$roleId, $permissionId]);
        if ((int) $valid->fetchColumn() !== 2) { http_response_code(400); exit('Invalid role or permission.'); }
        $pdo->prepare('INSERT INTO role_permissions(role_id,permission_id,granted) VALUES(?,?,?) ON DUPLICATE KEY UPDATE granted=VALUES(granted)')->execute([$roleId, $permissionId, isset($_POST['granted']) ? 1 : 0]);
        audit_admin_action((int) $user['id'], 'role_permission_changed', 'role', $roleId);
    }
    if ($action === 'approval' && user_can('manage_users')) {
        $approved = ($_POST['status'] ?? '') === 'approved';
        $pdo->prepare('UPDATE users SET approval_status=?,is_active=? WHERE id=?')->execute([$approved ? 'approved' : 'rejected', $approved ? 1 : 0, (int) $_POST['user_id']]);
        audit_admin_action((int) $user['id'], 'user_registration_' . ($approved ? 'approved' : 'rejected'), 'user', (int) $_POST['user_id']);
    }
    if ($action === 'user' && user_can('manage_users')) {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $target = $pdo->prepare('SELECT u.id, r.slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
        $target->execute([$targetId]);
        $targetRow = $target->fetch();
        $roleExists = $pdo->prepare('SELECT 1 FROM roles WHERE id = ?');
        $roleExists->execute([$roleId]);
        if (!$targetRow || !$roleExists->fetchColumn()) { http_response_code(400); exit('Invalid user or role.'); }
        if ($targetId === (int) $user['id'] && !isset($_POST['is_active'])) { http_response_code(400); exit('You cannot deactivate your own account.'); }
        if ($targetRow['slug'] === 'super-admin' && (!isset($_POST['is_active']) || $roleId !== (int) $user['role_id'])) {
            $remaining = $pdo->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug='super-admin' AND u.is_active=1 AND u.id <> ?");
            $remaining->execute([$targetId]);
            if ((int) $remaining->fetchColumn() < 1) { http_response_code(400); exit('At least one active Super Admin must remain.'); }
        }
        $pdo->prepare("UPDATE users SET role_id=?,is_active=?,approval_status='approved' WHERE id=?")->execute([$roleId, isset($_POST['is_active']) ? 1 : 0, $targetId]);
        audit_admin_action((int) $user['id'], 'user_access_changed', 'user', $targetId);
    }
    redirect('admin.php');
}
$roles = $pdo->query('SELECT * FROM roles ORDER BY id')->fetchAll();
$permissions = $pdo->query('SELECT * FROM permissions ORDER BY id')->fetchAll();
$grants = [];
foreach ($pdo->query('SELECT * FROM role_permissions') as $grant) $grants[$grant['role_id']][$grant['permission_id']] = $grant['granted'];
$users = $pdo->query('SELECT u.*,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id ORDER BY u.full_name')->fetchAll();
$pendingUsers = $pdo->query("SELECT u.id,u.full_name,u.username,u.email,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.approval_status='pending' ORDER BY u.created_at")->fetchAll();
$activeApi = $pdo->query('SELECT id FROM api_tokens WHERE revoked_at IS NULL ORDER BY id DESC LIMIT 1')->fetch();
$activeUserCount=count(array_filter($users,static fn(array $member):bool=>(bool)$member['is_active']));
$canManageUsers=user_can('manage_users');
page_start('Roles & Access',$user); ?>
<div class="roles-access-screen">
    <section class="access-panel access-utility-panel" aria-labelledby="api-access-title"><div class="access-section-head"><div><h2 id="api-access-title">Webhook / API access</h2><p>The active URL is rotated when a new one is generated.</p></div><?php if ($activeApi): ?><span class="access-role-badge">Configured</span><?php endif; ?></div><?php if (isset($_GET['api_token'])): ?><p class="action-notice">New URL: <code><?= e('https://cng-tickets.stratastaff.com/api/feed.php?token=' . $_GET['api_token']) ?></code></p><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="generate_api_token"><button class="button button-secondary">Generate new URL</button></form></section>
    <?php if ($pendingUsers): ?><section class="access-panel access-utility-panel" aria-labelledby="pending-users-title"><div class="access-section-head"><div><h2 id="pending-users-title">Pending registrations</h2><p>Review accounts awaiting approval.</p></div><span><?= count($pendingUsers) ?> pending</span></div><div class="access-pending-list"><?php foreach ($pendingUsers as $pending): ?><div><span><strong><?= e($pending['full_name']) ?></strong><small><?= e($pending['email']) ?> · <?= e($pending['role_name']) ?></small></span><span><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="approval"><input type="hidden" name="user_id" value="<?= (int) $pending['id'] ?>"><input type="hidden" name="status" value="approved"><button class="button">Approve</button></form><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="approval"><input type="hidden" name="user_id" value="<?= (int) $pending['id'] ?>"><input type="hidden" name="status" value="rejected"><button class="button button-secondary">Reject</button></form></span></div><?php endforeach; ?></div></section><?php endif; ?>
    <section class="access-summary-panel" aria-label="Access management summary">
        <div class="access-summary-grid">
            <article class="access-summary-card"><span>Roles</span><strong><?=count($roles)?></strong><small>Configured access levels</small></article>
            <article class="access-summary-card"><span>Permissions</span><strong><?=count($permissions)?></strong><small>Available capabilities</small></article>
            <article class="access-summary-card"><span>Users</span><strong><?=count($users)?></strong><small>Registered accounts</small></article>
            <article class="access-summary-card"><span>Active users</span><strong><?=$activeUserCount?></strong><small>Currently enabled</small></article>
        </div>
    </section>
    <section class="access-panel" aria-labelledby="role-permissions-title">
        <div class="access-section-head"><div><h2 id="role-permissions-title">Role permissions</h2><p>Permission changes apply immediately.</p></div><span><?=count($permissions)?> permissions</span></div>
        <div class="table-wrap access-permission-wrap"><table class="ticket-table access-permission-table"><thead><tr><th>Permission</th><?php foreach($roles as $role):?><th><?=e($role['name'])?></th><?php endforeach;?></tr></thead><tbody>
        <?php foreach($permissions as $permission):?><tr><td class="access-permission-copy"><strong><?=e($permission['label'])?></strong><span><?=e($permission['description'])?></span></td><?php foreach($roles as $role):$isGranted=!empty($grants[$role['id']][$permission['id']]);?><td><form method="post" class="permission-toggle-form"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="permission"><input type="hidden" name="role_id" value="<?=(int)$role['id']?>"><input type="hidden" name="permission_id" value="<?=(int)$permission['id']?>"><?php if(!$isGranted):?><input type="hidden" name="granted" value="1"><?php endif;?><button class="access-toggle<?=$isGranted?' is-on':''?>" aria-pressed="<?=$isGranted?'true':'false'?>" aria-label="<?=e(($isGranted?'Disable ':'Enable ').$permission['label'].' for '.$role['name'])?>"><span class="access-toggle-track" aria-hidden="true"><i></i></span><span class="access-toggle-label"><?=$isGranted?'On':'Off'?></span></button></form></td><?php endforeach;?></tr><?php endforeach;?>
        <?php if(!$permissions):?><tr><td colspan="<?=count($roles)+1?>" class="muted access-empty-row">No permissions are configured.</td></tr><?php endif;?>
        </tbody></table></div>
    </section>
    <section class="access-panel" aria-labelledby="access-users-title">
        <div class="access-section-head"><div><h2 id="access-users-title">Users and access</h2><p>Assign roles, enable accounts, or reset credentials.</p></div><span><?=count($users)?> users</span></div>
        <div class="table-wrap access-users-wrap"><table class="ticket-table access-users-table"><thead><tr><th>User</th><th>Current access</th><th>Manage access</th><th>Security</th></tr></thead><tbody>
        <?php foreach($users as $member):?><tr><td class="access-user-cell"><span class="access-user-avatar" aria-hidden="true"><?=e(strtoupper(substr((string)$member['full_name'],0,1)))?></span><span><strong><?=e($member['full_name'])?></strong><small>@<?=e($member['username'])?></small></span></td><td><span class="access-role-badge"><?=e($member['role_name'])?></span><span class="access-account-state<?=$member['is_active']?' is-active':' is-inactive'?>"><i></i><?=$member['is_active']?'Active':'Inactive'?></span></td><td><form method="post" class="user-access-form" data-submit-on-change="false"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="user"><input type="hidden" name="user_id" value="<?=(int)$member['id']?>"><div class="filter-picker access-role-picker" data-filter-picker><input type="hidden" name="role_id" value="<?=(int)$member['role_id']?>"><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"<?=$canManageUsers?'':' disabled'?>> <span class="filter-picker-copy"><strong data-filter-label><?=e($member['role_name'])?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Role</p><?php foreach($roles as $role):?><button type="button" class="filter-option<?=$member['role_id']==$role['id']?' is-selected':''?>" data-filter-target="role_id" data-filter-value="<?=(int)$role['id']?>" data-filter-label="<?=e($role['name'])?>"><span><?=e($role['name'])?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach;?></div></div><input type="hidden" value="1" data-access-active-input<?=$member['is_active']?' name="is_active"':''?>><button type="button" class="access-switch<?=$member['is_active']?' is-on':''?>" data-access-switch aria-pressed="<?=$member['is_active']?'true':'false'?>"<?=$canManageUsers?'':' disabled'?>> <span class="access-toggle-track" aria-hidden="true"><i></i></span><span data-access-switch-label><?=$member['is_active']?'Active':'Inactive'?></span></button><button class="button access-save-button"<?=$canManageUsers?'':' disabled'?>>Save</button></form></td><td><?php if($canManageUsers):?><a class="access-reset-link" href="reset-user-password.php?id=<?=(int)$member['id']?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 11a8 8 0 1 1 2.3 5.7M4 16v-5h5"/><path d="M12 8v4l3 2"/></svg>Reset password</a><?php else:?><span class="muted">Restricted</span><?php endif;?></td></tr><?php endforeach;?>
        <?php if(!$users):?><tr><td colspan="4" class="muted access-empty-row">No users are available.</td></tr><?php endif;?>
        </tbody></table></div>
    </section>
</div>
<?php page_end();
