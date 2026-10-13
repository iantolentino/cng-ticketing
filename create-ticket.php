<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';
require __DIR__ . '/app/ticket_attachment.php';
require __DIR__ . '/app/notifications.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('create_tickets');
$error = '';
$values = [];
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
        $attachment = null;
        try {
            if (isset($_FILES['attachment']) && !is_array($_FILES['attachment'])) {
                throw new RuntimeException('Attachment upload failed. Please choose the file again.');
            }
            $attachment = validate_creation_attachment($_FILES['attachment'] ?? null, user_can('upload_attachments'));
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
    }
    if ($error === '') {
        $pdo = db();
        $savedAttachmentPath = null;
        try {
            ticket_visibility_prepare_allocator($pdo);
            $pdo->beginTransaction();
            $id = next_ticket_id($pdo);
            $number = 'CNG-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $pdo->prepare('INSERT INTO tickets(id,ticket_number,issue_escalator,subject,issue,priority,category,subcategory,department_id,employee_name,description,assignee_id,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $id,
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
            // Explicit ID remains reserved until this transaction commits.
            sync_ticket_departments($id, $departmentSelection);
            sync_ticket_assignees($id, $assigneeIds);
            activity($id, $user['id'], 'created', ['ticket_number' => $number]);
            if ($attachment !== null) save_creation_attachment($pdo, $id, (int) $user['id'], $attachment, $savedAttachmentPath);
            notify_new_ticket($assigneeIds, (int) $user['id'], $number, $values['subject'], $id);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($savedAttachmentPath !== null && is_file($savedAttachmentPath)) unlink($savedAttachmentPath);
            $error = 'Ticket could not be created. If you selected an attachment, please choose it again before retrying.';
        }
        if ($error === '') {
            notify_management('New ticket ' . $number, $values['subject'], $id);
            redirect('ticket.php?id=' . $id);
        }
    }
}

page_start('Create ticket', $user);
?>
<div class="record-form-screen">
<?php if ($error): ?><p class="auth-error"><?= e($error) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="ticket-form record-form-panel">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="record-form-head"><div><h2>Ticket details</h2><p>Record the issue, routing, and responsible team.</p></div><span>Required fields</span></div><div class="record-form-grid">
    <label>Issue Escalator<input value="<?= e($user['full_name']) ?>" readonly aria-describedby="issue-escalator-help"><small id="issue-escalator-help" class="field-help">Automatically recorded from the signed-in account.</small></label>
    <label>Subject<input name="subject" value="<?= e($values['subject'] ?? '') ?>" required></label>
    <label>Issue<textarea name="issue" required rows="4"><?= e($values['issue'] ?? '') ?></textarea></label>
    <label>Priority<select name="priority" required data-ui-select><?php foreach (TICKET_PRIORITIES as $value => $label): ?><option value="<?= e($value) ?>"<?= ($values['priority'] ?? 'normal') === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label>Category<select name="category" id="category" required data-ui-select><option value="">Select category</option><?php foreach (TICKET_CATEGORIES as $category => $subcategories): ?><option<?= ($values['category'] ?? '') === $category ? ' selected' : '' ?>><?= e($category) ?></option><?php endforeach; ?></select></label>
    <?php $formSubcategories = TICKET_CATEGORIES[$values['category'] ?? ''] ?? []; ?><label>Subcategory<select name="subcategory" id="subcategory" data-ui-select><option value=""><?= $formSubcategories ? 'Select subcategory' : (($values['category'] ?? '') ? 'No subcategory' : 'Select category first') ?></option><?php foreach ($formSubcategories as $subcategoryOption): ?><option value="<?= e($subcategoryOption) ?>"<?= ($values['subcategory'] ?? '') === $subcategoryOption ? ' selected' : '' ?>><?= e($subcategoryOption) ?></option><?php endforeach; ?></select></label>
    <label>Current department<select name="department_id" id="department-id" required data-ui-select><option value="">Select category first</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"<?= (string) ($values['department_id'] ?? '') === (string) $department['id'] ? ' selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></label>
    <label>Departments involved<select name="department_ids[]" id="department-ids" multiple size="5"><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"><?= e($department['name']) ?></option><?php endforeach; ?></select><small class="field-help">Options are selected from the category’s department group.</small></label>
    <label>Employee<input name="employee_name" value="<?= e($values['employee_name'] ?? '') ?>" required></label>
    <?php if (user_can('assign_tickets')): ?><label>Assignees<select name="assignee_ids[]" id="assignee-ids" multiple size="6"><?php foreach ($users as $member): ?><option value="<?= (int) $member['id'] ?>" data-department-id="<?= (int) ($member['department_id'] ?? 0) ?>" data-role="<?= e($member['role_slug'] ?? '') ?>"><?= e($member['full_name']) ?></option><?php endforeach; ?></select><small class="field-help">Assignees are filtered to the selected category’s department group.</small></label><?php endif; ?>
    <label>Description<textarea name="description" required rows="6"><?= e($values['description'] ?? '') ?></textarea></label>
    <?php if (user_can('upload_attachments')): ?><label>Attachment (optional)<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" aria-describedby="attachment-help"><small id="attachment-help" class="field-help">PDF, JPG, or PNG, up to 10 MB (server limits may be lower). Only roles with attachment access can download it. If the form has an error, choose the file again.</small></label><?php endif; ?>
    </div><div class="record-form-actions"><a class="button button-secondary" href="index.php">Cancel</a><button class="button">Create ticket</button></div>
</form></div>
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
  department.dispatchEvent(new Event('change', { bubbles: true }));
  Array.from(departments.options).forEach(option => { const allowed = allowedDepartments.length === 0 || allowedDepartments.includes(option.value); option.hidden = !allowed; option.selected = allowed; });
  if (assignees) Array.from(assignees.options).forEach(option => { const allowed = allowedAssignees.length === 0 || allowedAssignees.includes(option.value); option.hidden = !allowed; option.selected = allowed ? option.selected : false; });
}
category.onchange = () => {
  subcategory.innerHTML = '<option value="">No subcategory</option>';
  (map[category.value] || []).forEach(value => subcategory.add(new Option(value, value)));
  subcategory.required = (map[category.value] || []).length > 0;
  subcategory.dispatchEvent(new Event('ui-options-changed'));
  applyCategoryDefaults();
};
applyCategoryDefaults();
</script>
<?php page_end();
