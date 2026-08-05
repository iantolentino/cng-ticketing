<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/notifications.php';
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
$error = '';
$canApprove = in_array($user['role_slug'] ?? '', ['team-leader', 'department-head'], true);
$canSubmit = ($user['role_slug'] ?? '') === 'team-member';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        if (!$canSubmit) { http_response_code(403); exit('You do not have permission to submit leave requests.'); }
        $startDate = trim((string) ($_POST['start_date'] ?? ''));
        $endDate = trim((string) ($_POST['end_date'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $file = $_FILES['supporting_file'] ?? null;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) || $endDate < $startDate || $reason === '') {
            $error = 'Enter a valid date range and reason.';
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
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('INSERT INTO leave_requests(employee_user_id,start_date,end_date,reason) VALUES(?,?,?,?)')->execute([$user['id'], $startDate, $endDate, $reason]);
                    $requestId = (int) $pdo->lastInsertId();
                    $storageDir = ROOT_PATH . '/storage/private/leave-attachments/' . $requestId;
                    if (!is_dir($storageDir)) mkdir($storageDir, 0775, true);
                    $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
                    $relativePath = 'storage/private/leave-attachments/' . $requestId . '/' . $storedName;
                    if (!move_uploaded_file((string) $file['tmp_name'], ROOT_PATH . '/' . $relativePath)) {
                        throw new RuntimeException('Upload failed.');
                    }
                    $pdo->prepare('INSERT INTO leave_request_attachments(leave_request_id,uploaded_by,file_name,file_path,mime_type,file_size) VALUES(?,?,?,?,?,?)')->execute([$requestId, $user['id'], $originalName, $relativePath, $mime, (int) $file['size']]);
                    notify_many(active_user_ids_by_role('team-leader'), (int) $user['id'], 'approval', 'Leave request needs TL approval', $user['full_name'] . ' requested leave from ' . $startDate . ' to ' . $endDate, 'leave-requests.php');
                    $pdo->commit();
                    redirect('leave-requests.php?notice=submitted');
                } catch (Throwable $exception) {
                    $pdo->rollBack();
                    $error = 'Leave request could not be submitted.';
                }
            }
        }
    } elseif ($action === 'approve' || $action === 'reject') {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        if (($user['role_slug'] ?? '') === 'team-leader') {
            $nextStatus = $action === 'approve' ? 'team_leader_approved' : 'rejected';
            $stmt = $pdo->prepare('UPDATE leave_requests SET status=?, team_leader_approval_by=?, team_leader_approval_at=NOW() WHERE id=? AND status="pending"');
            $stmt->execute([$nextStatus, $user['id'], $requestId]);
            if ($stmt->rowCount()) {
                $request = $pdo->prepare('SELECT lr.employee_user_id, lr.start_date, lr.end_date, u.full_name FROM leave_requests lr JOIN users u ON u.id = lr.employee_user_id WHERE lr.id = ?');
                $request->execute([$requestId]);
                $leave = $request->fetch();
                if ($leave) {
                    notify_user((int) $leave['employee_user_id'], (int) $user['id'], 'approval', 'Leave request ' . ($action === 'approve' ? 'approved by TL' : 'rejected'), 'Leave from ' . $leave['start_date'] . ' to ' . $leave['end_date'], 'leave-requests.php');
                    if ($action === 'approve') notify_many(active_user_ids_by_role('department-head'), (int) $user['id'], 'approval', 'Leave request needs Dept Head approval', $leave['full_name'] . ' requested leave from ' . $leave['start_date'] . ' to ' . $leave['end_date'], 'leave-requests.php');
                }
            }
        } elseif (($user['role_slug'] ?? '') === 'department-head') {
            $nextStatus = $action === 'approve' ? 'department_head_approved' : 'rejected';
            $stmt = $pdo->prepare('UPDATE leave_requests SET status=?, department_head_approval_by=?, department_head_approval_at=NOW() WHERE id=? AND status="team_leader_approved"');
            $stmt->execute([$nextStatus, $user['id'], $requestId]);
            if ($stmt->rowCount()) {
                $request = $pdo->prepare('SELECT employee_user_id, start_date, end_date FROM leave_requests WHERE id = ?');
                $request->execute([$requestId]);
                $leave = $request->fetch();
                if ($leave) notify_user((int) $leave['employee_user_id'], (int) $user['id'], 'approval', 'Leave request ' . ($action === 'approve' ? 'approved' : 'rejected'), 'Leave from ' . $leave['start_date'] . ' to ' . $leave['end_date'], 'leave-requests.php');
            }
        } else {
            http_response_code(403);
            exit('You do not have permission to approve leave requests.');
        }
        redirect('leave-requests.php?notice=updated');
    }
}

$notices = ['submitted' => 'Leave request submitted.', 'updated' => 'Leave request updated.'];
$notice = $notices[$_GET['notice'] ?? ''] ?? '';

$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));
if ($canApprove) {
    $tlPendingCount = (int) $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();
    $deptPendingCount = (int) $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'team_leader_approved'")->fetchColumn();
    $approvedThisWeek = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE status = 'department_head_approved' AND start_date <= ? AND end_date >= ?");
    $approvedThisWeek->execute([$weekEnd, $weekStart]);
    $approvedThisWeekCount = (int) $approvedThisWeek->fetchColumn();
} else {
    $tlPending = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_user_id = ? AND status = 'pending'");
    $tlPending->execute([$user['id']]);
    $tlPendingCount = (int) $tlPending->fetchColumn();
    $deptPending = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_user_id = ? AND status = 'team_leader_approved'");
    $deptPending->execute([$user['id']]);
    $deptPendingCount = (int) $deptPending->fetchColumn();
    $approvedThisWeek = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_user_id = ? AND status = 'department_head_approved' AND start_date <= ? AND end_date >= ?");
    $approvedThisWeek->execute([$user['id'], $weekEnd, $weekStart]);
    $approvedThisWeekCount = (int) $approvedThisWeek->fetchColumn();
}

