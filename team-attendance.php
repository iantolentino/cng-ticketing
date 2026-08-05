<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('view_all_tickets');
if (($user['role_slug'] ?? '') === 'cng-admin') { http_response_code(403); exit('You do not have permission to access this action.'); }
$pdo = db();
$notice = '';
$error = '';
$statuses = [
    'present' => 'Present',
    'partial' => 'Partial coverage',
    'absent' => 'Absent',
    'training' => 'Training',
    'work_from_home' => 'Work from home',
];
$canLog = ($user['role_slug'] ?? '') === 'team-leader' || user_can('manage_roles');
$selectedDate = trim((string) ($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) $selectedDate = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$canLog) { http_response_code(403); exit('You do not have permission to log attendance.'); }
    $attendanceDate = trim((string) ($_POST['attendance_date'] ?? ''));
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $status = trim((string) ($_POST['status'] ?? 'present'));
    $headcount = max(0, (int) ($_POST['headcount'] ?? 0));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $departmentCheck = $pdo->prepare('SELECT id FROM departments WHERE id = ?');
    $departmentCheck->execute([$departmentId]);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendanceDate) || !$departmentCheck->fetchColumn() || !isset($statuses[$status])) {
        $error = 'Choose a valid date, department, and attendance status.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO team_attendance(attendance_date,department_id,logged_by,status,headcount,notes) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE logged_by=VALUES(logged_by), status=VALUES(status), headcount=VALUES(headcount), notes=VALUES(notes)');
        $stmt->execute([$attendanceDate, $departmentId, $user['id'], $status, $headcount, $notes ?: null]);
        redirect('team-attendance.php?date=' . $attendanceDate . '&notice=saved');
    }
}

$notice = ($_GET['notice'] ?? '') === 'saved' ? 'Attendance logged.' : '';
$departments = $pdo->query('SELECT id,name FROM departments ORDER BY name')->fetchAll();
$summaryStmt = $pdo->prepare('SELECT ta.*, d.name AS department, u.full_name AS logger FROM team_attendance ta JOIN departments d ON d.id = ta.department_id JOIN users u ON u.id = ta.logged_by WHERE ta.attendance_date = ? ORDER BY d.name');
$summaryStmt->execute([$selectedDate]);
$dayRows = $summaryStmt->fetchAll();
$recent = $pdo->query('SELECT ta.*, d.name AS department, u.full_name AS logger FROM team_attendance ta JOIN departments d ON d.id = ta.department_id JOIN users u ON u.id = ta.logged_by ORDER BY ta.attendance_date DESC, d.name LIMIT 20')->fetchAll();

page_start('Team Attendance', $user);
?>
<?php if ($notice): ?><p class="action-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="auth-error"><?= e($error) ?></p><?php endif; ?>
<div class="page-head"><div><p class="eyebrow">Planning</p><h1>Team Attendance</h1><p class="page-subtitle">Log department coverage so the Team Calendar can show attendance context beside holidays, events, and approved leave.</p></div><form method="get" class="month-form"><label>Date<input type="date" name="date" value="<?= e($selectedDate) ?>" onchange="this.form.submit()"></label></form></div>
<?php if ($canLog): ?><section><h2>Log department coverage</h2><form method="post" class="ticket-form compact-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Date<input type="date" name="attendance_date" required value="<?= e($selectedDate) ?>"></label><label>Department<select name="department_id" required><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"><?= e($department['name']) ?></option><?php endforeach; ?></select></label><label>Status<select name="status"><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label><label>Headcount<input type="number" name="headcount" min="0" value="0"></label><label>Notes<textarea name="notes" rows="3" placeholder="Optional coverage notes"></textarea></label><button class="button">Save attendance</button></form></section><?php endif; ?>
<section><div class="section-head"><div><h2><?= e(date('M j, Y', strtotime($selectedDate))) ?> coverage</h2><p class="muted">These records appear on Team Calendar for the same date.</p></div></div><div class="table-wrap"><table class="ticket-table compact-table"><thead><tr><th>Department</th><th>Status</th><th>Headcount</th><th>Notes</th><th>Logged by</th></tr></thead><tbody><?php foreach ($dayRows as $row): ?><tr><td><?= e($row['department']) ?></td><td><span class="pill pill-<?= e(str_replace('_', '-', $row['status'])) ?>"><?= e($statuses[$row['status']] ?? $row['status']) ?></span></td><td><?= (int) $row['headcount'] ?></td><td><?= e($row['notes'] ?: '-') ?></td><td class="muted"><?= e($row['logger']) ?></td></tr><?php endforeach; ?><?php if (!$dayRows): ?><tr><td colspan="5" class="muted">No attendance logged for this date.</td></tr><?php endif; ?></tbody></table></div></section>
<section><h2>Recent attendance logs</h2><div class="table-wrap"><table class="ticket-table compact-table"><thead><tr><th>Date</th><th>Department</th><th>Status</th><th>Headcount</th><th>Logged by</th></tr></thead><tbody><?php foreach ($recent as $row): ?><tr><td><a href="team-attendance.php?date=<?= e($row['attendance_date']) ?>"><?= e($row['attendance_date']) ?></a></td><td><?= e($row['department']) ?></td><td><?= e($statuses[$row['status']] ?? $row['status']) ?></td><td><?= (int) $row['headcount'] ?></td><td class="muted"><?= e($row['logger']) ?></td></tr><?php endforeach; ?><?php if (!$recent): ?><tr><td colspan="5" class="muted">No attendance logs yet.</td></tr><?php endif; ?></tbody></table></div></section>
<?php page_end();
