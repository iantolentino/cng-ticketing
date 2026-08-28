<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('view_all_tickets');
if (($user['role_slug'] ?? '') === 'cng-admin') { http_response_code(403); exit('You do not have permission to access this action.'); }
$pdo = db();
$notice = '';
$error = '';
$statuses = [
    'annual' => 'Annual',
    'sick' => 'Sick',
    'emergency' => 'Emergency',
    'half_day' => 'Half-day',
    'birthday' => 'Birthday',
    'bereavement' => 'Bereavement',
    'paternity' => 'Paternity',
    'maternity' => 'Maternity',
    'undertime' => 'Undertime',
];
$canLog = ($user['role_slug'] ?? '') === 'team-leader' || user_can('manage_roles');
$selectedDate = request_string($_GET, 'date') ?: date('Y-m-d');
$selectedDateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $selectedDate);
if (!$selectedDateValue || $selectedDateValue->format('Y-m-d') !== $selectedDate) $selectedDate = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$canLog) { http_response_code(403); exit('You do not have permission to manage attendance.'); }
    $action = request_string($_POST, 'action') ?: 'save';
    $attendanceId = (int) request_string($_POST, 'attendance_id');
    $attendanceDate = request_string($_POST, 'attendance_date');
    $departmentId = (int) request_string($_POST, 'department_id');
    $status = request_string($_POST, 'status') ?: 'annual';
    $headcountValue = request_string($_POST, 'headcount');
    $headcount = $headcountValue === '' ? 0 : (int) $headcountValue;
    $notes = request_string($_POST, 'notes');
    $staffIds = posted_ids('staff_ids');
    $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $attendanceDate);
    $departmentCheck = $pdo->prepare('SELECT id FROM departments WHERE id = ?');
    $departmentCheck->execute([$departmentId]);
    $staffCheck = $pdo->query('SELECT id FROM staff_directory WHERE is_active = 1');
    $validStaffIds = array_map('intval', $staffCheck->fetchAll(PDO::FETCH_COLUMN));

    if (!in_array($action, ['save', 'update'], true) || !$dateValue || $dateValue->format('Y-m-d') !== $attendanceDate || !$departmentCheck->fetchColumn() || !isset($statuses[$status]) || ($headcountValue !== '' && !ctype_digit($headcountValue)) || $headcount < 0 || !valid_ids($staffIds, $validStaffIds)) {
        $error = 'Choose a valid date, department, status, headcount, and staff list.';
    } elseif ($action === 'update' && $attendanceId < 1) {
        $error = 'Choose an attendance record to edit.';
    } else {
        $pdo->beginTransaction();
        try {
            if ($action === 'update') {
                $existing = $pdo->prepare('SELECT id FROM team_attendance WHERE id = ?');
                $existing->execute([$attendanceId]);
                if (!$existing->fetchColumn()) throw new InvalidArgumentException('Attendance record not found.');
                $pdo->prepare('UPDATE team_attendance SET attendance_date=?,department_id=?,logged_by=?,status=?,headcount=?,notes=? WHERE id=?')->execute([$attendanceDate, $departmentId, $user['id'], $status, $headcount, $notes ?: null, $attendanceId]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO team_attendance(attendance_date,department_id,logged_by,status,headcount,notes) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id), logged_by=VALUES(logged_by), status=VALUES(status), headcount=VALUES(headcount), notes=VALUES(notes)');
                $stmt->execute([$attendanceDate, $departmentId, $user['id'], $status, $headcount, $notes ?: null]);
                $attendanceId = (int) $pdo->lastInsertId();
            }
            $pdo->prepare('DELETE FROM team_attendance_leave WHERE attendance_id = ?')->execute([$attendanceId]);
            $insertLeave = $pdo->prepare('INSERT INTO team_attendance_leave(attendance_id,staff_id) VALUES(?,?)');
            foreach ($staffIds as $staffId) $insertLeave->execute([$attendanceId, $staffId]);
            $pdo->commit();
            redirect('team-attendance.php?date=' . rawurlencode($attendanceDate) . '&notice=' . ($action === 'update' ? 'updated' : 'saved'));
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Attendance could not be saved.';
        }
    }
}

