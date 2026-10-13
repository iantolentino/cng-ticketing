<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/notifications.php';
require __DIR__ . '/app/roster.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('access_leave_request_module');
$pdo = db();
$statuses = [
    'pending' => 'Pending Team Leader',
    'team_leader_approved' => 'Pending Department Head',
    'department_head_approved' => 'Approved',
    'rejected' => 'Rejected',
];
$notice = '';
$warning = '';
$error = '';
$roleSlug = (string) ($user['role_slug'] ?? '');
$canApprove = in_array($roleSlug, ['team-leader', 'department-head'], true);
$canSubmit = $roleSlug === 'team-member';
// Leaders and administrators may file leave on behalf of a staff member.
$canSubmitOnBehalf = in_array($roleSlug, ['super-admin', 'management', 'cng-admin', 'pod-leader', 'team-leader', 'department-head'], true);
$canViewAllRequests = $canApprove || $canSubmitOnBehalf;
$requestedStartDate = trim((string) ($_GET['start_date'] ?? ''));
$requestedEndDate = trim((string) ($_GET['end_date'] ?? ''));
foreach (['requestedStartDate', 'requestedEndDate'] as $requestedDateVariable) {
    $requestedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $$requestedDateVariable);
    if (!$requestedDate || $requestedDate->format('Y-m-d') !== $$requestedDateVariable) $$requestedDateVariable = '';
}
if ($requestedStartDate !== '' && $requestedEndDate === '') $requestedEndDate = $requestedStartDate;
if ($requestedEndDate !== '' && ($requestedStartDate === '' || $requestedEndDate < $requestedStartDate)) $requestedEndDate = '';

/** Normalised name key, so "KC  Pena" and "kc pena" resolve to the same person. */
function leave_name_key(string $name): string
{
    return strtolower(trim((string) preg_replace('/\s+/', ' ', $name)));
}

/** User account that belongs to a roster name, or null when the staff member has no login. */
function leave_user_id_for_name(string $fullName): ?int
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (db()->query('SELECT id, full_name FROM users WHERE is_active = 1')->fetchAll() as $row) {
            $key = leave_name_key((string) $row['full_name']);
            if (!isset($map[$key])) $map[$key] = (int) $row['id'];
        }
    }
    return $map[leave_name_key($fullName)] ?? null;
}

/** Roster row that belongs to a user account name, or null when the account is not on the roster. */
function leave_staff_id_for_name(string $fullName): ?int
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (db()->query('SELECT id, full_name FROM staff_directory WHERE is_active = 1 ORDER BY id')->fetchAll() as $row) {
            $key = leave_name_key((string) $row['full_name']);
            if (!isset($map[$key])) $map[$key] = (int) $row['id'];
        }
    }
    return $map[leave_name_key($fullName)] ?? null;
}

