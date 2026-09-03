<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';
require __DIR__ . '/app/external_tickets.php';
require __DIR__ . '/app/notifications.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('view_all_tickets');
$externalReference = trim((string) ($_GET['external'] ?? ''));
if ($externalReference !== '') {
    [$externalSourceKey, $externalTicketId] = array_pad(explode(':', $externalReference, 2), 2, '');
    if (!preg_match('/\A[a-z0-9_]+\z/', $externalSourceKey) || !ctype_digit($externalTicketId) || (int) $externalTicketId < 1) {
        http_response_code(404);
        exit('Ticket not found.');
    }
    try {
        $externalTicket = external_ticket_find($externalSourceKey, (int) $externalTicketId, $user);
    } catch (Throwable $exception) {
        error_log('CITS external ticket lookup failed: ' . $externalSourceKey . ' (' . $exception::class . ')');
        $externalTicket = null;
    }
    if (!$externalTicket) {
        http_response_code(404);
        exit('Ticket not found.');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(405);
        exit('External tickets are read-only.');
    }
    try {
        $externalThread = external_ticket_thread($externalSourceKey, (int) $externalTicketId);
        $externalThreadError = false;
    } catch (Throwable $exception) {
        error_log('CITS external thread lookup failed: ' . $externalSourceKey . ' (' . $exception::class . ')');
        $externalThread = [];
        $externalThreadError = true;
    }
    page_start($externalTicket['subject'], $user);
    ?>
    <p class="eyebrow"><?= e($externalTicket['ticket_number']) ?></p>
    <h1><?= e($externalTicket['subject']) ?></h1>
    <p class="page-subtitle"><?php if (!empty($externalTicket['source_url'])): ?><a class="source-badge source-link" href="<?= e($externalTicket['source_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($externalTicket['source']) ?></a><?php else: ?><span class="source-badge"><?= e($externalTicket['source']) ?></span><?php endif; ?> External ticket (read-only)</p>
    <div class="page-actions"><a class="button button-secondary" href="index.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>Back to tickets</a></div>
    <div class="sla-summary sla-summary-external"><span class="sla-badge sla-external">N/A</span><span>SLA is not available from the external source.</span><span><?= ticket_age_days($externalTicket, 'created_at') ?> days old</span><span>Idle: &mdash;</span></div>
    <div class="ticket-detail">
        <section><h2>Ticket details</h2><dl><dt>Source</dt><dd><?php if (!empty($externalTicket['source_url'])): ?><a href="<?= e($externalTicket['source_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($externalTicket['source']) ?></a><?php else: ?><?= e($externalTicket['source']) ?><?php endif; ?></dd><dt>External ID</dt><dd>#<?= (int) $externalTicket['external_id'] ?></dd><dt>Requester</dt><dd><?= e($externalTicket['requester'] ?: '-') ?><?= $externalTicket['email'] ? ' (' . e($externalTicket['email']) . ')' : '' ?></dd><dt>Status</dt><dd><?= e($externalTicket['status_label']) ?></dd><dt>Priority</dt><dd><?= e($externalTicket['priority_label']) ?></dd><dt>Category</dt><dd><?= e($externalTicket['category']) ?></dd><dt>Departments</dt><dd><?= e($externalTicket["departments"] ?? $externalTicket["department"] ?? "-") ?></dd><dt>Subcategory</dt><dd>&mdash;</dd><dt>Assignee</dt><dd><?= e($externalTicket['agent'] ?: 'Unassigned') ?></dd><dt>Created</dt><dd><?= e($externalTicket['created_at'] ?: '-') ?></dd><dt>Date closed</dt><dd><?= e($externalTicket['closed_at'] ?: '-') ?></dd></dl></section>
        <section><h2>Conversation</h2><?php if ($externalThreadError): ?><p class="auth-error">The conversation could not be loaded right now.</p><?php elseif ($externalThread): ?><div class="external-thread"><?php foreach ($externalThread as $message): ?><article class="external-thread-message"><small><?= e($message['date']) ?></small><div class="thread-body"><?= external_thread_body_html((string) $message['body']) ?></div></article><?php endforeach; ?></div><?php else: ?><p class="muted">No messages found for this ticket.</p><?php endif; ?></section>
    </div>
    <?php page_end();
    exit;
}
$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT t.*, d.name department,
    COALESCE((SELECT GROUP_CONCAT(DISTINCT u.full_name ORDER BY u.full_name SEPARATOR ', ') FROM ticket_assignees ta JOIN users u ON u.id = ta.user_id WHERE ta.ticket_id = t.id), a.full_name) AS assignees,
    COALESCE((SELECT GROUP_CONCAT(DISTINCT dep.name ORDER BY dep.name SEPARATOR ', ') FROM ticket_departments td JOIN departments dep ON dep.id = td.department_id WHERE td.ticket_id = t.id), d.name) AS departments
    FROM tickets t
    JOIN departments d ON d.id = t.department_id
    LEFT JOIN users a ON a.id = t.assignee_id
    WHERE t.id = ? AND t.deleted_at IS NULL");
