<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';
require __DIR__ . '/app/notifications.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('create_tickets');
$error = '';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = [
        'issue_escalator' => $user['full_name'],
        'subject' => posted_text('subject'),
        'issue' => posted_text('issue'),
        'priority' => posted_text('priority') ?: 'normal',
        'category' => posted_text('category'),
        'subcategory' => posted_text('subcategory'),
        'department_id' => posted_text('department_id'),
        'employee_name' => posted_text('employee_name'),
        'description' => posted_text('description'),
    ];
    $subcategories = TICKET_CATEGORIES[$values['category'] ?? ''] ?? null;
    $departmentId = (int) ($values['department_id'] ?? 0);
    $departmentSelection = posted_ids('department_ids');
    if ($departmentId > 0 && !in_array($departmentId, $departmentSelection, true)) $departmentSelection[] = $departmentId;
    $assigneeIds = user_can('assign_tickets') ? posted_ids('assignee_ids') : [];
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
        !valid_ids($assigneeIds, $categoryAssigneeIds) ||
        empty($values['employee_name']) ||
        empty($values['description']) ||
        ($subcategories && !in_array($values['subcategory'] ?? '', $subcategories, true))
    ) {
        $error = 'Complete all required fields with valid departments, category, and subcategory.';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $number = 'CNG-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $pdo->prepare('INSERT INTO tickets(ticket_number,issue_escalator,subject,issue,priority,category,subcategory,department_id,employee_name,description,assignee_id,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $number,
                $values['issue_escalator'],
                $values['subject'],
                $values['issue'],
                $values['priority'],
                $values['category'],
                $values['subcategory'] ?: null,
                $departmentId,
                $values['employee_name'],
                $values['description'],
                $assigneeIds[0] ?? null,
                $user['id'],
            ]);
            $id = (int) $pdo->lastInsertId();
            sync_ticket_departments($id, $departmentSelection);
            sync_ticket_assignees($id, $assigneeIds);
            activity($id, $user['id'], 'created', ['ticket_number' => $number]);
            notify_many($assigneeIds, (int) $user['id'], 'assignment', 'Ticket assigned: ' . $number, $values['subject'], 'ticket.php?id=' . $id);
            $pdo->commit();
            notify_management('New ticket ' . $number, $values['subject']);
            redirect('ticket.php?id=' . $id);
        } catch (Throwable $exception) {
            $pdo->rollBack();
            $error = 'Ticket could not be created.';
        }
    }
}

page_start('Create ticket', $user);
?>
<div class="page-head"><div><p class="eyebrow">New record</p><h1>Create ticket</h1></div></div>
<?php if ($error): ?><p class="auth-error"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="ticket-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label>Issue Escalator<input value="<?= e($user['full_name']) ?>" readonly aria-describedby="issue-escalator-help"><small id="issue-escalator-help" class="field-help">Automatically recorded from the signed-in account.</small></label>
    <label>Subject<input name="subject" required></label>
    <label>Issue<textarea name="issue" required rows="4"></textarea></label>
    <label>Priority<select name="priority" required><?php foreach (TICKET_PRIORITIES as $value => $label): ?><option value="<?= e($value) ?>"<?= $value === 'normal' ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label>Category<select name="category" id="category" required><option value="">Select category</option><?php foreach (TICKET_CATEGORIES as $category => $subcategories): ?><option><?= e($category) ?></option><?php endforeach; ?></select></label>
    <label>Subcategory<select name="subcategory" id="subcategory"><option value="">Select category first</option></select></label>
    <label>Current department<select name="department_id" id="department-id" required><option value="">Select category first</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"><?= e($department['name']) ?></option><?php endforeach; ?></select></label>
    <label>Departments involved<select name="department_ids[]" id="department-ids" multiple size="5"><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"><?= e($department['name']) ?></option><?php endforeach; ?></select><small class="field-help">Options are selected from the category’s department group.</small></label>
    <label>Employee<input name="employee_name" required></label>
    <?php if (user_can('assign_tickets')): ?><label>Assignees<select name="assignee_ids[]" id="assignee-ids" multiple size="6"><?php foreach ($users as $member): ?><option value="<?= (int) $member['id'] ?>" data-department-id="<?= (int) ($member['department_id'] ?? 0) ?>" data-role="<?= e($member['role_slug'] ?? '') ?>"><?= e($member['full_name']) ?></option><?php endforeach; ?></select><small class="field-help">Assignees are filtered to the selected category’s department group.</small></label><?php endif; ?>
    <label>Description<textarea name="description" required rows="6"></textarea></label>
    <button class="button">Create ticket</button>
</form>
<script>
const map = <?= json_encode(TICKET_CATEGORIES) ?>;
const categoryDepartmentMap = <?= json_encode($categoryDepartmentMap) ?>;
const categoryAssigneeMap = <?= json_encode($categoryAssigneeMap) ?>;
const category = document.querySelector('#category'), subcategory = document.querySelector('#subcategory'), department = document.querySelector('#department-id'), departments = document.querySelector('#department-ids'), assignees = document.querySelector('#assignee-ids');
function applyCategoryDefaults() {
  const allowedDepartments = (categoryDepartmentMap[category.value] || []).map(String);
  const allowedAssignees = (categoryAssigneeMap[category.value] || []).map(String);
  Array.from(department.options).forEach(option => { if (!option.value) return; option.hidden = allowedDepartments.length > 0 && !allowedDepartments.includes(option.value); });
  if (allowedDepartments.length > 0 && !allowedDepartments.includes(department.value)) department.value = allowedDepartments[0];
  Array.from(departments.options).forEach(option => { const allowed = allowedDepartments.length === 0 || allowedDepartments.includes(option.value); option.hidden = !allowed; option.selected = allowed; });
  if (assignees) Array.from(assignees.options).forEach(option => { const allowed = allowedAssignees.length === 0 || allowedAssignees.includes(option.value); option.hidden = !allowed; option.selected = allowed ? option.selected : false; });
}
category.onchange = () => {
  subcategory.innerHTML = '<option value="">No subcategory</option>';
  (map[category.value] || []).forEach(value => subcategory.add(new Option(value, value)));
  subcategory.required = (map[category.value] || []).length > 0;
  applyCategoryDefaults();
};
applyCategoryDefaults();
</script>
<?php page_end();