/** Copy an approved request into the shared staff-leave calendar row used by attendance. */
function leave_materialise(array $request, array $actor): array
{
    $staffId = (int) ($request['staff_id'] ?? 0);
    if ($staffId < 1) return ['ok' => false, 'error' => 'The request is not linked to a roster staff member.'];
    return roster_leave_save([
        'staff_id' => $staffId,
        'start_date' => (string) $request['start_date'],
        'end_date' => (string) $request['end_date'],
        'leave_type' => (string) ($request['leave_type'] ?: 'annual'),
        'notes' => 'Approved leave request #' . (int) $request['id'],
    ], $actor);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $staffIdInput = (int) ($_POST['staff_id'] ?? 0);
        $onBehalf = $staffIdInput > 0;
        if ($onBehalf && !$canSubmitOnBehalf) { http_response_code(403); exit('You do not have permission to file leave for another staff member.'); }
        if (!$onBehalf && !$canSubmit) { http_response_code(403); exit('You do not have permission to submit leave requests.'); }
        $startDate = trim((string) ($_POST['start_date'] ?? ''));
        $endDate = trim((string) ($_POST['end_date'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $leaveType = trim((string) ($_POST['leave_type'] ?? 'annual'));
        $file = $_FILES['supporting_file'] ?? null;
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
        $staffRow = $onBehalf ? roster_staff_load($staffIdInput) : null;
        if (!$start || !$end || $start->format('Y-m-d') !== $startDate || $end->format('Y-m-d') !== $endDate || $endDate < $startDate || $reason === '') {
            $error = 'Enter a valid date range and reason.';
        } elseif (!isset(ROSTER_LEAVE_TYPES[$leaveType])) {
            $error = 'Choose a valid leave type.';
        } elseif ($onBehalf && !$staffRow) {
            $error = 'Choose a staff member from the roster.';
        } elseif ($onBehalf && ($staffRow['employment_status'] ?? 'active') === 'resigned') {
            $error = 'Resigned staff cannot be given new leave. Set their status back to Active first.';
        } elseif (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'Upload a supporting screenshot, photo, or PDF.';
        } elseif (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            $error = 'Supporting file must be 10 MB or smaller.';
        } else {
            $originalName = basename((string) $file['name']);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? (finfo_file($finfo, (string) $file['tmp_name']) ?: 'application/octet-stream') : 'application/octet-stream';
            if ($finfo) finfo_close($finfo);
            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
            if (!in_array($extension, $allowedExtensions, true) || !in_array($mime, $allowedMimes, true)) {
                $error = 'Only PDF, JPG, and PNG supporting files are allowed.';
            } else {
                $employeeName = $onBehalf ? (string) $staffRow['full_name'] : (string) $user['full_name'];
                $employeeUserId = $onBehalf ? leave_user_id_for_name($employeeName) : (int) $user['id'];
                $linkedStaffId = $onBehalf ? $staffIdInput : (leave_staff_id_for_name($employeeName) ?? 0);
                // A team leader filing for their own team member already performs the TL step;
                // every other filer goes through the normal approval chain.
                $selfApproved = $onBehalf && $roleSlug === 'team-leader';
                $initialStatus = $selfApproved ? 'team_leader_approved' : 'pending';
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('INSERT INTO leave_requests(employee_user_id, staff_id, created_by, leave_type, start_date, end_date, reason, status, team_leader_approval_by, team_leader_approval_at) VALUES(?,?,?,?,?,?,?,?,?,?)')
                        ->execute([
                            $employeeUserId,
                            $linkedStaffId > 0 ? $linkedStaffId : null,
                            $user['id'],
                            $leaveType,
                            $startDate,
                            $endDate,
                            $reason,
                            $initialStatus,
                            $selfApproved ? $user['id'] : null,
                            $selfApproved ? date('Y-m-d H:i:s') : null,
                        ]);
                    $requestId = (int) $pdo->lastInsertId();
                    $storageDir = ROOT_PATH . '/storage/private/leave-attachments/' . $requestId;
                    if (!is_dir($storageDir)) mkdir($storageDir, 0775, true);
                    $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
                    $relativePath = 'storage/private/leave-attachments/' . $requestId . '/' . $storedName;
                    if (!move_uploaded_file((string) $file['tmp_name'], ROOT_PATH . '/' . $relativePath)) {
                        throw new RuntimeException('Upload failed.');
                    }
                    try {
                        $pdo->prepare('INSERT INTO leave_request_attachments(leave_request_id,uploaded_by,file_name,file_path,mime_type,file_size) VALUES(?,?,?,?,?,?)')->execute([$requestId, $user['id'], $originalName, $relativePath, $mime, (int) $file['size']]);
                    } catch (Throwable $attachmentException) {
                        if (is_file(ROOT_PATH . '/' . $relativePath)) unlink(ROOT_PATH . '/' . $relativePath);
                        throw $attachmentException;
                    }
                    $leaveSummary = $employeeName . ' leave from ' . $startDate . ' to ' . $endDate;
                    $filerNote = $onBehalf ? ' filed by ' . $user['full_name'] : ' requested leave';
                    if ($selfApproved) {
                        notify_many(active_user_ids_by_role('department-head'), (int) $user['id'], 'approval', 'Leave request needs Dept Head approval', $leaveSummary . $filerNote, 'leave-requests.php');
                    } else {
                        notify_many(active_user_ids_by_role('team-leader'), (int) $user['id'], 'approval', 'Leave request needs TL approval', $leaveSummary . $filerNote, 'leave-requests.php');
                    }
                    if ($onBehalf && $employeeUserId && $employeeUserId !== (int) $user['id']) {
                        notify_user($employeeUserId, (int) $user['id'], 'approval', 'Leave filed for you', $user['full_name'] . ' filed your leave from ' . $startDate . ' to ' . $endDate, 'leave-requests.php');
                    }
                    $pdo->commit();
                    audit_admin_action((int) $user['id'], 'leave_request_created', 'leave_request', $requestId, ['staff_id' => $linkedStaffId, 'on_behalf' => $onBehalf, 'status' => $initialStatus, 'leave_type' => $leaveType]);
                    redirect('leave-requests.php?notice=' . ($onBehalf ? 'filed' : 'submitted'));
                } catch (Throwable $exception) {
                    if (isset($relativePath) && is_file(ROOT_PATH . '/' . $relativePath)) unlink(ROOT_PATH . '/' . $relativePath);
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $error = 'Leave request could not be submitted.';
                }
            }
        }
    } elseif ($action === 'approve' || $action === 'reject') {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        if ($roleSlug === 'team-leader') {
            $nextStatus = $action === 'approve' ? 'team_leader_approved' : 'rejected';
            $stmt = $pdo->prepare('UPDATE leave_requests SET status=?, team_leader_approval_by=?, team_leader_approval_at=NOW() WHERE id=? AND status="pending"');
            $stmt->execute([$nextStatus, $user['id'], $requestId]);
            if ($stmt->rowCount()) {
                audit_admin_action((int) $user['id'], 'leave_' . $action, 'leave_request', $requestId, ['stage' => 'team_leader', 'status' => $nextStatus]);
                $request = $pdo->prepare('SELECT lr.employee_user_id, lr.start_date, lr.end_date, COALESCE(u.full_name, sd.full_name) AS full_name FROM leave_requests lr LEFT JOIN users u ON u.id = lr.employee_user_id LEFT JOIN staff_directory sd ON sd.id = lr.staff_id WHERE lr.id = ?');
                $request->execute([$requestId]);
                $leave = $request->fetch();
                if ($leave) {
                    if ($leave['employee_user_id']) notify_user((int) $leave['employee_user_id'], (int) $user['id'], 'approval', 'Leave request ' . ($action === 'approve' ? 'approved by TL' : 'rejected'), 'Leave from ' . $leave['start_date'] . ' to ' . $leave['end_date'], 'leave-requests.php');
                    if ($action === 'approve') notify_many(active_user_ids_by_role('department-head'), (int) $user['id'], 'approval', 'Leave request needs Dept Head approval', $leave['full_name'] . ' requested leave from ' . $leave['start_date'] . ' to ' . $leave['end_date'], 'leave-requests.php');
                }
            }
        } elseif ($roleSlug === 'department-head') {
            $nextStatus = $action === 'approve' ? 'department_head_approved' : 'rejected';
            $stmt = $pdo->prepare('UPDATE leave_requests SET status=?, department_head_approval_by=?, department_head_approval_at=NOW() WHERE id=? AND status="team_leader_approved"');
            $stmt->execute([$nextStatus, $user['id'], $requestId]);
            if ($stmt->rowCount()) {
                audit_admin_action((int) $user['id'], 'leave_' . $action, 'leave_request', $requestId, ['stage' => 'department_head', 'status' => $nextStatus]);
                $request = $pdo->prepare('SELECT id, employee_user_id, staff_id, start_date, end_date, leave_type FROM leave_requests WHERE id = ?');
                $request->execute([$requestId]);
                $leave = $request->fetch();
                if ($leave) {
                    if ($leave['employee_user_id']) notify_user((int) $leave['employee_user_id'], (int) $user['id'], 'approval', 'Leave request ' . ($action === 'approve' ? 'approved' : 'rejected'), 'Leave from ' . $leave['start_date'] . ' to ' . $leave['end_date'], 'leave-requests.php');
                    $material = ['ok' => true, 'error' => ''];
                    if ($action === 'approve') $material = leave_materialise($leave, $user);
                    redirect('leave-requests.php?notice=updated' . ($material['ok'] ? '' : '&leave_warning=' . urlencode($material['error'])));
                }
            }
        } else {
            http_response_code(403);
            exit('You do not have permission to approve leave requests.');
        }
        redirect('leave-requests.php?notice=updated');
    }
}

$notices = ['submitted' => 'Leave request submitted.', 'filed' => 'Leave request filed for the selected staff member.', 'updated' => 'Leave request updated.'];
$notice = $notices[$_GET['notice'] ?? ''] ?? '';
$warning = trim((string) ($_GET['leave_warning'] ?? ''));

$countLeave = static function (string $statusSql, array $params) use ($pdo): int {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM leave_requests lr LEFT JOIN staff_directory sd ON sd.id = lr.staff_id WHERE ' . $statusSql);
    $statement->execute($params);
    return (int) $statement->fetchColumn();
};

$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));
if ($canViewAllRequests) {
    $tlPendingCount = $countLeave('lr.status = ?', ['pending']);
    $deptPendingCount = $countLeave('lr.status = ?', ['team_leader_approved']);
    $approvedThisWeekCount = $countLeave('lr.status = ? AND lr.start_date <= ? AND lr.end_date >= ?', ['department_head_approved', $weekEnd, $weekStart]);
} else {
    $tlPendingCount = $countLeave('lr.employee_user_id = ? AND lr.status = ?', [$user['id'], 'pending']);
    $deptPendingCount = $countLeave('lr.employee_user_id = ? AND lr.status = ?', [$user['id'], 'team_leader_approved']);
    $approvedThisWeekCount = $countLeave('lr.employee_user_id = ? AND lr.status = ? AND lr.start_date <= ? AND lr.end_date >= ?', [$user['id'], 'department_head_approved', $weekEnd, $weekStart]);
}