$stmt->execute([$id]);
$ticket = $stmt->fetch();
if (!$ticket) { http_response_code(404); exit('Ticket not found.'); }
require_ticket_visible($user, $id);

$users = active_users();
$userIds = array_map('intval', array_column($users, 'id'));
$selectedAssignees = selected_ids('ticket_assignees', 'user_id', $id) ?: array_filter([(int) ($ticket['assignee_id'] ?? 0)]);
$attachmentError = '';
$sla = ticket_sla_state($ticket);
$canFollowUp = user_can('comment_tickets') && $selectedAssignees && ($ticket['status'] === 'pending' || $sla[0] === 'overdue');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $notice = '';
    if (($_POST['action'] ?? '') === 'follow_up' && $canFollowUp) {
        $placeholders = implode(',', array_fill(0, count($selectedAssignees), '?'));
        $assigneeQuery = db()->prepare("SELECT id,email FROM users WHERE is_active = 1 AND id IN ($placeholders)");
        $assigneeQuery->execute($selectedAssignees);
        $assignees = $assigneeQuery->fetchAll();
        $assigneeIds = array_map('intval', array_column($assignees, 'id'));
        $emails = array_column($assignees, 'email');
        $subject = 'Follow up needed: ' . $ticket['ticket_number'];
        $body = $ticket['subject'] . "\n\nStatus: " . $ticket['status'] . "\nSLA: " . $sla[1] . "\n\nPlease review and update the ticket.";
        send_mail_to_addresses($emails, $subject, $body);
        notify_many($assigneeIds, (int) $user['id'], 'follow_up', $subject, $ticket['subject'], 'ticket.php?id=' . $id);
        activity($id, $user['id'], 'follow_up_sent', ['assignee_ids' => $assigneeIds]);
        $notice = 'follow_up_sent';
    } elseif (($_POST['action'] ?? '') === 'upload_attachment' && user_can('upload_attachments')) {
        $file = $_FILES['attachment'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $attachmentError = 'Choose a PDF or image file to upload.';
        } elseif (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            $attachmentError = 'Attachment must be 10 MB or smaller.';
        } else {
            $originalName = basename((string) $file['name']);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? (finfo_file($finfo, (string) $file['tmp_name']) ?: 'application/octet-stream') : 'application/octet-stream';
            if ($finfo) finfo_close($finfo);
            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
            if (!in_array($extension, $allowedExtensions, true) || !in_array($mime, $allowedMimes, true)) {
                $attachmentError = 'Only PDF, JPG, and PNG attachments are allowed.';
            } else {
                $storageDir = ROOT_PATH . '/storage/private/ticket-attachments/' . $id;
                if (!is_dir($storageDir)) mkdir($storageDir, 0775, true);
                $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
                $relativePath = 'storage/private/ticket-attachments/' . $id . '/' . $storedName;
                if (!move_uploaded_file((string) $file['tmp_name'], ROOT_PATH . '/' . $relativePath)) {
                    $attachmentError = 'Attachment could not be saved.';
                } else {
                    try {
                        db()->prepare('INSERT INTO ticket_attachments(ticket_id,uploaded_by,file_name,file_path,stored_name,original_name,mime_type,file_size) VALUES(?,?,?,?,?,?,?,?)')->execute([$id, $user['id'], $originalName, $relativePath, $storedName, $originalName, $mime, (int) $file['size']]);
                        activity($id, $user['id'], 'attachment_uploaded', ['file_name' => $originalName]);
                        $notice = 'attachment_uploaded';
                    } catch (Throwable $exception) {
                        if (is_file(ROOT_PATH . '/' . $relativePath)) unlink(ROOT_PATH . '/' . $relativePath);
                        $attachmentError = 'Attachment could not be saved.';
                    }
                }
            }
        }
    } elseif (isset($_POST['comment']) && user_can('comment_tickets')) {
        $body = trim((string) $_POST['comment']);
        if ($body !== '') {
            $visibility = (string) ($_POST['visibility'] ?? 'shared');
            if (!in_array($visibility, ['shared', 'assignees', 'departments'], true)) $visibility = 'shared';
            db()->prepare('INSERT INTO ticket_comments(ticket_id,user_id,body,visibility) VALUES(?,?,?,?)')->execute([$id, $user['id'], $body, $visibility]);
            activity($id, $user['id'], 'commented');
            notify_many(array_merge($selectedAssignees, [(int) $ticket['created_by']]), (int) $user['id'], 'comment', 'New comment: ' . $ticket['ticket_number'], $ticket['subject'], 'ticket.php?id=' . $id);
            $notice = 'comment_added';
        }
    } elseif (user_can('edit_tickets') || user_can('assign_tickets')) {
        $resolution = trim((string) ($_POST['resolution'] ?? ($ticket['resolution'] ?? '')));
        $newStatus = user_can('edit_tickets') ? (string) ($_POST['status'] ?? $ticket['status']) : $ticket['status'];
        $assigneeIds = user_can('assign_tickets') ? posted_ids('assignee_ids') : $selectedAssignees;
        $statusChanged = $newStatus !== $ticket['status'];
        if (!in_array($newStatus, ['open', 'in_progress', 'pending', 'closed'], true) || ($statusChanged && $newStatus === 'closed' && !user_can('close_tickets')) || !valid_ids($assigneeIds, $userIds)) {
            http_response_code(400);
            exit('Invalid workflow update.');
        }
        $closedAt = $newStatus === 'closed' ? ($statusChanged ? date('Y-m-d H:i:s') : $ticket['closed_at']) : null;
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE tickets SET status=?,closed_at=?,resolution=?,assignee_id=? WHERE id=?')->execute([$newStatus, $closedAt, $resolution ?: null, $assigneeIds[0] ?? null, $id]);
            if (user_can('assign_tickets')) sync_ticket_assignees($id, $assigneeIds);
            if (user_can('assign_tickets')) notify_many(array_diff($assigneeIds, $selectedAssignees), (int) $user['id'], 'assignment', 'Ticket assigned: ' . $ticket['ticket_number'], $ticket['subject'], 'ticket.php?id=' . $id);
            activity($id, $user['id'], 'updated', ['status' => $newStatus, 'resolution' => $resolution, 'assignee_ids' => $assigneeIds]);
            $pdo->commit();
            notify_management('Ticket updated: ' . $ticket['ticket_number'], $ticket['subject'] . ' - ' . $newStatus);
            $notice = 'changes_saved';
        } catch (Throwable $exception) {
            $pdo->rollBack();
            http_response_code(500);
            exit('Ticket could not be updated.');
        }
    }
    if (!$attachmentError) redirect('ticket.php?id=' . $id . ($notice ? '&notice=' . $notice : ''));
}

