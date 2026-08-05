<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('view_all_tickets');
if (($user['role_slug'] ?? '') === 'cng-admin') { http_response_code(403); exit('You do not have permission to access this action.'); }

$departments = active_departments();
$departmentIds = array_map('intval', array_column($departments, 'id'));
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $ticketId = (int) ($_POST['ticket_id'] ?? 0);
    require_ticket_visible($user, $ticketId);
    $departmentQuery = db()->prepare('SELECT department_id FROM tickets WHERE id = ? AND deleted_at IS NULL');
    $departmentQuery->execute([$ticketId]);
    $currentDepartmentId = (int) $departmentQuery->fetchColumn();
    $selection = posted_ids('department_ids');
    if ($currentDepartmentId > 0 && !in_array($currentDepartmentId, $selection, true)) $selection[] = $currentDepartmentId;
    if (!$ticketId || !$currentDepartmentId || !valid_ids($selection, $departmentIds)) {
        $error = 'Choose valid departments for the selected ticket.';
    } else {
        sync_ticket_departments($ticketId, $selection);
        activity($ticketId, (int) $user['id'], 'departments_logged', ['department_ids' => $selection]);
        redirect('department-workload.php?notice=departments_logged');
    }
}

$notice = ($_GET['notice'] ?? '') === 'departments_logged' ? 'Department log updated.' : '';
$scopeParams = [];
$scopeSql = ticket_scope_sql($user, $scopeParams);

$summary = db()->prepare("SELECT d.id, d.name,
    COUNT(DISTINCT CASE WHEN t.department_id = d.id THEN t.id END) AS current_count,
    COUNT(DISTINCT td.ticket_id) AS involved_count,
    COUNT(DISTINCT CASE WHEN t.status <> 'closed' AND (t.department_id = d.id OR td.department_id = d.id) THEN t.id END) AS open_count,
    COUNT(DISTINCT CASE WHEN t.status <> 'closed' AND t.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) AND (t.department_id = d.id OR td.department_id = d.id) THEN t.id END) AS overdue_count
    FROM departments d
    LEFT JOIN tickets t ON t.deleted_at IS NULL AND (t.department_id = d.id OR EXISTS (SELECT 1 FROM ticket_departments td_match WHERE td_match.ticket_id = t.id AND td_match.department_id = d.id)) $scopeSql
    LEFT JOIN ticket_departments td ON td.ticket_id = t.id AND td.department_id = d.id
    GROUP BY d.id, d.name
    ORDER BY open_count DESC, involved_count DESC, d.name");
$summary->execute($scopeParams);
$departmentSummary = $summary->fetchAll();

$ticketParams = [];
$ticketScopeSql = ticket_scope_sql($user, $ticketParams);
$ticketQuery = db()->prepare("SELECT t.id, t.ticket_number, t.subject, t.status, t.department_id, d.name AS current_department,
    COALESCE(GROUP_CONCAT(DISTINCT dep.name ORDER BY dep.name SEPARATOR ', '), d.name) AS departments,
    COALESCE(GROUP_CONCAT(DISTINCT dep.id ORDER BY dep.name SEPARATOR ','), t.department_id) AS department_ids,
    COALESCE((SELECT GROUP_CONCAT(DISTINCT u.full_name ORDER BY u.full_name SEPARATOR ', ') FROM ticket_assignees ta JOIN users u ON u.id = ta.user_id WHERE ta.ticket_id = t.id), a.full_name) AS assignees
    FROM tickets t
    JOIN departments d ON d.id = t.department_id
    LEFT JOIN ticket_departments td ON td.ticket_id = t.id
    LEFT JOIN departments dep ON dep.id = td.department_id
    LEFT JOIN users a ON a.id = t.assignee_id
    WHERE t.deleted_at IS NULL $ticketScopeSql
    GROUP BY t.id, t.ticket_number, t.subject, t.status, t.department_id, d.name, a.full_name
    ORDER BY FIELD(t.status,'open','in_progress','pending','closed'), t.updated_at DESC, t.id DESC
    LIMIT 40");
$ticketQuery->execute($ticketParams);
$tickets = $ticketQuery->fetchAll();

page_start('Department workload', $user);
?>
<?php if ($notice): ?><p class="action-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="auth-error"><?= e($error) ?></p><?php endif; ?>
<div class="page-head"><div><p class="eyebrow">Department tracking</p><h1>Department workload</h1><p class="page-subtitle">Track current and involved departments for tickets you can access.</p></div></div>
<section class="dashboard-panel">
    <div class="section-head"><div><h2>Workload by department</h2><p class="muted">Counts include current department and logged involved departments.</p></div></div>
    <div class="workload-grid"><?php foreach ($departmentSummary as $department): ?><div class="workload-card"><span><?= e($department['name']) ?></span><strong><?= (int) $department['open_count'] ?></strong><small><?= (int) $department['involved_count'] ?> involved, <?= (int) $department['current_count'] ?> current, <?= (int) $department['overdue_count'] ?> overdue</small></div><?php endforeach; ?></div>
</section>
<section>
    <div class="section-head"><div><h2>Log involved departments</h2><p class="muted">Team Leaders can update department involvement for their assigned tickets.</p></div></div>
    <div class="table-wrap"><table class="ticket-table department-log-table"><thead><tr><?php foreach (['Status', 'Ticket', 'Current department', 'Departments involved', 'Assignees', 'Log departments'] as $heading): ?><th><?= e($heading) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($tickets as $ticket): $selected = array_map('intval', array_filter(explode(',', (string) $ticket['department_ids']))); ?><tr><td><span class="pill pill-<?= e(str_replace('_', '-', $ticket['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', $ticket['status']))) ?></span></td><td class="subject"><a href="ticket.php?id=<?= (int) $ticket['id'] ?>"><?= e($ticket['subject']) ?></a><br><span class="muted"><?= e($ticket['ticket_number']) ?></span></td><td><?= e($ticket['current_department']) ?></td><td><?= e($ticket['departments']) ?></td><td><?= e($ticket['assignees'] ?? 'Unassigned') ?></td><td><form method="post" class="department-log-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>"><select name="department_ids[]" multiple size="4"><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"<?= in_array((int) $department['id'], $selected, true) || (int) $ticket['department_id'] === (int) $department['id'] ? ' selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select><button class="button button-secondary">Save</button></form></td></tr><?php endforeach; ?><?php if (!$tickets): ?><tr><td colspan="6" class="muted">No tickets are available for department logging.</td></tr><?php endif; ?></tbody></table></div>
</section>
<?php page_end();
