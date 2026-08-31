<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';

$admin = require_permission('manage_users');
$pdo = db();
$error = '';
$notice = '';
$teamMemberEmailValues = [];
if (isset($_POST['team_member_email']) && is_array($_POST['team_member_email'])) {
    foreach ($_POST['team_member_email'] as $staffId => $email) $teamMemberEmailValues[(int) $staffId] = strtolower(trim((string) $email));
}
$credentialBatch = is_array($_SESSION['team_member_credentials'] ?? null) ? $_SESSION['team_member_credentials'] : [];
unset($_SESSION['team_member_credentials']);

function staff_directory_key(string $name): string
{
    return strtolower(trim((string) preg_replace('/\s+/', ' ', $name)));
}

function team_member_username(string $email, string $fullName, array &$usedUsernames): string
{
    $localPart = strstr($email, '@', true);
    $base = strtolower((string) ($localPart ?: $fullName));
    $base = (string) preg_replace('/[^a-z0-9._-]+/i', '.', $base);
    $base = trim($base, '._-');
    if (!preg_match('/^[a-z0-9._-]{3,80}$/', $base)) {
        $base = strtolower((string) preg_replace('/[^a-z0-9]+/i', '.', $fullName));
        $base = trim($base, '.');
    }
    $base = substr($base ?: 'team.member', 0, 80);
    $candidate = $base;
    $suffix = 2;
    while (isset($usedUsernames[$candidate])) {
        $suffixText = '.' . $suffix++;
        $candidate = substr($base, 0, 80 - strlen($suffixText)) . $suffixText;
    }
    $usedUsernames[$candidate] = true;
    return $candidate;
}

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
            $roleQuery = $pdo->prepare('SELECT slug FROM roles WHERE id = ?');
            $roleQuery->execute([$roleId]);
            $roleSlug = (string) ($roleQuery->fetchColumn() ?: '');
            if ($roleSlug === '') throw new InvalidArgumentException('Select a valid role.');
            if ($roleSlug === 'super-admin' && ($admin['role_slug'] ?? '') !== 'super-admin') throw new InvalidArgumentException('Only a Super Admin can assign the Super Admin role.');
            if ($departmentId !== null) {
                $departmentQuery = $pdo->prepare('SELECT 1 FROM departments WHERE id = ?');
                $departmentQuery->execute([$departmentId]);
                if (!$departmentQuery->fetchColumn()) throw new InvalidArgumentException('Select a valid department.');
            }
            if ($id > 0) {
                $active = isset($_POST['is_active']) ? 1 : 0;
                if ($id === (int) $admin['id'] && !$active) throw new InvalidArgumentException('You cannot deactivate your own account.');
                $targetQuery = $pdo->prepare('SELECT u.id,r.slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?');
                $targetQuery->execute([$id]);
                $target = $targetQuery->fetch();
                if (!$target) throw new InvalidArgumentException('User not found.');
                if ($target['slug'] === 'super-admin' && (!$active || $roleSlug !== 'super-admin')) {
                    $remaining = $pdo->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='super-admin' AND u.is_active=1 AND u.id<>?");
                    $remaining->execute([$id]);
                    if ((int) $remaining->fetchColumn() < 1) throw new InvalidArgumentException('At least one active Super Admin must remain.');
                }
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
            $targetQuery = $pdo->prepare('SELECT u.id,r.slug,u.is_active FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?');
            $targetQuery->execute([$id]);
            $target = $targetQuery->fetch();
            if (!$target) throw new InvalidArgumentException('User not found.');
            if ($target['slug'] === 'super-admin') {
                $remaining = $pdo->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='super-admin' AND u.is_active=1 AND u.id<>?");
                $remaining->execute([$id]);
                if ((int) $remaining->fetchColumn() < 1) throw new InvalidArgumentException('At least one active Super Admin must remain.');
            }
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
            $notice = 'User deleted.';
        } elseif ($action === 'overrides') {
            $id = (int) ($_POST['id'] ?? 0);
            $targetQuery = $pdo->prepare('SELECT 1 FROM users WHERE id=?');
            $targetQuery->execute([$id]);
            if (!$targetQuery->fetchColumn()) throw new InvalidArgumentException('User not found.');
            $pdo->prepare('DELETE FROM user_permission_overrides WHERE user_id=?')->execute([$id]);
            $insert = $pdo->prepare('INSERT INTO user_permission_overrides(user_id,permission_id,granted) VALUES(?,?,?)');
            $validPermissions = array_map('intval', $pdo->query('SELECT id FROM permissions')->fetchAll(PDO::FETCH_COLUMN));
            foreach ($_POST['permission'] ?? [] as $permissionId => $granted) if (in_array((int) $permissionId, $validPermissions, true)) $insert->execute([$id, (int) $permissionId, (int) $granted === 1 ? 1 : 0]);
            $notice = 'User permission overrides updated.';
        } elseif ($action === 'create_team_members') {
            $candidateEmails = array_filter($teamMemberEmailValues, static fn(string $email): bool => $email !== '');
            if (!$candidateEmails) throw new InvalidArgumentException('Enter at least one exact employee email before creating accounts.');
            $staffIds = array_keys($candidateEmails);
            $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
            $staffQuery = $pdo->prepare("SELECT id,full_name,team_label,email_domain FROM staff_directory WHERE is_active=1 AND team_label<> 'TL' AND id IN ($placeholders) ORDER BY id");
            $staffQuery->execute($staffIds);
            $staffRows = $staffQuery->fetchAll();
            if (count($staffRows) !== count($staffIds)) throw new InvalidArgumentException('One or more selected staff rows are no longer available. Refresh the page and try again.');
            $roleId = (int) $pdo->query("SELECT id FROM roles WHERE slug='team-member'")->fetchColumn();
            if ($roleId < 1) throw new RuntimeException('The Team Member role is not available. Apply the role migration first.');
            $existingUsers = $pdo->query('SELECT full_name,email,username FROM users')->fetchAll();
            $existingNames = [];
            $existingEmails = [];
            $usedUsernames = [];
            foreach ($existingUsers as $existingUser) {
                $existingNames[staff_directory_key((string) $existingUser['full_name'])] = $existingUser;
                if (!empty($existingUser['email'])) $existingEmails[strtolower((string) $existingUser['email'])] = $existingUser;
                $usedUsernames[strtolower((string) $existingUser['username'])] = true;
            }
            $batchEmails = [];
            $credentials = [];
            foreach ($staffRows as $staff) {
                $staffId = (int) $staff['id'];
                $email = $candidateEmails[$staffId] ?? '';
                $domain = strtolower(ltrim(trim((string) ($staff['email_domain'] ?? '')), '@'));
                $expectedSuffix = '@' . $domain;
                if ($domain === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || substr($email, -strlen($expectedSuffix)) !== $expectedSuffix) {
                    throw new InvalidArgumentException('Enter a valid ' . $expectedSuffix . ' email for ' . $staff['full_name'] . '.');
                }
                if (isset($batchEmails[$email])) throw new InvalidArgumentException('The email ' . $email . ' is entered more than once.');
                if (isset($existingEmails[$email])) throw new InvalidArgumentException('The email ' . $email . ' already belongs to an account.');
                $nameKey = staff_directory_key((string) $staff['full_name']);
                if (isset($existingNames[$nameKey])) throw new InvalidArgumentException($staff['full_name'] . ' already has an account. Open the existing account instead of creating a duplicate.');
                $batchEmails[$email] = true;
                $credentials[] = [
                    'full_name' => $staff['full_name'],
                    'email' => $email,
                    'username' => team_member_username($email, (string) $staff['full_name'], $usedUsernames),
                    'password' => strtoupper(bin2hex(random_bytes(10))),
                ];
            }
            $pdo->beginTransaction();
            try {
                $insert = $pdo->prepare("INSERT INTO users(role_id,username,full_name,email,password_hash,must_change_password,is_active,approval_status) VALUES(?,?,?,?,?,?,1,'approved')");
                foreach ($credentials as $credential) $insert->execute([$roleId, $credential['username'], $credential['full_name'], $credential['email'], password_hash($credential['password'], PASSWORD_DEFAULT), 1]);
                audit_admin_action((int) $admin['id'], 'team_member_accounts_created', 'users', null, ['created_count' => count($credentials), 'staff_ids' => $staffIds]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }
            $_SESSION['team_member_credentials'] = $credentials;
            redirect('users.php?credentials=1');
        }
    } catch (Throwable $exception) {
        $error = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'The user change could not be saved. Check for duplicate username or email.';
    }
}