$comments = db()->prepare("SELECT c.*,u.full_name FROM ticket_comments c JOIN users u ON u.id=c.user_id WHERE c.ticket_id=? AND (c.visibility='shared' OR c.user_id=? OR (c.visibility='assignees' AND (EXISTS (SELECT 1 FROM ticket_assignees ca WHERE ca.ticket_id=c.ticket_id AND ca.user_id=?) OR EXISTS (SELECT 1 FROM tickets ct WHERE ct.id=c.ticket_id AND ct.assignee_id=?))) OR (c.visibility='departments' AND (EXISTS (SELECT 1 FROM tickets cd JOIN users cu ON cu.id=? WHERE cd.id=c.ticket_id AND cu.department_id=cd.department_id) OR EXISTS (SELECT 1 FROM ticket_departments ctd JOIN users cdu ON cdu.department_id=ctd.department_id WHERE ctd.ticket_id=c.ticket_id AND cdu.id=?)))) ORDER BY c.created_at");
$comments->execute([$id, $user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$events = db()->prepare('SELECT a.*,u.full_name FROM ticket_activity a LEFT JOIN users u ON u.id=a.actor_id WHERE a.ticket_id=? ORDER BY a.created_at DESC');
$events->execute([$id]);
$attachments = [];
if (user_can('view_attachments')) {
    $attachmentQuery = db()->prepare('SELECT ta.*, u.full_name AS uploaded_by_name FROM ticket_attachments ta JOIN users u ON u.id = ta.uploaded_by WHERE ta.ticket_id = ? ORDER BY ta.uploaded_at DESC, ta.created_at DESC');
    $attachmentQuery->execute([$id]);
    $attachments = $attachmentQuery->fetchAll();
}
$notices = ['changes_saved' => 'Changes saved.', 'comment_added' => 'Comment added.', 'attachment_uploaded' => 'Attachment uploaded.', 'follow_up_sent' => 'Follow-up sent to assignees.'];
$notice = $notices[$_GET['notice'] ?? ''] ?? null;
page_start($ticket['subject'], $user);
?>
<?php if ($notice): ?><p class="action-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
<p class="eyebrow"><?= e($ticket['ticket_number']) ?></p>
<h1><?= e($ticket['subject']) ?></h1>
<p class="page-subtitle"><?= e($ticket['departments']) ?> &middot; <?= e($ticket['category']) ?></p>
<div class="sla-summary sla-summary-<?= e($sla[0]) ?>"><span class="sla-badge sla-<?= e($sla[0]) ?>"><?= e($sla[1]) ?></span><span><?= e($sla[2]) ?></span><span><?= ticket_age_days($ticket, 'created_at') ?> days open</span><span><?= ticket_age_days($ticket, 'updated_at') ?> days idle</span></div>
<div class="page-actions"><a class="button button-secondary" href="index.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>Back to tickets</a><?php if ($canFollowUp): ?><form class="inline-form" method="post" data-feedback data-confirm="Send a follow-up to all assignees?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="follow_up"><button class="button button-secondary" data-processing="Sending...">Follow up</button></form><?php endif; ?><?php if (user_can('edit_tickets')): ?><a class="button" href="edit-ticket.php?id=<?= $id ?>">Edit details</a><?php endif; ?><?php if (user_can('delete_tickets')): ?><form class="inline-form" method="post" action="delete-ticket.php" data-feedback data-confirm="Soft-delete this ticket?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $id ?>"><button class="button button-danger" data-processing="Deleting...">Delete ticket</button></form><?php endif; ?></div>
<div class="ticket-detail">
    <section><h2>Description</h2><p><?= nl2br(e($ticket['description'])) ?></p><h2>Issue</h2><p><?= nl2br(e($ticket['issue'] ?? 'Not provided')) ?></p><h2>Resolution</h2><p><?= $ticket['resolution'] ? nl2br(e($ticket['resolution'])) : '<span class="muted">Not resolved; status remains In Progress.</span>' ?></p><dl><dt>Priority</dt><dd><span class="pill pill-priority-<?= e($ticket['priority'] ?? 'normal') ?>"><?= e(TICKET_PRIORITIES[$ticket['priority'] ?? 'normal'] ?? 'Normal') ?></span></dd><dt>Escalator</dt><dd><?= e($ticket['issue_escalator']) ?></dd><dt>Employee</dt><dd><?= e($ticket['employee_name']) ?></dd><dt>Assignees</dt><dd><?= e($ticket['assignees'] ?? 'Unassigned') ?></dd><dt>Current department</dt><dd><?= e($ticket['department']) ?></dd></dl></section>
    <?php if (user_can('edit_tickets') || user_can('assign_tickets')): ?><section><h2>Workflow</h2><form method="post" data-feedback><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><?php if (user_can('edit_tickets')): ?><label>Status<select name="status"><?php foreach (['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'closed' => 'Closed'] as $key => $label): ?><option value="<?= $key ?>"<?= $ticket['status'] === $key ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><?php endif; ?><?php if (user_can('assign_tickets')): ?><label>Assignees<select name="assignee_ids[]" multiple size="6"><?php foreach ($users as $member): ?><option value="<?= (int) $member['id'] ?>"<?= in_array((int) $member['id'], $selectedAssignees, true) ? ' selected' : '' ?>><?= e($member['full_name']) ?></option><?php endforeach; ?></select></label><?php endif; ?><button class="button" data-processing="Saving...">Save changes</button></form></section><?php endif; ?>
</div>
<?php if (user_can('view_attachments') || user_can('upload_attachments')): ?><section><div class="section-head"><div><h2>Confidential attachments</h2><p class="muted">Medical certificates and private files are visible only to roles granted attachment access.</p></div><?php if (user_can('upload_attachments')): ?><span class="pill pill-pending">Restricted</span><?php endif; ?></div><?php if ($attachmentError): ?><p class="auth-error"><?= e($attachmentError) ?></p><?php endif; ?><?php if (user_can('view_attachments')): ?><div class="attachment-list"><?php foreach ($attachments as $attachment): ?><div class="attachment-item"><div><strong><?= e($attachment['file_name'] ?: $attachment['original_name']) ?></strong><br><span class="muted">Uploaded by <?= e($attachment['uploaded_by_name']) ?> on <?= e($attachment['uploaded_at'] ?: $attachment['created_at']) ?></span></div><a class="button button-secondary" href="download-attachment.php?id=<?= (int) $attachment['id'] ?>">Download</a></div><?php endforeach; ?><?php if (!$attachments): ?><p class="muted">No confidential attachments uploaded yet.</p><?php endif; ?></div><?php endif; ?><?php if (user_can('upload_attachments')): ?><form method="post" enctype="multipart/form-data" class="compact-form" data-feedback><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="upload_attachment"><label>Upload attachment<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required></label><button class="button" data-processing="Uploading...">Upload file</button></form><?php endif; ?></section><?php endif; ?>
<section><h2>Comments</h2><?php foreach ($comments as $comment): ?><p><strong><?= e($comment['full_name']) ?></strong> <span class="muted"><?= e($comment['created_at']) ?></span><?php if ($comment['visibility'] !== 'shared'): ?><span class="pill pill-pending"><?= e(ucfirst($comment['visibility'])) ?> only</span><?php endif; ?><br><?= nl2br(e($comment['body'])) ?></p><?php endforeach; ?><?php if (user_can('comment_tickets')): ?><form method="post" data-feedback><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Add comment<textarea name="comment" required rows="3"></textarea></label><label>Visibility<select name="visibility"><option value="shared">All permitted ticket viewers</option><option value="assignees">Assignees and author only</option><option value="departments">Involved department members and author only</option></select></label><button class="button" data-processing="Adding...">Add comment</button></form><?php endif; ?></section>
<section><h2>Activity</h2><?php foreach ($events as $event): ?><p class="muted"><?= e($event['created_at']) ?> - <?= e($event['full_name'] ?? 'System') ?> <?= e($event['action']) ?></p><?php endforeach; ?></section>
<script>document.querySelectorAll('form[data-feedback]').forEach(function(form){form.addEventListener('submit',function(event){if(event.defaultPrevented)return;var button=form.querySelector('button[type="submit"],button');if(!button)return;if(form.dataset.confirm&&!window.confirm(form.dataset.confirm)){event.preventDefault();return;}button.disabled=true;button.classList.add('is-processing');button.textContent=button.dataset.processing||button.textContent;});});</script>
<?php page_end();
