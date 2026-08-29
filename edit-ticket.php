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
$categoryDepartmentMap = [];
$categoryAssigneeMap = [];
foreach (array_keys(TICKET_CATEGORIES) as $category) {
    $categoryDepartmentMap[$category] = category_department_ids($category, $departments);
    $categoryAssigneeMap[$category] = category_assignee_ids($category, $departments, $users);
}
$selectedDepartments = selected_ids('ticket_departments', 'department_id', $id) ?: [(int) $ticket['department_id']];
$selectedAssignees = selected_ids('ticket_assignees', 'user_id', $id) ?: array_filter([(int) ($ticket['assignee_id'] ?? 0)]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = [
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
    $categoryDepartmentIds = category_department_ids($values['category'], $departments);
    $categoryAssigneeIds = category_assignee_ids($values['category'], $departments, $users);

    if (
        empty($values['issue_escalator']) ||
        empty($values['subject']) ||
        empty($values['issue']) ||
        $subcategories === null ||
        !array_key_exists($values['priority'], TICKET_PRIORITIES) ||
        !in_array($departmentId, $departmentIds, true) ||
        !valid_ids($departmentSelection, $departmentIds) ||
        !in_array($departmentId, $categoryDepartmentIds, true) ||
        !valid_ids($departmentSelection, $categoryDepartmentIds) ||
        !valid_ids($assigneeIds, $userIds) ||
        (user_can('assign_tickets') && !valid_ids($assigneeIds, $categoryAssigneeIds)) ||
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
        $pdo->prepare('UPDATE tickets SET subject=?,issue=?,priority=?,category=?,subcategory=?,department_id=?,employee_name=?,description=?,resolution=?,status=?,closed_at=?,assignee_id=? WHERE id=?')->execute([
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
<div class="record-form-screen">
<form method="post" class="ticket-form record-form-panel">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="record-form-head"><div><h2>Ticket details</h2><p>Update the issue record without changing its workflow history.</p></div><span><?= e($ticket['ticket_number']) ?></span></div><div class="record-form-grid">
    <label>Issue Escalator<input value="<?= e($ticket['issue_escalator']) ?>" readonly aria-describedby="issue-escalator-help"><small id="issue-escalator-help" class="field-help">Recorded from the account that submitted the ticket and cannot be changed here.</small></label>
    <label>Subject<input name="subject" value="<?= e($ticket['subject']) ?>" required></label>
    <label>Issue<textarea name="issue" required rows="4"><?= e($ticket['issue'] ?? '') ?></textarea></label>
    <label>Priority<select name="priority" required data-ui-select><?php foreach (TICKET_PRIORITIES as $value => $label): ?><option value="<?= e($value) ?>"<?= ($ticket['priority'] ?? 'normal') === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label>Status<select name="status" required data-ui-select><?php foreach (['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'closed' => 'Closed'] as $value => $label): ?><option value="<?= e($value) ?>"<?= ($ticket['status'] ?? 'open') === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label>Category<select name="category" id="category" required data-ui-select><?php foreach (TICKET_CATEGORIES as $category => $subcategories): ?><option<?= $ticket['category'] === $category ? ' selected' : '' ?>><?= e($category) ?></option><?php endforeach; ?></select></label>
    <label>Subcategory<select name="subcategory" id="subcategory" data-ui-select><?php if ($ticket['subcategory']): ?><option value="<?= e($ticket['subcategory']) ?>" selected><?= e($ticket['subcategory']) ?></option><?php else: ?><option value="">Select category first</option><?php endif; ?></select></label>
    <label>Current department<select name="department_id" id="department-id" required data-ui-select><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"<?= (int) $ticket['department_id'] === (int) $department['id'] ? ' selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></label>
    <label>Departments involved<select name="department_ids[]" id="department-ids" multiple size="5"><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"<?= in_array((int) $department['id'], $selectedDepartments, true) ? ' selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select><small class="field-help">Options are selected from the category’s department group.</small></label>
    <label>Employee<input name="employee_name" value="<?= e($ticket['employee_name']) ?>" required></label>
    <?php if (user_can('assign_tickets')): ?><label>Assignees<select name="assignee_ids[]" id="assignee-ids" multiple size="6"><?php foreach ($users as $member): ?><option value="<?= (int) $member['id'] ?>" data-department-id="<?= (int) ($member['department_id'] ?? 0) ?>" data-role="<?= e($member['role_slug'] ?? '') ?>"<?= in_array((int) $member['id'], $selectedAssignees, true) ? ' selected' : '' ?>><?= e($member['full_name']) ?></option><?php endforeach; ?></select><small class="field-help">Assignees are filtered to the selected category’s department group.</small></label><?php endif; ?>
    <label>Description<textarea name="description" rows="6" required><?= e($ticket['description']) ?></textarea></label>
    <label>Resolution<textarea name="resolution" rows="5"><?= e($ticket['resolution'] ?? '') ?></textarea></label>
    </div><div class="record-form-actions"><a class="button button-secondary" href="ticket.php?id=<?= $id ?>">Cancel</a><button class="button">Save ticket details</button></div>
</form></div>
<script>
const map = <?= json_encode(TICKET_CATEGORIES) ?>;
const categoryDepartmentMap = <?= json_encode($categoryDepartmentMap) ?>;
const categoryAssigneeMap = <?= json_encode($categoryAssigneeMap) ?>;
const category = document.querySelector('#category'), subcategory = document.querySelector('#subcategory'), department = document.querySelector('#department-id'), departments = document.querySelector('#department-ids'), assignees = document.querySelector('#assignee-ids');
const currentSubcategory = <?= json_encode($ticket['subcategory'] ?? '') ?>;
function applyCategoryDefaults() {
  const allowedDepartments = (categoryDepartmentMap[category.value] || []).map(String);
  const allowedAssignees = (categoryAssigneeMap[category.value] || []).map(String);
  Array.from(department.options).forEach(option => { option.hidden = allowedDepartments.length > 0 && !allowedDepartments.includes(option.value); });
  if (allowedDepartments.length > 0 && !allowedDepartments.includes(department.value)) department.value = allowedDepartments[0];
  Array.from(departments.options).forEach(option => { const allowed = allowedDepartments.length === 0 || allowedDepartments.includes(option.value); option.hidden = !allowed; if (!allowed) option.selected = false; });
  if (assignees) Array.from(assignees.options).forEach(option => { const allowed = allowedAssignees.length === 0 || allowedAssignees.includes(option.value); option.hidden = !allowed; if (!allowed) option.selected = false; });
}
function updateSubcategories(keepCurrent) {
  const values = map[category.value] || [];
  subcategory.innerHTML = '<option value="">No subcategory</option>';
  values.forEach(value => subcategory.add(new Option(value, value)));
  if (keepCurrent && values.includes(currentSubcategory)) subcategory.value = currentSubcategory;
  subcategory.required = values.length > 0;
}
category.addEventListener('change', () => { updateSubcategories(false); applyCategoryDefaults(); });
updateSubcategories(true);
applyCategoryDefaults();
</script>
<?php page_end();