$roles = $pdo->query('SELECT id,name FROM roles ORDER BY name')->fetchAll();
$departments = $pdo->query('SELECT id,name FROM departments ORDER BY name')->fetchAll();
$permissions = $pdo->query('SELECT id,label,permission_key FROM permissions ORDER BY label')->fetchAll();
$users = $pdo->query('SELECT u.*,r.name role_name,d.name department_name FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN departments d ON d.id=u.department_id ORDER BY u.full_name')->fetchAll();
$teamMemberDirectory = [];
$directoryError = '';
try {
    $teamMemberDirectory = $pdo->query("SELECT id,full_name,team_label,email_domain FROM staff_directory WHERE is_active=1 AND team_label <> 'TL' ORDER BY id")->fetchAll();
    $accountByName = [];
    foreach ($users as $member) $accountByName[staff_directory_key((string) $member['full_name'])] = $member;
    foreach ($teamMemberDirectory as &$staff) $staff['account'] = $accountByName[staff_directory_key((string) $staff['full_name'])] ?? null;
    unset($staff);
} catch (Throwable) {
    $directoryError = 'The staff directory is unavailable. Apply migrations/022_staff_leave_attendance_updates.sql before setting up Team Member accounts.';
}
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
<?php if ($credentialBatch): ?><section class="team-member-credentials" aria-labelledby="team-member-credentials-title"><div class="section-head"><div><h2 id="team-member-credentials-title">Temporary Team Member credentials</h2><p>Save or send these now. They are shown only once, and every account must change its password at first sign-in.</p></div><span><?= count($credentialBatch) ?> account<?= count($credentialBatch) === 1 ? '' : 's' ?></span></div><div class="table-wrap"><table class="ticket-table"><thead><tr><th>Name</th><th>Email</th><th>Username</th><th>Temporary password</th></tr></thead><tbody><?php foreach ($credentialBatch as $credential): ?><tr><td><?= e($credential['full_name']) ?></td><td><?= e($credential['email']) ?></td><td><?= e($credential['username']) ?></td><td><code><?= e($credential['password']) ?></code></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
<section><h2><?= $editing ? 'Edit user' : 'Create user' ?></h2><form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>"><label>Full name<input name="full_name" required value="<?= e($editing['full_name'] ?? '') ?>"></label><label>Username<input name="username" required pattern="[A-Za-z0-9._-]{3,80}" value="<?= e($editing['username'] ?? '') ?>"></label><label>Email<input name="email" type="email" required value="<?= e($editing['email'] ?? '') ?>"></label><label>Role<select name="role_id" required><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>" <?= (int) ($editing['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>><?= e($role['name']) ?></option><?php endforeach; ?></select></label><label>Department<select name="department_id"><option value="">None</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>" <?= (int) ($editing['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></label><?php if ($editing): ?><label class="checkbox-label"><input type="checkbox" name="is_active" <?= $editing['is_active'] ? 'checked' : '' ?>> Active account</label><?php endif; ?><div><button class="button"><?= $editing ? 'Save changes' : 'Create user' ?></button></div></form></section>
<?php if ($directoryError): ?><section><h2>Team Member directory</h2><p class="error"><?= e($directoryError) ?></p></section><?php elseif ($teamMemberDirectory): ?><section class="team-member-setup"><div class="section-head"><div><h2>Team Member account setup</h2><p>These are the 70 employee rows from the staff list. Enter the real company email for each person you want to add; blank rows are skipped. The account username is derived from the email, and the temporary password is displayed once after creation.</p></div><span><?= count($teamMemberDirectory) ?> employees</span></div><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create_team_members"><div class="table-wrap"><table class="ticket-table team-member-setup-table"><thead><tr><th>Name</th><th>Staff team</th><th>Exact company email</th><th>Account</th></tr></thead><tbody><?php foreach ($teamMemberDirectory as $staff): $account = $staff['account'] ?? null; ?><tr><td><?= e($staff['full_name']) ?></td><td><?= e($staff['team_label']) ?></td><td><?php if ($account): ?><?= e($account['email'] ?? 'No email recorded') ?><?php else: ?><input type="email" name="team_member_email[<?= (int) $staff['id'] ?>]" value="<?= e($teamMemberEmailValues[(int) $staff['id']] ?? '') ?>" placeholder="employee@<?= e($staff['email_domain']) ?>" autocomplete="email"><small>Allowed: @<?= e($staff['email_domain']) ?></small><?php endif; ?></td><td><?php if ($account): ?><a href="users.php?edit=<?= (int) $account['id'] ?>">Existing: <?= e($account['username']) ?></a><?php else: ?><span class="muted">Ready to add</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><button class="button" type="submit">Create filled Team Member accounts</button></form></section><?php endif; ?>
<section><h2>Accounts</h2><div class="table-wrap"><table class="ticket-table"><thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($users as $member): ?><tr><td><?= e($member['full_name']) ?></td><td><?= e($member['username']) ?></td><td><?= e($member['email'] ?? '') ?></td><td><?= e($member['role_name']) ?></td><td><?= e($member['department_name'] ?? '—') ?></td><td><?= $member['is_active'] ? 'Active' : 'Inactive' ?></td><td><a href="users.php?edit=<?= (int) $member['id'] ?>">Edit</a> · <a href="reset-user-password.php?id=<?= (int) $member['id'] ?>">Reset password</a><form method="post" style="display:inline" onsubmit="return confirm('Delete this user account?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $member['id'] ?>"><button class="link-button" type="submit">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php if ($editing): ?><section><h2>Permission overrides for <?= e($editing['full_name']) ?></h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="overrides"><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><div class="permission-grid"><?php foreach ($permissions as $permission): ?><label><input type="checkbox" name="permission[<?= (int) $permission['id'] ?>]" value="1" <?= ($overrideMap[(int) $permission['id']] ?? 0) === 1 ? 'checked' : '' ?>> <?= e($permission['label']) ?></label><?php endforeach; ?></div><button class="button">Save permission overrides</button></form></section><?php endif; ?>
<?php page_end();