if ($canApprove) {
    $queueStatus = $user['role_slug'] === 'team-leader' ? 'pending' : 'team_leader_approved';
    $queue = $pdo->prepare('SELECT lr.*, u.full_name AS employee FROM leave_requests lr JOIN users u ON u.id = lr.employee_user_id WHERE lr.status = ? ORDER BY lr.submitted_at ASC');
    $queue->execute([$queueStatus]);
    $approvalRequests = $queue->fetchAll();
} else {
    $approvalRequests = [];
}

$ownRequests = $pdo->prepare('SELECT lr.*, u.full_name AS employee, (SELECT COUNT(*) FROM leave_request_attachments lra WHERE lra.leave_request_id = lr.id) AS attachment_count FROM leave_requests lr JOIN users u ON u.id = lr.employee_user_id WHERE lr.employee_user_id = ? ORDER BY lr.submitted_at DESC');
$ownRequests->execute([$user['id']]);
$requests = $ownRequests->fetchAll();

page_start('Leave requests', $user);
?>
<?php if ($notice): ?><p class="action-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="auth-error"><?= e($error) ?></p><?php endif; ?>
<div class="page-head"><div><p class="eyebrow">Self-service</p><h1>Leave requests</h1><p class="page-subtitle"><?= $canSubmit ? 'Submit leave requests and track approval status.' : 'Review leave requests in the required approval order.' ?></p></div></div>
<section class="dashboard-panel" aria-labelledby="leave-dashboard-title">
    <div class="section-head"><div><h2 id="leave-dashboard-title">Leave dashboard</h2><p class="muted"><?= $canApprove ? 'Approval workload and approved leave overlapping this week.' : 'Your approval status and approved leave overlapping this week.' ?></p></div></div>
    <div class="leave-metric-grid">
        <div class="metric-card<?= $tlPendingCount > 0 ? ' metric-card-alert' : '' ?>"><span><?= $canApprove ? 'TL approvals' : 'My TL approval' ?></span><strong><?= (int) $tlPendingCount ?></strong><small><?= $canApprove ? 'Waiting for Team Leader' : 'Waiting for your Team Leader' ?></small></div>
        <div class="metric-card<?= $deptPendingCount > 0 ? ' metric-card-alert' : '' ?>"><span><?= $canApprove ? 'Dept Head approvals' : 'My Dept Head approval' ?></span><strong><?= (int) $deptPendingCount ?></strong><small><?= $canApprove ? 'Waiting for Department Head' : 'Waiting for Department Head' ?></small></div>
        <div class="metric-card"><span>Approved this week</span><strong><?= (int) $approvedThisWeekCount ?></strong><small><?= e(date('M j', strtotime($weekStart))) ?> to <?= e(date('M j', strtotime($weekEnd))) ?></small></div>
    </div>
</section>
<?php if ($canSubmit): ?><section><h2>Submit leave request</h2><form method="post" enctype="multipart/form-data" class="ticket-form compact-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create"><label>Start date<input type="date" name="start_date" required></label><label>End date<input type="date" name="end_date" required></label><label>Supporting screenshot/photo/PDF<input type="file" name="supporting_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required></label><label>Reason<textarea name="reason" rows="4" required></textarea></label><button class="button">Submit request</button></form></section><?php endif; ?>
<?php if ($canApprove): ?><section><div class="section-head"><div><h2><?= $user['role_slug'] === 'team-leader' ? 'Team Leader approval queue' : 'Department Head approval queue' ?></h2><p class="muted"><?= $user['role_slug'] === 'team-leader' ? 'These requests move to Department Head only after approval.' : 'Only Team Leader-approved requests appear here.' ?></p></div></div><div class="table-wrap"><table class="ticket-table compact-table"><thead><tr><th>Employee</th><th>Dates</th><th>Reason</th><th>Submitted</th><th>Decision</th></tr></thead><tbody><?php foreach ($approvalRequests as $request): ?><tr><td><?= e($request['employee']) ?></td><td><?= e($request['start_date']) ?> to <?= e($request['end_date']) ?></td><td><?= e($request['reason']) ?></td><td class="muted"><?= e($request['submitted_at']) ?></td><td><form method="post" class="row-actions"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><button class="button" name="action" value="approve">Approve</button><button class="button button-secondary" name="action" value="reject">Reject</button></form></td></tr><?php endforeach; ?><?php if (!$approvalRequests): ?><tr><td colspan="5" class="muted">No leave requests need your approval.</td></tr><?php endif; ?></tbody></table></div></section><?php endif; ?>
<section><h2><?= $canSubmit ? 'My requests' : 'My leave requests' ?></h2><div class="table-wrap"><table class="ticket-table compact-table"><thead><tr><th>Dates</th><th>Status</th><th>Reason</th><th>Files</th><th>Submitted</th></tr></thead><tbody><?php foreach ($requests as $request): ?><tr><td><?= e($request['start_date']) ?> to <?= e($request['end_date']) ?></td><td><span class="pill pill-<?= e(str_replace('_', '-', $request['status'])) ?>"><?= e($statuses[$request['status']] ?? $request['status']) ?></span></td><td><?= e($request['reason']) ?></td><td><?= (int) $request['attachment_count'] ?></td><td class="muted"><?= e($request['submitted_at']) ?></td></tr><?php endforeach; ?><?php if (!$requests): ?><tr><td colspan="5" class="muted">No leave requests yet.</td></tr><?php endif; ?></tbody></table></div></section>
<?php page_end();