$notice = ['saved' => 'Attendance logged.', 'updated' => 'Attendance updated.'][request_string($_GET, 'notice')] ?? '';
$departments = $pdo->query('SELECT id,name FROM departments ORDER BY name')->fetchAll();
$staff = $pdo->query('SELECT id,full_name,team_label FROM staff_directory WHERE is_active=1 ORDER BY full_name')->fetchAll();
$editId = (int) request_string($_GET, 'edit');
$editing = null;
$selectedLeaveStaffIds = [];
if ($editId > 0) {
    $editQuery = $pdo->prepare('SELECT * FROM team_attendance WHERE id = ?');
    $editQuery->execute([$editId]);
    $editing = $editQuery->fetch() ?: null;
    if ($editing) {
        $selectedLeave = $pdo->prepare('SELECT staff_id FROM team_attendance_leave WHERE attendance_id = ?');
        $selectedLeave->execute([$editId]);
        $selectedLeaveStaffIds = array_map('intval', $selectedLeave->fetchAll(PDO::FETCH_COLUMN));
    }
}
$formDate = $editing['attendance_date'] ?? $selectedDate;
$formDepartmentId = (int) ($editing['department_id'] ?? ($departments[0]['id'] ?? 0));
$formStatus = $editing['status'] ?? 'annual';
$formHeadcount = (int) ($editing['headcount'] ?? 0);
$formNotes = $editing['notes'] ?? '';
$formStatuses = $statuses;
if ($formStatus !== '' && !isset($formStatuses[$formStatus])) $formStatuses[$formStatus] = 'Legacy: ' . $formStatus;

$summaryStmt = $pdo->prepare('SELECT ta.*, d.name AS department, u.full_name AS logger, (SELECT COUNT(*) FROM team_attendance_leave tal WHERE tal.attendance_id = ta.id) AS leave_count FROM team_attendance ta JOIN departments d ON d.id = ta.department_id JOIN users u ON u.id = ta.logged_by WHERE ta.attendance_date = ? ORDER BY d.name');
$summaryStmt->execute([$selectedDate]);
$dayRows = $summaryStmt->fetchAll();
$leaveByAttendance = [];
if ($dayRows) {
    $ids = array_map('intval', array_column($dayRows, 'id'));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $leaveQuery = $pdo->prepare('SELECT tal.attendance_id, sd.full_name FROM team_attendance_leave tal JOIN staff_directory sd ON sd.id = tal.staff_id WHERE tal.attendance_id IN (' . $placeholders . ') ORDER BY sd.full_name');
    $leaveQuery->execute($ids);
    foreach ($leaveQuery->fetchAll() as $leaveRow) $leaveByAttendance[(int) $leaveRow['attendance_id']][] = $leaveRow['full_name'];
}
$recent = $pdo->query('SELECT ta.*, d.name AS department, u.full_name AS logger, (SELECT COUNT(*) FROM team_attendance_leave tal WHERE tal.attendance_id = ta.id) AS leave_count FROM team_attendance ta JOIN departments d ON d.id = ta.department_id JOIN users u ON u.id = ta.logged_by ORDER BY ta.attendance_date DESC, d.name LIMIT 20')->fetchAll();