if ($canApprove) {
    $queueStatus = $roleSlug === 'team-leader' ? 'pending' : 'team_leader_approved';
    $queue = $pdo->prepare('SELECT lr.*, COALESCE(u.full_name, sd.full_name, \'Unknown\') AS employee, d.name AS department, sd.team_label, filer.full_name AS filed_by FROM leave_requests lr LEFT JOIN users u ON u.id = lr.employee_user_id LEFT JOIN staff_directory sd ON sd.id = lr.staff_id LEFT JOIN departments d ON d.id = sd.department_id LEFT JOIN users filer ON filer.id = lr.created_by WHERE lr.status = ? ORDER BY lr.submitted_at ASC');
    $queue->execute([$queueStatus]);
    $approvalRequests = $queue->fetchAll();
} else {
    $approvalRequests = [];
}

$ownStatement = $pdo->prepare('SELECT lr.*, (SELECT COUNT(*) FROM leave_request_attachments lra WHERE lra.leave_request_id = lr.id) AS attachment_count FROM leave_requests lr WHERE lr.employee_user_id = ? ORDER BY lr.submitted_at DESC');
$ownStatement->execute([$user['id']]);
$requests = $ownStatement->fetchAll();

if ($canViewAllRequests) {
    $allStatement = $pdo->prepare('SELECT lr.*, COALESCE(u.full_name, sd.full_name, \'Unknown\') AS employee, d.name AS department, sd.team_label, filer.full_name AS filed_by, (SELECT COUNT(*) FROM leave_request_attachments lra WHERE lra.leave_request_id = lr.id) AS attachment_count FROM leave_requests lr LEFT JOIN users u ON u.id = lr.employee_user_id LEFT JOIN staff_directory sd ON sd.id = lr.staff_id LEFT JOIN departments d ON d.id = sd.department_id LEFT JOIN users filer ON filer.id = lr.created_by ORDER BY lr.submitted_at DESC LIMIT 200');
    $allStatement->execute();
    $allRequests = $allStatement->fetchAll();
} else {
    $allRequests = [];
}
$staffOptions = $canSubmitOnBehalf ? roster_staff_rows(['statuses' => ROSTER_WORKING_STATUSES]) : [];
$staffByTeam = [];
foreach ($staffOptions as $staffOption) $staffByTeam[(string) $staffOption['team_label']][] = $staffOption;

