<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('view_all_tickets');
if (($user['role_slug'] ?? '') === 'cng-admin') { http_response_code(403); exit('You do not have permission to access this action.'); }

$statuses = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'closed' => 'Closed'];
$status = trim((string) ($_GET['status'] ?? ''));
$priority = trim((string) ($_GET['priority'] ?? ''));
if (!isset($statuses[$status])) $status = '';
if (!isset(TICKET_PRIORITIES[$priority])) $priority = '';
$params = ['user_id' => (int) $user['id'], 'user_id_pivot' => (int) $user['id']];
$where = ['t.deleted_at IS NULL', '(t.assignee_id = :user_id OR EXISTS (SELECT 1 FROM ticket_assignees ta_scope WHERE ta_scope.ticket_id = t.id AND ta_scope.user_id = :user_id_pivot))'];
if ($status !== '') {
    $where[] = 't.status = :status';
    $params['status'] = $status;
}
if ($priority !== '') {
    $where[] = 't.priority = :priority';
    $params['priority'] = $priority;
}
$whereSql = implode(' AND ', $where);

$summary = db()->prepare('SELECT t.status, COUNT(*) AS count FROM tickets t WHERE ' . $where[0] . ' AND ' . $where[1] . ' GROUP BY t.status');
$summary->execute(['user_id' => (int) $user['id'], 'user_id_pivot' => (int) $user['id']]);
$counts = array_fill_keys(array_keys($statuses), 0);
foreach ($summary->fetchAll() as $row) {
    if (isset($counts[$row['status']])) $counts[$row['status']] = (int) $row['count'];
}
$openWork = $counts['open'] + $counts['in_progress'] + $counts['pending'];
$overdue = db()->prepare('SELECT COUNT(*) FROM tickets t WHERE ' . $where[0] . ' AND ' . $where[1] . ' AND t.status <> "closed" AND t.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
$overdue->execute(['user_id' => (int) $user['id'], 'user_id_pivot' => (int) $user['id']]);
$overdueCount = (int) $overdue->fetchColumn();
$urgent = db()->prepare('SELECT COUNT(*) FROM tickets t WHERE ' . $where[0] . ' AND ' . $where[1] . ' AND t.status <> "closed" AND t.priority = "urgent"');
$urgent->execute(['user_id' => (int) $user['id'], 'user_id_pivot' => (int) $user['id']]);
$urgentCount = (int) $urgent->fetchColumn();
$idle = db()->prepare('SELECT COUNT(*) FROM tickets t WHERE ' . $where[0] . ' AND ' . $where[1] . ' AND t.status <> "closed" AND t.updated_at < DATE_SUB(NOW(), INTERVAL 3 DAY) AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
$idle->execute(['user_id' => (int) $user['id'], 'user_id_pivot' => (int) $user['id']]);
$idleCount = (int) $idle->fetchColumn();

$query = db()->prepare("SELECT t.*, d.name AS department,
    COALESCE((SELECT GROUP_CONCAT(DISTINCT dep.name ORDER BY dep.name SEPARATOR ', ') FROM ticket_departments td JOIN departments dep ON dep.id = td.department_id WHERE td.ticket_id = t.id), d.name) AS departments,
    COALESCE((SELECT GROUP_CONCAT(DISTINCT u.full_name ORDER BY u.full_name SEPARATOR ', ') FROM ticket_assignees ta JOIN users u ON u.id = ta.user_id WHERE ta.ticket_id = t.id), a.full_name) AS assignees
    FROM tickets t
    JOIN departments d ON d.id = t.department_id
    LEFT JOIN users a ON a.id = t.assignee_id
    WHERE $whereSql
    ORDER BY FIELD(t.status,'open','in_progress','pending','closed'), t.updated_at DESC, t.id DESC
    LIMIT 100");
foreach ($params as $key => $value) $query->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
$query->execute();
$tickets = $query->fetchAll();

page_start('My Work', $user);
?>
<div class="page-head"><div><p class="eyebrow">Assigned queue</p><h1>My Work</h1><p class="page-subtitle">Tickets assigned to <?= e($user['full_name']) ?>.</p></div><div class="page-head-actions"><?php if (user_can('create_tickets')): ?><a class="button" href="create-ticket.php">Create ticket</a><?php endif; ?></div></div>
<section class="dashboard-panel">
    <div class="metric-grid">
        <div class="metric-card"><span>Open work</span><strong><?= (int) $openWork ?></strong><small>Open, in progress, pending</small></div>
        <div class="metric-card metric-card-alert"><span>Urgent</span><strong><?= (int) $urgentCount ?></strong><small>Assigned urgent tickets</small></div>
        <div class="metric-card metric-card-alert"><span>Overdue</span><strong><?= (int) $overdueCount ?></strong><small>7+ days open</small></div>
        <div class="metric-card"><span>Idle watch</span><strong><?= (int) $idleCount ?></strong><small>3+ days without update</small></div>
        <div class="metric-card"><span>Closed</span><strong><?= (int) $counts['closed'] ?></strong><small>Assigned and completed</small></div>
        <div class="metric-card"><span>Total assigned</span><strong><?= array_sum($counts) ?></strong><small>All statuses</small></div>
    </div>
    <div class="status-strip"><?php foreach ($statuses as $key => $label): ?><div><span class="pill pill-<?= e(str_replace('_', '-', $key)) ?>"><?= e($label) ?></span><strong><?= (int) $counts[$key] ?></strong></div><?php endforeach; ?></div>
</section>
<form method="get" class="filter-panel">
    <div class="filter-head"><div><h2>Queue filter</h2><p class="muted">Focus your assigned work by status or priority.</p></div><div class="filter-actions"><button class="button">Apply</button><a class="button button-secondary" href="my-work.php">Clear</a></div></div>
    <div class="filter-grid"><label>Status<select name="status"><option value="">All assigned statuses</option><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><label>Priority<select name="priority"><option value="">All priorities</option><?php foreach (TICKET_PRIORITIES as $key => $label): ?><option value="<?= e($key) ?>"<?= $priority === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label></div>
</form>
<div class="table-wrap"><table class="ticket-table"><thead><tr><?php foreach (['Status', 'Priority', 'SLA', 'Subject', 'Departments', 'Category', 'Subcategory', 'Age', 'Idle', 'Assignees', 'Employee'] as $heading): ?><th><?= e($heading) ?></th><?php endforeach; ?><th aria-label="Open ticket"></th></tr></thead><tbody><?php foreach ($tickets as $ticket): $sla = ticket_sla_state($ticket); $ticketPriority = $ticket['priority'] ?? 'normal'; ?><tr class="ticket-row-<?= e($sla[0]) ?>"><td><span class="pill pill-<?= e(str_replace('_', '-', $ticket['status'])) ?>"><?= e($statuses[$ticket['status']] ?? $ticket['status']) ?></span></td><td><span class="pill pill-priority-<?= e($ticketPriority) ?>"><?= e(TICKET_PRIORITIES[$ticketPriority] ?? ucfirst($ticketPriority)) ?></span></td><td><span class="sla-badge sla-<?= e($sla[0]) ?>"><?= e($sla[1]) ?></span><br><span class="muted"><?= e($sla[2]) ?></span></td><td class="subject"><a href="ticket.php?id=<?= (int) $ticket['id'] ?>"><?= e($ticket['subject']) ?></a><br><span class="muted"><?= e($ticket['ticket_number']) ?></span></td><td><?= e($ticket['departments']) ?></td><td><?= e($ticket['category']) ?></td><td><?= e($ticket['subcategory'] ?? '-') ?></td><td class="muted"><?= ticket_age_days($ticket, 'created_at') ?>d</td><td class="muted"><?= ticket_age_days($ticket, 'updated_at') ?>d</td><td><?= e($ticket['assignees'] ?? 'Unassigned') ?></td><td><?= e($ticket['employee_name']) ?></td><td class="row-arrow"><a href="ticket.php?id=<?= (int) $ticket['id'] ?>" aria-label="Open <?= e($ticket['ticket_number']) ?>">&rsaquo;</a></td></tr><?php endforeach; ?><?php if (!$tickets): ?><tr><td colspan="12" class="muted">No tickets are assigned to you for this view.</td></tr><?php endif; ?></tbody></table></div>
<?php page_end();
