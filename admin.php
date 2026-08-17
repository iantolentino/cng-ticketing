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
page_start('Roles & Access', $user);
?>
<div class="page-head"><div><p class="eyebrow">Super Admin</p><h1>Roles &amp; Access Management</h1></div></div>
<section><h2>Webhook / API Access</h2><p class="muted">The URL is the only credential. Generating a new URL revokes the previous one immediately. A new URL is shown only once.</p><?php if(isset($_GET['api_token'])):?><p class="action-notice">New URL: <code><?=e('https://cng-tickets.stratastaff.com/api/feed.php?token=' . $_GET['api_token'])?></code></p><?php elseif($activeApi):?><p class="muted">An active API URL is configured. Generate a new URL to replace it.</p><?php else:?><p class="muted">No active URL.</p><?php endif;?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="generate_api_token"><button class="button">Generate New URL</button></form></section>
<?php if ($pendingUsers): ?><section><h2>Pending registrations</h2><div class="table-wrap"><table class="ticket-table"><thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Requested role</th><th>Action</th></tr></thead><tbody><?php foreach ($pendingUsers as $pending): ?><tr><td><?=e($pending['full_name'])?></td><td><?=e($pending['username'])?></td><td><?=e($pending['email'])?></td><td><?=e($pending['role_name'])?></td><td><form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="approval"><input type="hidden" name="user_id" value="<?=$pending['id']?>"><input type="hidden" name="status" value="approved"><button class="button">Approve</button></form> <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="approval"><input type="hidden" name="user_id" value="<?=$pending['id']?>"><input type="hidden" name="status" value="rejected"><button class="button">Reject</button></form></td></tr><?php endforeach;?></tbody></table></div></section><?php endif; ?>
<section><h2>Role permissions</h2><div class="table-wrap"><table class="ticket-table"><thead><tr><th>Permission</th><?php foreach ($roles as $role): ?><th><?=e($role['name'])?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($permissions as $permission): ?><tr><td><strong><?=e($permission['label'])?></strong><br><span class="muted"><?=e($permission['description'])?></span></td><?php foreach ($roles as $role): ?><td><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="permission"><input type="hidden" name="role_id" value="<?=$role['id']?>"><input type="hidden" name="permission_id" value="<?=$permission['id']?>"><input type="checkbox" name="granted" onchange="this.form.submit()" <?=$grants[$role['id']][$permission['id']]??false?'checked':''?> aria-label="<?=e($role['name'].' '.$permission['label'])?>"></form></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div></section>
<section><h2>Users</h2><div class="table-wrap"><table class="ticket-table"><thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Active</th><th>Save</th></tr></thead><tbody><?php foreach ($users as $member): ?><tr><td><?=e($member['full_name'])?></td><td><?=e($member['username'])?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="user"><input type="hidden" name="user_id" value="<?=$member['id']?>"><select name="role_id"><?php foreach ($roles as $role): ?><option value="<?=$role['id']?>" <?=$member['role_id']==$role['id']?'selected':''?>><?=e($role['name'])?></option><?php endforeach; ?></select></td><td><input type="checkbox" name="is_active" <?=$member['is_active']?'checked':''?>></td><td><button class="button">Save</button></form></td></tr><?php endforeach; ?></tbody></table></div></section><?php page_end();
