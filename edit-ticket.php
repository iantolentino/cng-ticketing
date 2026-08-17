<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';
require __DIR__ . '/app/notifications.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('edit_tickets');
$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM tickets WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$id]);
$ticket = $stmt->fetch();
if (!$ticket) { http_response_code(404); exit('Ticket not found.'); }
require_ticket_visible($user, $id);

$departments = active_departments();
$departmentIds = array_map('intval', array_column($departments, 'id'));
$users = active_users();
$userIds = array_map('intval', array_column($users, 'id'));
$selectedDepartments = selected_ids('ticket_departments', 'department_id', $id) ?: [(int) $ticket['department_id']];
$selectedAssignees = selected_ids('ticket_assignees', 'user_id', $id) ?: array_filter([(int) ($ticket['assignee_id'] ?? 0)]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = [
        'issue_escalator' => posted_text('issue_escalator'),
        'subject' => posted_text('subject'),
        'issue' => posted_text('issue'),
        'resolution' => posted_text('resolution'),
        'priority' => posted_text('priority') ?: 'normal',
        'category' => posted_text('category'),
        'subcategory' => posted_text('subcategory'),
        'department_id' => posted_text('department_id'),
        'employee_name' => posted_text('employee_name'),
        'description' => posted_text('description'),
        'status' => posted_text('status') ?: $ticket['status'],
    ];
    $subcategories = TICKET_CATEGORIES[$values['category'] ?? ''] ?? null;
    $departmentId = (int) ($values['department_id'] ?? 0);
    $departmentSelection = posted_ids('department_ids');
    if ($departmentId > 0 && !in_array($departmentId, $departmentSelection, true)) $departmentSelection[] = $departmentId;
    $assigneeIds = user_can('assign_tickets') ? posted_ids('assignee_ids') : $selectedAssignees;

    if (
        empty($values['issue_escalator']) ||
        empty($values['subject']) ||
        empty($values['issue']) ||
        $subcategories === null ||
        !array_key_exists($values['priority'], TICKET_PRIORITIES) ||
        !in_array($departmentId, $departmentIds, true) ||
        !valid_ids($departmentSelection, $departmentIds) ||
        !valid_ids($assigneeIds, $userIds) ||
        empty($values['employee_name']) ||
        empty($values['description']) ||
        ($subcategories && !in_array($values['subcategory'] ?? '', $subcategories, true))
    ) {
        http_response_code(400);
        exit('Invalid ticket details.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $newStatus = $values['status'];
        if (!in_array($newStatus, ['open', 'in_progress', 'pending', 'closed'], true) || ($newStatus === 'closed' && $ticket['status'] !== 'closed' && !user_can('close_tickets'))) {
            http_response_code(403);
            exit('You do not have permission to set this ticket status.');
        }
        $pdo->prepare('UPDATE tickets SET issue_escalator=?,subject=?,issue=?,priority=?,category=?,subcategory=?,department_id=?,employee_name=?,description=?,resolution=?,status=?,closed_at=?,assignee_id=? WHERE id=?')->execute([
            $values['issue_escalator'],
            $values['subject'],
            $values['issue'],
            $values['priority'],
            $values['category'],
            $values['subcategory'] ?: null,
            $departmentId,
            $values['employee_name'],
            $values['description'],
            $values['resolution'] ?: null,
            $newStatus,
            $newStatus === 'closed' ? ($ticket['status'] === 'closed' ? $ticket['closed_at'] : date('Y-m-d H:i:s')) : null,
            $assigneeIds[0] ?? null,
            $id,
        ]);
        sync_ticket_departments($id, $departmentSelection);
        if (user_can('assign_tickets')) sync_ticket_assignees($id, $assigneeIds);
        if (user_can('assign_tickets')) notify_many(array_diff($assigneeIds, $selectedAssignees), (int) $user['id'], 'assignment', 'Ticket assigned: ' . $ticket['ticket_number'], $values['subject'], 'ticket.php?id=' . $id);
        activity($id, $user['id'], 'details_updated', ['issue' => $values['issue'], 'resolution' => $values['resolution'], 'status' => $newStatus]);
        $pdo->commit();
        notify_management('Ticket updated: ' . $ticket['ticket_number'], $values['subject']);
        redirect('ticket.php?id=' . $id);
    } catch (Throwable $exception) {
        $pdo->rollBack();
        http_response_code(500);
        exit('Ticket could not be updated.');
    }
}

page_start('Edit ticket', $user);
?>
<h1>Edit ticket</h1>
<form method="post" class="ticket-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label>Issue Escalator<input name="issue_escalator" value="<?= e($ticket['issue_escalator']) ?>" required></label>
    <label>Subject<input name="subject" value="<?= e($ticket['subject']) ?>" required></label>
    <label>Issue<textarea name="issue" required rows="4"><?= e($ticket['issue'] ?? '') ?></textarea></label>
    <label>Priority<select name="priority" required><?php foreach (TICKET_PRIORITIES as $value => $label): ?><option value="<?= e($value) ?>"<?= ($ticket['priority'] ?? 'normal') === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label>Status<select name="status" required><?php foreach (['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'closed' => 'Closed'] as $value => $label): ?><option value="<?= e($value) ?>"<?= ($ticket['status'] ?? 'open') === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label>Category<select name="category"><?php foreach (TICKET_CATEGORIES as $category => $subcategories): ?><option<?= $ticket['category'] === $category ? ' selected' : '' ?>><?= e($category) ?></option><?php endforeach; ?></select></label>
    <label>Subcategory<input name="subcategory" value="<?= e($ticket['subcategory']) ?>"></label>
    <label>Current department<select name="department_id"><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"<?= (int) $ticket['department_id'] === (int) $department['id'] ? ' selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></label>
    <label>Departments involved<select name="department_ids[]" multiple size="5"><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"<?= in_array((int) $department['id'], $selectedDepartments, true) ? ' selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></label>
    <label>Employee<input name="employee_name" value="<?= e($ticket['employee_name']) ?>" required></label>
    <?php if (user_can('assign_tickets')): ?><label>Assignees<select name="assignee_ids[]" multiple size="6"><?php foreach ($users as $member): ?><option value="<?= (int) $member['id'] ?>"<?= in_array((int) $member['id'], $selectedAssignees, true) ? ' selected' : '' ?>><?= e($member['full_name']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
    <label>Description<textarea name="description" rows="6" required><?= e($ticket['description']) ?></textarea></label>
    <label>Resolution<textarea name="resolution" rows="5"><?= e($ticket['resolution'] ?? '') ?></textarea></label>
    <button class="button">Save ticket details</button>
</form>
<?php page_end();