page_start('Team Attendance', $user);
?>
<?php if ($notice): ?><p class="action-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="auth-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<div class="page-head"><div><p class="eyebrow">Planning</p><h1>Team Attendance</h1><p class="page-subtitle">Record leave status and staff absences so the Team Calendar shows accurate team availability.</p></div><form method="get" class="month-form"><label>Date<input type="date" name="date" value="<?= e($selectedDate) ?>" onchange="this.form.submit()"></label></form></div>
<?php if ($canLog): ?><section><h2><?= $editing ? 'Edit attendance record' : 'Log attendance' ?></h2><form method="post" class="ticket-form compact-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="<?= $editing ? 'update' : 'save' ?>"><input type="hidden" name="attendance_id" value="<?= (int) ($editing['id'] ?? 0) ?>"><label>Date<input type="date" name="attendance_date" required value="<?= e($formDate) ?>"></label><label>Department<select name="department_id" required><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"<?= $formDepartmentId === (int) $department['id'] ? ' selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></label><label>Status<select name="status" required><?php foreach ($formStatuses as $key => $label): ?><option value="<?= e($key) ?>"<?= $formStatus === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><label>Headcount<input type="number" name="headcount" min="0" value="<?= $formHeadcount ?>"></label><label>Staff on leave<details class="staff-picker"><summary>Select staff<?= $selectedLeaveStaffIds ? ' (' . count($selectedLeaveStaffIds) . ' selected)' : '' ?></summary><div class="staff-picker-options"><?php foreach ($staff as $member): ?><label><input type="checkbox" name="staff_ids[]" value="<?= (int) $member['id'] ?>"<?= in_array((int) $member['id'], $selectedLeaveStaffIds, true) ? ' checked' : '' ?>> <span><?= e($member['full_name']) ?></span><small><?= e($member['team_label']) ?></small></label><?php endforeach; ?></div></details></label><label>Notes<textarea name="notes" rows="3" placeholder="Optional attendance or coverage notes"><?= e($formNotes) ?></textarea></label><div><button class="button"><?= $editing ? 'Update attendance' : 'Save attendance' ?></button><?php if ($editing): ?> <a class="button button-secondary" href="team-attendance.php?date=<?= e($selectedDate) ?>">Cancel</a><?php endif; ?></div></form></section><?php endif; ?>
<section><div class="section-head"><div><h2><?= e(date('M j, Y', strtotime($selectedDate))) ?> attendance</h2><p class="muted">These records appear on Team Calendar for the same date.</p></div></div><div class="table-wrap"><table class="ticket-table compact-table"><thead><tr><th>Department</th><th>Status</th><th>Headcount</th><th>Staff on leave</th><th>Notes</th><th>Logged by</th><?php if ($canLog): ?><th>Action</th><?php endif; ?></tr></thead><tbody><?php foreach ($dayRows as $row): ?><tr><td><?= e($row['department']) ?></td><td><span class="pill pill-<?= e(str_replace('_', '-', $row['status'])) ?>"><?= e($statuses[$row['status']] ?? $row['status']) ?></span></td><td><?= (int) $row['headcount'] ?></td><td><?= e(implode(', ', $leaveByAttendance[(int) $row['id']] ?? []) ?: 'None') ?></td><td><?= e($row['notes'] ?: '-') ?></td><td class="muted"><?= e($row['logger']) ?></td><?php if ($canLog): ?><td><a href="team-attendance.php?date=<?= e($selectedDate) ?>&edit=<?= (int) $row['id'] ?>">Edit</a></td><?php endif; ?></tr><?php endforeach; ?><?php if (!$dayRows): ?><tr><td colspan="<?= $canLog ? '7' : '6' ?>" class="muted">No attendance logged for this date.</td></tr><?php endif; ?></tbody></table></div></section>
<section><h2>Recent attendance logs</h2><div class="table-wrap"><table class="ticket-table compact-table"><thead><tr><th>Date</th><th>Department</th><th>Status</th><th>Headcount</th><th>Staff on leave</th><th>Logged by</th><?php if ($canLog): ?><th>Action</th><?php endif; ?></tr></thead><tbody><?php foreach ($recent as $row): ?><tr><td><a href="team-attendance.php?date=<?= e($row['attendance_date']) ?>"><?= e($row['attendance_date']) ?></a></td><td><?= e($row['department']) ?></td><td><?= e($statuses[$row['status']] ?? $row['status']) ?></td><td><?= (int) $row['headcount'] ?></td><td><?= (int) $row['leave_count'] ?></td><td class="muted"><?= e($row['logger']) ?></td><?php if ($canLog): ?><td><a href="team-attendance.php?date=<?= e($row['attendance_date']) ?>&edit=<?= (int) $row['id'] ?>">Edit</a></td><?php endif; ?></tr><?php endforeach; ?><?php if (!$recent): ?><tr><td colspan="<?= $canLog ? '7' : '6' ?>" class="muted">No attendance logs yet.</td></tr><?php endif; ?></tbody></table></div></section>
<?php page_end();
