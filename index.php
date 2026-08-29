<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';
require __DIR__ . '/app/external_tickets.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('view_all_tickets');
$statuses = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'closed' => 'Closed'];
$departments = active_departments();
$departmentIds = array_map('intval', array_column($departments, 'id'));
$filters = [
    'dashboard_range' => trim((string) ($_GET['dashboard_range'] ?? '7d')),
    'dashboard_view' => trim((string) ($_GET['dashboard_view'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'priority' => trim((string) ($_GET['priority'] ?? '')),
    'department' => trim((string) ($_GET['department'] ?? '')),
    'category' => trim((string) ($_GET['category'] ?? '')),
    'subcategory' => trim((string) ($_GET['subcategory'] ?? '')),
    'search' => trim((string) ($_GET['search'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
];
$dashboardRanges = [
    'today' => ['Today', 'Today'],
    '7d' => ['Last 7 days', '-6 days'],
    '30d' => ['Last 30 days', '-29 days'],
    '1y' => ['Last 1 year', '-1 year'],
];
$dashboardViews = [
    'total' => 'Total created',
    'open_work' => 'Open work',
    'urgent' => 'Urgent',
    'overdue' => 'Overdue',
    'idle' => 'Idle watch',
    'unassigned' => 'Unassigned',
];
if (!isset($dashboardRanges[$filters['dashboard_range']])) $filters['dashboard_range'] = '7d';
if (!isset($dashboardViews[$filters['dashboard_view']])) $filters['dashboard_view'] = '';
if (!isset($statuses[$filters['status']])) $filters['status'] = '';
if (!isset(TICKET_PRIORITIES[$filters['priority']])) $filters['priority'] = '';
if (!ctype_digit($filters['department']) || !in_array((int) $filters['department'], $departmentIds, true)) $filters['department'] = '';
if (!array_key_exists($filters['category'], TICKET_CATEGORIES)) $filters['category'] = '';
$subcategoryOptions = $filters['category'] ? TICKET_CATEGORIES[$filters['category']] : array_merge(...array_values(TICKET_CATEGORIES));
if (!in_array($filters['subcategory'], $subcategoryOptions, true)) $filters['subcategory'] = '';
foreach (['date_from', 'date_to'] as $dateKey) if ($filters[$dateKey] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$dateKey])) $filters[$dateKey] = '';

$dashboardStart = $filters['dashboard_range'] === 'today'
    ? date('Y-m-d 00:00:00')
    : date('Y-m-d 00:00:00', strtotime($dashboardRanges[$filters['dashboard_range']][1]));

$where = ['t.deleted_at IS NULL'];
$params = [];
foreach (['status', 'priority', 'category', 'subcategory'] as $key) {
    if ($filters[$key] !== '') { $where[] = 't.' . $key . ' = :' . $key; $params[$key] = $filters[$key]; }
}
if ($filters['department'] !== '') {
    $where[] = '(t.department_id = :department OR EXISTS (SELECT 1 FROM ticket_departments td_filter WHERE td_filter.ticket_id = t.id AND td_filter.department_id = :department_pivot))';
    $params['department'] = (int) $filters['department'];
    $params['department_pivot'] = (int) $filters['department'];
}
if ($filters['search'] !== '') {
    $where[] = '(t.ticket_number LIKE :ticket_search OR t.subject LIKE :subject_search OR t.employee_name LIKE :employee_search OR t.description LIKE :description_search OR EXISTS (SELECT 1 FROM ticket_comments sc WHERE sc.ticket_id=t.id AND sc.body LIKE :comment_search) OR EXISTS (SELECT 1 FROM ticket_assignees sa JOIN users su ON su.id=sa.user_id WHERE sa.ticket_id=t.id AND su.full_name LIKE :assignee_search) OR EXISTS (SELECT 1 FROM ticket_departments sd JOIN departments sdep ON sdep.id=sd.department_id WHERE sd.ticket_id=t.id AND sdep.name LIKE :department_search))';
    $search = '%' . $filters['search'] . '%';
    $params['ticket_search'] = $params['subject_search'] = $params['employee_search'] = $params['description_search'] = $params['comment_search'] = $params['assignee_search'] = $params['department_search'] = $search;
}
if ($filters['date_from'] !== '') { $where[] = 't.created_at >= :date_from'; $params['date_from'] = $filters['date_from'] . ' 00:00:00'; }
if ($filters['date_to'] !== '') { $where[] = 't.created_at < DATE_ADD(:date_to, INTERVAL 1 DAY)'; $params['date_to'] = $filters['date_to']; }
if ($filters['dashboard_view'] !== '') {
    $where[] = 't.created_at >= :dashboard_view_start';
    $params['dashboard_view_start'] = $dashboardStart;
    if ($filters['dashboard_view'] === 'open_work') $where[] = "t.status IN ('open','in_progress','pending')";
    if ($filters['dashboard_view'] === 'urgent') $where[] = 't.status <> "closed" AND t.priority = "urgent"';
    if ($filters['dashboard_view'] === 'overdue') $where[] = 't.status <> "closed" AND TIMESTAMPDIFF(DAY,t.created_at,NOW()) >= COALESCE((SELECT open_days FROM sla_rules sr WHERE sr.priority=t.priority),7)';
    if ($filters['dashboard_view'] === 'idle') $where[] = 't.status <> "closed" AND TIMESTAMPDIFF(DAY,t.updated_at,NOW()) >= COALESCE((SELECT idle_days FROM sla_rules sr WHERE sr.priority=t.priority),3) AND TIMESTAMPDIFF(DAY,t.created_at,NOW()) < COALESCE((SELECT open_days FROM sla_rules sr2 WHERE sr2.priority=t.priority),7)';
    if ($filters['dashboard_view'] === 'unassigned') $where[] = 't.assignee_id IS NULL AND NOT EXISTS (SELECT 1 FROM ticket_assignees ta_view WHERE ta_view.ticket_id = t.id)';
}
$scopeSql = ticket_scope_sql($user, $params);
$whereSql = implode(' AND ', $where) . $scopeSql;

$perPage = 15;
$requestedPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$notice = match ($_GET['notice'] ?? '') { 'ticket_deleted' => 'Ticket deleted.', 'bulk_updated' => 'Selected tickets updated.', 'bulk_empty' => 'No active tickets were selected.', default => null };

$query = db()->prepare("SELECT t.*, d.name AS department,
    COALESCE((SELECT GROUP_CONCAT(DISTINCT dep.name ORDER BY dep.name SEPARATOR ', ') FROM ticket_departments td JOIN departments dep ON dep.id = td.department_id WHERE td.ticket_id = t.id), d.name) AS departments,
    COALESCE((SELECT GROUP_CONCAT(DISTINCT u.full_name ORDER BY u.full_name SEPARATOR ', ') FROM ticket_assignees ta JOIN users u ON u.id = ta.user_id WHERE ta.ticket_id = t.id), a.full_name) AS assignees
    FROM tickets t
    JOIN departments d ON d.id = t.department_id
    LEFT JOIN users a ON a.id = t.assignee_id
    WHERE $whereSql
    ORDER BY t.updated_at DESC, t.id DESC");
foreach ($params as $key => $value) $query->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
$query->execute();
$nativeTickets = $query->fetchAll();
$externalResult = external_ticket_load_all($user);
$externalTickets = array_values(array_filter($externalResult['tickets'], static fn(array $ticket): bool => external_ticket_matches_filters($ticket, $filters, $dashboardStart)));
$tickets = array_merge($nativeTickets, $externalTickets);
usort($tickets, static function (array $left, array $right): int {
    $leftTime = strtotime((string) ($left['sort_at'] ?? $left['updated_at'] ?? $left['created_at'] ?? '')) ?: 0;
    $rightTime = strtotime((string) ($right['sort_at'] ?? $right['updated_at'] ?? $right['created_at'] ?? '')) ?: 0;
    return ($rightTime <=> $leftTime) ?: ((int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0));
});
$totalTickets = count($tickets);
$totalPages = max(1, (int) ceil($totalTickets / $perPage));
$currentPage = min($requestedPage, $totalPages);
$offset = ($currentPage - 1) * $perPage;
$pageStart = max(1, $currentPage - 2);
$pageEnd = min($totalPages, $currentPage + 2);
$tickets = array_slice($tickets, $offset, $perPage);

function ticket_filter_query(array $filters): string
{
    return http_build_query(array_filter($filters, static fn($value): bool => $value !== ''));
}
function ticket_page_url(int $page, array $filters): string
{
    $query = array_filter($filters, static fn($value): bool => $value !== '');
    $query['page'] = $page;
    return 'index.php?' . http_build_query($query);
}
function pagination_link(int $page, string $label, bool $current, array $filters): string
{
    if ($current) return '<span class="current" aria-current="page">' . e($label) . '</span>';
    return '<a href="' . e(ticket_page_url($page, $filters)) . '">' . e($label) . '</a>';
}
$exportQuery = ticket_filter_query($filters);
$exportCsv = 'export-tickets.php?format=csv' . ($exportQuery ? '&' . $exportQuery : '');
$exportXlsx = 'export-tickets.php?format=xlsx' . ($exportQuery ? '&' . $exportQuery : '');
page_start('Issues register', $user);
?>
<?php if ($notice): ?><p class="action-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($externalResult['errors']): ?><p class="external-source-notice" role="status">Some external ticket sources are temporarily unavailable. Available native and external tickets are still shown.</p><?php endif; ?>
<div class="page-head"><div><p class="eyebrow">CNG / Jamesons Ticketing System</p><h1>Issues register</h1><p class="page-subtitle"><?= (int) $totalTickets ?> active ticket<?= $totalTickets === 1 ? '' : 's' ?> in this view.</p></div><div class="page-head-actions"><?php if (user_can('export_tickets')): ?><a class="button button-secondary" href="<?= e($exportCsv) ?>">Export CSV</a><a class="button button-secondary" href="<?= e($exportXlsx) ?>">Export Excel</a><?php endif; ?><?php if (user_can('create_tickets')): ?><a class="button" href="create-ticket.php">Create ticket</a><?php endif; ?></div></div>
<form method="get" class="filter-panel">
    <input type="hidden" name="dashboard_range" value="<?= e($filters['dashboard_range']) ?>">
    <input type="hidden" name="dashboard_view" value="<?= e($filters['dashboard_view']) ?>">
    <div class="filter-head"><div><h2>Filter tickets</h2><p class="muted">Narrow the register by workflow, department, category, date, or keyword.</p></div><div class="filter-actions"><button class="button">Apply filters</button><a class="button button-secondary" href="index.php">Clear</a></div></div>
    <div class="filter-grid">
        <label>Status<select name="status"><option value="">All statuses</option><?php foreach ($statuses as $value => $label): ?><option value="<?= e($value) ?>"<?= $filters['status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label>Priority<select name="priority"><option value="">All priorities</option><?php foreach (TICKET_PRIORITIES as $value => $label): ?><option value="<?= e($value) ?>"<?= $filters['priority'] === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label>Department<select name="department"><option value="">All departments</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"<?= $filters['department'] === (string) $department['id'] ? ' selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></label>
        <label>Category<select name="category"><option value="">All categories</option><?php foreach (array_keys(TICKET_CATEGORIES) as $category): ?><option value="<?= e($category) ?>"<?= $filters['category'] === $category ? ' selected' : '' ?>><?= e($category) ?></option><?php endforeach; ?></select></label>
        <label>Subcategory<select name="subcategory"><option value="">All subcategories</option><?php foreach ($subcategoryOptions as $subcategory): ?><option value="<?= e($subcategory) ?>"<?= $filters['subcategory'] === $subcategory ? ' selected' : '' ?>><?= e($subcategory) ?></option><?php endforeach; ?></select></label>
        <label class="filter-search">Ticket, subject, or employee<input name="search" value="<?= e($filters['search']) ?>" placeholder="Search ticket, subject, or employee"></label>
        <label>Created from<input type="date" name="date_from" value="<?= e($filters['date_from']) ?>"></label>
        <label>Created to<input type="date" name="date_to" value="<?= e($filters['date_to']) ?>"></label>
    </div>
</form>
<div class="table-wrap"><table class="ticket-table"><thead><tr><?php foreach (['Status', 'Priority', 'SLA', 'Subject', 'Departments', 'Category', 'Subcategory', 'Age', 'Idle', 'Date Closed', 'Assignees', 'Employee'] as $heading): ?><th><?= e($heading) ?></th><?php endforeach; ?><th aria-label="Open ticket"></th></tr></thead><tbody><?php foreach ($tickets as $ticket): $sla = ticket_sla_state($ticket); $priority = $ticket['priority'] ?? 'normal'; $statusLabel = $ticket['status_label'] ?? ($statuses[$ticket['status']] ?? $ticket['status']); $priorityLabel = $ticket['priority_label'] ?? (TICKET_PRIORITIES[$priority] ?? ucfirst($priority)); $ticketUrl = !empty($ticket['is_external']) ? 'ticket.php?external=' . rawurlencode($ticket['external_key'] . ':' . $ticket['external_id']) : 'ticket.php?id=' . (int) $ticket['id']; ?><tr class="ticket-row-<?= e($sla[0]) ?>"><td><span class="pill pill-<?= e(str_replace('_', '-', $ticket['status'])) ?>"><?= e($statusLabel) ?></span></td><td><span class="pill pill-priority-<?= e($priority) ?>"><?= e($priorityLabel) ?></span></td><td><span class="sla-badge sla-<?= e($sla[0]) ?>"><?= e($sla[1]) ?></span><br><span class="muted"><?= e($sla[2]) ?></span></td><td class="subject"><a href="<?= e($ticketUrl) ?>"><?= e($ticket['subject']) ?></a><br><?php if (!empty($ticket['is_external'])): ?><span class="source-badge"><?= e($ticket['source']) ?></span> <?php endif; ?><span class="muted"><?= e($ticket['ticket_number']) ?></span></td><td><?= e($ticket['departments']) ?></td><td><?= e($ticket['category']) ?></td><td><?= e($ticket['subcategory'] ?? '-') ?></td><td class="muted"><?= ticket_age_days($ticket, 'created_at') ?>d</td><td class="muted"><?= !empty($ticket['is_external']) ? '—' : ticket_age_days($ticket, 'updated_at') . 'd' ?></td><td class="muted"><?= e($ticket['closed_at'] ?? '-') ?></td><td><?= e($ticket['assignees'] ?? 'Unassigned') ?></td><td><?= e($ticket['employee_name']) ?></td><td class="row-arrow"><a href="<?= e($ticketUrl) ?>" aria-label="Open <?= e($ticket['ticket_number']) ?>">&rsaquo;</a></td></tr><?php endforeach; ?><?php if (!$tickets): ?><tr><td colspan="13" class="muted">No tickets match the selected filters.</td></tr><?php endif; ?></tbody></table></div>
<?php if ($totalTickets > 0): ?><nav class="pagination" aria-label="Ticket pages"><?php if ($currentPage > 1): ?><a href="<?= e(ticket_page_url($currentPage - 1, $filters)) ?>">Previous</a><?php else: ?><span class="disabled">Previous</span><?php endif; ?><?php if ($pageStart > 1): ?><?= pagination_link(1, '1', false, $filters) ?><?php if ($pageStart > 2): ?><span class="disabled">...</span><?php endif; ?><?php endif; ?><?php for ($page = $pageStart; $page <= $pageEnd; $page++): ?><?= pagination_link($page, (string) $page, $page === $currentPage, $filters) ?><?php endfor; ?><?php if ($pageEnd < $totalPages): ?><?php if ($pageEnd < $totalPages - 1): ?><span class="disabled">...</span><?php endif; ?><?= pagination_link($totalPages, (string) $totalPages, false, $filters) ?><?php endif; ?><?php if ($currentPage < $totalPages): ?><a href="<?= e(ticket_page_url($currentPage + 1, $filters)) ?>">Next</a><?php else: ?><span class="disabled">Next</span><?php endif; ?></nav><?php endif; ?>
<?php if (user_can('bulk_ticket_actions')): ?><section><h2>Bulk actions</h2><form method="post" action="bulk-tickets.php" data-feedback data-confirm="Apply this action to the selected ticket IDs?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Ticket IDs<input name="ticket_ids[]" placeholder="Select ticket IDs manually, one at a time"></label><label>Action<select name="bulk_action"><option value="status">Set status</option><option value="priority">Set priority</option><option value="delete">Soft-delete</option></select></label><label>Status<select name="status"><option value="open">Open</option><option value="in_progress">In Progress</option><option value="pending">Pending</option><option value="closed">Closed</option></select></label><label>Priority<select name="priority"><option value="normal">Normal</option><option value="low">Low</option><option value="high">High</option><option value="urgent">Urgent</option></select></label><button class="button">Apply</button></form></section><?php endif; ?><?php page_end();
