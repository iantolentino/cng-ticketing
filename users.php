<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';

$admin = require_permission('manage_users');
$pdo = db();
$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $username = strtolower(trim((string) ($_POST['username'] ?? '')));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $roleId = (int) ($_POST['role_id'] ?? 0);
            $departmentId = (int) ($_POST['department_id'] ?? 0) ?: null;
            if ($fullName === '' || !preg_match('/^[a-z0-9._-]{3,80}$/', $username) || !filter_var($email, FILTER_VALIDATE_EMAIL) || $roleId < 1) throw new InvalidArgumentException('Enter a name, valid username, email, and role.');
            if ($id > 0) {
                $active = isset($_POST['is_active']) ? 1 : 0;
                $pdo->prepare("UPDATE users SET full_name=?, username=?, email=?, role_id=?, department_id=?, is_active=?, approval_status=CASE WHEN ?=1 THEN 'approved' ELSE approval_status END WHERE id=?")->execute([$fullName, $username, $email, $roleId, $departmentId, $active, $active, $id]);
                $notice = 'User updated.';
            } else {
                $temporary = strtoupper(bin2hex(random_bytes(5)));
                $pdo->prepare('INSERT INTO users(full_name, username, email, role_id, department_id, password_hash, must_change_password) VALUES(?,?,?,?,?,?,1)')->execute([$fullName, $username, $email, $roleId, $departmentId, password_hash($temporary, PASSWORD_DEFAULT)]);
                $notice = 'User created. Temporary password: ' . $temporary;
            }
        } elseif ($action === 'activate') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE users SET is_active=1, approval_status='approved' WHERE id=?")->execute([$id]);
            $notice = 'Account activated.';
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) $admin['id']) throw new InvalidArgumentException('You cannot delete your own account.');
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
            $notice = 'User deleted.';
        } elseif ($action === 'overrides') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM user_permission_overrides WHERE user_id=?')->execute([$id]);
            $insert = $pdo->prepare('INSERT INTO user_permission_overrides(user_id,permission_id,granted) VALUES(?,?,?)');
            foreach ($_POST['permission'] ?? [] as $permissionId => $granted) $insert->execute([$id, (int) $permissionId, (int) $granted === 1 ? 1 : 0]);
            $notice = 'User permission overrides updated.';
        }
    } catch (Throwable $exception) {
        $error = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'The user change could not be saved. Check for duplicate username or email.';
    }
}

$roles = $pdo->query('SELECT id,name FROM roles ORDER BY name')->fetchAll();
$departments = $pdo->query('SELECT id,name FROM departments ORDER BY name')->fetchAll();
$permissions = $pdo->query('SELECT id,label,permission_key FROM permissions ORDER BY label')->fetchAll();
$users = $pdo->query('SELECT u.*,r.name role_name,d.name department_name FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN departments d ON d.id=u.department_id ORDER BY u.full_name')->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);
$editing = $editId ? array_values(array_filter($users, static fn(array $member): bool => (int) $member['id'] === $editId))[0] ?? null : null;
$overrideMap = [];
if ($editing) {
    $q = $pdo->prepare('SELECT permission_id,granted FROM user_permission_overrides WHERE user_id=?');
    $q->execute([$editId]);
    foreach ($q as $override) $overrideMap[(int) $override['permission_id']] = (int) $override['granted'];
}
page_start('Users', $admin); ?>
<?php $pendingAccounts = array_filter($users, static fn(array $member): bool => !$member['is_active']); if ($pendingAccounts): ?><section><h2>Pending activation</h2><p class="muted">Review registration requests before activating access.</p><div class="table-wrap"><table class="ticket-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Action</th></tr></thead><tbody><?php foreach ($pendingAccounts as $member): ?><tr><td><?= e($member['full_name']) ?></td><td><?= e($member['email'] ?? '') ?></td><td><?= e($member['role_name']) ?></td><td><form method="post" onsubmit="return confirm('Activate this account?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?= (int) $member['id'] ?>"><button class="button" type="submit">Activate</button></form></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
<div class="page-head"><div><p class="eyebrow">Super Admin</p><h1>Users</h1><p class="page-subtitle">Create accounts, assign roles, and manage individual permission overrides.</p></div><a class="button" href="users.php">New user</a></div>
<?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?><?php if ($notice): ?><p class="notice"><?= e($notice) ?></p><?php endif; ?>
<section><h2><?= $editing ? 'Edit user' : 'Create user' ?></h2><form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>"><label>Full name<input name="full_name" required value="<?= e($editing['full_name'] ?? '') ?>"></label><label>Username<input name="username" required pattern="[A-Za-z0-9._-]{3,80}" value="<?= e($editing['username'] ?? '') ?>"></label><label>Email<input name="email" type="email" required value="<?= e($editing['email'] ?? '') ?>"></label><label>Role<select name="role_id" required><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>" <?= (int) ($editing['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>><?= e($role['name']) ?></option><?php endforeach; ?></select></label><label>Department<select name="department_id"><option value="">None</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>" <?= (int) ($editing['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></label><?php if ($editing): ?><label class="checkbox-label"><input type="checkbox" name="is_active" <?= $editing['is_active'] ? 'checked' : '' ?>> Active account</label><?php endif; ?><div><button class="button"><?= $editing ? 'Save changes' : 'Create user' ?></button></div></form></section>
<section><h2>Accounts</h2><div class="table-wrap"><table class="ticket-table"><thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($users as $member): ?><tr><td><?= e($member['full_name']) ?></td><td><?= e($member['username']) ?></td><td><?= e($member['email'] ?? '') ?></td><td><?= e($member['role_name']) ?></td><td><?= e($member['department_name'] ?? '—') ?></td><td><?= $member['is_active'] ? 'Active' : 'Inactive' ?></td><td><a href="users.php?edit=<?= (int) $member['id'] ?>">Edit</a> · <a href="reset-user-password.php?id=<?= (int) $member['id'] ?>">Reset password</a><form method="post" style="display:inline" onsubmit="return confirm('Delete this user account?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $member['id'] ?>"><button class="link-button" type="submit">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php if ($editing): ?><section><h2>Permission overrides for <?= e($editing['full_name']) ?></h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="overrides"><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><div class="permission-grid"><?php foreach ($permissions as $permission): ?><label><input type="checkbox" name="permission[<?= (int) $permission['id'] ?>]" value="1" <?= ($overrideMap[(int) $permission['id']] ?? 0) === 1 ? 'checked' : '' ?>> <?= e($permission['label']) ?></label><?php endforeach; ?></div><button class="button">Save permission overrides</button></form></section><?php endif; ?>
<?php page_end();