page_start('Leave requests', $user);
?>
<div class="leave-screen">
    <?php if ($notice): ?><p class="action-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($warning): ?><p class="action-notice is-warning" role="status">The request was approved, but the staff leave entry could not be created: <?= e($warning) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="auth-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <section class="leave-summary-panel" aria-label="Leave request summary">
        <div class="leave-summary-card<?= $tlPendingCount > 0 ? ' is-alert' : '' ?>"><span><?= $canViewAllRequests ? 'TL approvals' : 'My TL approval' ?></span><strong><?= (int) $tlPendingCount ?></strong><small><?= $canViewAllRequests ? 'Waiting for Team Leader' : 'Pending Team Leader review' ?></small></div>
        <div class="leave-summary-card<?= $deptPendingCount > 0 ? ' is-alert' : '' ?>"><span><?= $canViewAllRequests ? 'Dept Head approvals' : 'My Dept Head approval' ?></span><strong><?= (int) $deptPendingCount ?></strong><small>Waiting for Department Head</small></div>
        <div class="leave-summary-card"><span>Approved this week</span><strong><?= (int) $approvedThisWeekCount ?></strong><small><?= e(date('M j', strtotime($weekStart))) ?> – <?= e(date('M j', strtotime($weekEnd))) ?></small></div>
    </section>
    <?php if ($canSubmit): ?><section class="leave-form-panel"><div class="section-head"><div><h2>New leave request</h2><p class="muted">Submit dates, supporting proof, and a clear reason.</p></div></div><form method="post" enctype="multipart/form-data" class="leave-request-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create"><div class="calendar-form-field leave-range-picker" data-date-range-picker><span class="calendar-field-label">Leave dates</span><input type="hidden" name="start_date" required data-date-from><input type="hidden" name="end_date" required data-date-to><button type="button" class="filter-picker-trigger" data-date-range-trigger aria-expanded="false"><span class="filter-picker-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M8 3.5v3M16 3.5v3M3.5 9.5h17"/></svg></span><span class="filter-picker-copy"><strong data-date-range-label>Select date range</strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu date-range-menu" data-date-range-menu hidden></div></div><label>Leave type<select name="leave_type"><?php foreach (ROSTER_LEAVE_TYPES as $leaveTypeKey => $leaveTypeLabel): ?><option value="<?= e($leaveTypeKey) ?>"><?= e($leaveTypeLabel) ?></option><?php endforeach; ?></select></label><label>Supporting file<input type="file" name="supporting_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required></label><label class="is-wide">Reason<textarea name="reason" rows="4" required placeholder="Briefly explain the leave request."></textarea></label><div class="leave-form-actions"><small>PDF, JPG, or PNG · up to 10 MB</small><button class="button">Submit request</button></div></form></section><?php endif; ?>
    <?php if ($canSubmitOnBehalf): ?><section class="leave-form-panel"><div class="section-head"><div><h2>File leave for a staff member</h2><p class="muted">Choose the staff member, their leave type and dates, then attach their proof.</p></div><?php if ($roleSlug === 'team-leader'): ?><span class="section-count">Skips TL approval</span><?php endif; ?></div><form method="post" enctype="multipart/form-data" class="leave-request-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create"><label class="is-wide">Staff member<select name="staff_id" required><option value="">Choose a staff member&hellip;</option><?php foreach ($staffByTeam as $teamLabel => $teamStaff): ?><optgroup label="<?= e((string) $teamLabel) ?>"><?php foreach ($teamStaff as $teamMember): ?><option value="<?= (int) $teamMember['id'] ?>"><?= e($teamMember['full_name']) ?><?= ($teamMember['employment_status'] ?? 'active') !== 'active' ? ' · ' . e(roster_status_label((string) $teamMember['employment_status'])) : '' ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label><div class="calendar-form-field leave-range-picker" data-date-range-picker><span class="calendar-field-label">Leave dates</span><input type="hidden" name="start_date" required data-date-from><input type="hidden" name="end_date" required data-date-to><button type="button" class="filter-picker-trigger" data-date-range-trigger aria-expanded="false"><span class="filter-picker-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M8 3.5v3M16 3.5v3M3.5 9.5h17"/></svg></span><span class="filter-picker-copy"><strong data-date-range-label>Select date range</strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu date-range-menu" data-date-range-menu hidden></div></div><label>Leave type<select name="leave_type"><?php foreach (ROSTER_LEAVE_TYPES as $leaveTypeKey => $leaveTypeLabel): ?><option value="<?= e($leaveTypeKey) ?>"><?= e($leaveTypeLabel) ?></option><?php endforeach; ?></select></label><label>Supporting file<input type="file" name="supporting_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required></label><label class="is-wide">Reason<textarea name="reason" rows="4" required placeholder="Reason recorded for this staff member."></textarea></label><div class="leave-form-actions"><small>Saved once for the calendar and attendance on approval</small><button class="button">File leave request</button></div></form></section><?php endif; ?>
    <?php if ($canApprove): ?><section class="leave-table-panel"><div class="section-head"><div><h2><?= $roleSlug === 'team-leader' ? 'Team Leader queue' : 'Department Head queue' ?></h2><p class="muted"><?= $roleSlug === 'team-leader' ? 'Approved requests move to Department Head review.' : 'Only Team Leader-approved requests appear here. Approving creates the staff leave entry.' ?></p></div><span class="section-count"><?= count($approvalRequests) ?> pending</span></div><div class="table-wrap"><table class="ticket-table compact-table"><thead><tr><th>Employee</th><th>Department</th><th>Leave dates</th><th>Type</th><th>Reason</th><th>Filed by</th><th>Decision</th></tr></thead><tbody><?php foreach ($approvalRequests as $request): ?><tr><td class="table-primary"><?= e($request['employee']) ?><?php if ($request['team_label']): ?><small class="muted"> <?= e((string) $request['team_label']) ?></small><?php endif; ?></td><td><?= e($request['department'] ?? 'Unassigned') ?></td><td><?= e(date('M j', strtotime((string) $request['start_date']))) ?> – <?= e(date('M j, Y', strtotime((string) $request['end_date']))) ?></td><td><?= e(roster_leave_type_label((string) ($request['leave_type'] ?? 'annual'))) ?></td><td class="table-wrap-text"><?= e((string) $request['reason']) ?></td><td class="muted"><?= e((string) ($request['filed_by'] ?? '—')) ?></td><td><form method="post" class="row-actions"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><button class="button" name="action" value="approve">Approve</button><button class="button button-secondary" name="action" value="reject">Reject</button></form></td></tr><?php endforeach; ?><?php if (!$approvalRequests): ?><tr><td colspan="7" class="empty-table-cell">No leave requests need your approval.</td></tr><?php endif; ?></tbody></table></div></section><?php endif; ?>
    <?php if ($canViewAllRequests): ?><section class="leave-table-panel"><div class="section-head"><div><h2>All leave records</h2><p class="muted">Every request, including leave filed on behalf of staff.</p></div><span class="section-count"><?= count($allRequests) ?> requests</span></div><div class="table-wrap"><table class="ticket-table compact-table"><thead><tr><th>Employee</th><th>Department</th><th>Leave dates</th><th>Type</th><th>Status</th><th>Filed by</th><th>Files</th></tr></thead><tbody><?php foreach ($allRequests as $request): ?><tr><td class="table-primary"><?= e($request['employee']) ?></td><td><?= e($request['department'] ?? 'Unassigned') ?></td><td><?= e(date('M j', strtotime((string) $request['start_date']))) ?> – <?= e(date('M j, Y', strtotime((string) $request['end_date']))) ?></td><td><?= e(roster_leave_type_label((string) ($request['leave_type'] ?? 'annual'))) ?></td><td><span class="pill pill-<?= e(str_replace('_', '-', (string) $request['status'])) ?>"><?= e($statuses[$request['status']] ?? $request['status']) ?></span></td><td class="muted"><?= e((string) ($request['filed_by'] ?? '—')) ?></td><td><?= (int) $request['attachment_count'] ?></td></tr><?php endforeach; ?><?php if (!$allRequests): ?><tr><td colspan="7" class="empty-table-cell">No leave requests yet.</td></tr><?php endif; ?></tbody></table></div></section><?php endif; ?>
    <?php if ($roleSlug === 'team-leader'): ?><section class="leave-table-panel"><div class="section-head"><div><h2>My requests</h2><p class="muted">Your submitted leave and current approval status.</p></div><span class="section-count"><?= count($requests) ?> requests</span></div><div class="table-wrap"><table class="ticket-table compact-table"><thead><tr><th>Leave dates</th><th>Type</th><th>Status</th><th>Reason</th><th>Files</th><th>Submitted</th></tr></thead><tbody><?php foreach ($requests as $request): ?><tr><td class="table-primary"><?= e(date('M j', strtotime((string) $request['start_date']))) ?> – <?= e(date('M j, Y', strtotime((string) $request['end_date']))) ?></td><td><?= e(roster_leave_type_label((string) ($request['leave_type'] ?? 'annual'))) ?></td><td><span class="pill pill-<?= e(str_replace('_', '-', (string) $request['status'])) ?>"><?= e($statuses[$request['status']] ?? $request['status']) ?></span></td><td class="table-wrap-text"><?= e((string) $request['reason']) ?></td><td><?= (int) $request['attachment_count'] ?></td><td class="muted"><?= e(date('M j, Y', strtotime((string) $request['submitted_at']))) ?></td></tr><?php endforeach; ?><?php if (!$requests): ?><tr><td colspan="6" class="empty-table-cell">No leave requests yet.</td></tr><?php endif; ?></tbody></table></div></section><?php endif; ?>
</div>
<?php if ($requestedStartDate !== ''): ?><script>
document.querySelectorAll('[data-date-from]').forEach(function (input) { input.value = <?= json_encode($requestedStartDate) ?>; input.dispatchEvent(new Event('change', { bubbles: true })); });
document.querySelectorAll('[data-date-to]').forEach(function (input) { input.value = <?= json_encode($requestedEndDate) ?>; input.dispatchEvent(new Event('change', { bubbles: true })); });
</script><?php endif; ?>
<?php page_end();
