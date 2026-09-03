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
    'dashboard_range' => trim((string) ($_GET['dashboard_range'] ?? 'all')),
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
    'all' => ['All tickets', null],
    'today' => ['Today', 'Today'],
    '7d' => ['Last 7 days', '-6 days'],
    '30d' => ['Last 30 days', '-29 days'],
    '3m' => ['Last 3 months', '-3 months'],
    '6m' => ['Last 6 months', '-6 months'],
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
if (!isset($dashboardRanges[$filters['dashboard_range']])) $filters['dashboard_range'] = 'all';
if (!isset($dashboardViews[$filters['dashboard_view']])) $filters['dashboard_view'] = '';
if (!isset($statuses[$filters['status']])) $filters['status'] = '';
if (!isset(TICKET_PRIORITIES[$filters['priority']])) $filters['priority'] = '';
if (!ctype_digit($filters['department']) || !in_array((int) $filters['department'], $departmentIds, true)) $filters['department'] = '';
if (!array_key_exists($filters['category'], TICKET_CATEGORIES)) $filters['category'] = '';
$subcategoryOptions = $filters['category'] ? TICKET_CATEGORIES[$filters['category']] : array_merge(...array_values(TICKET_CATEGORIES));
if (!in_array($filters['subcategory'], $subcategoryOptions, true)) $filters['subcategory'] = '';
foreach (['date_from', 'date_to'] as $dateKey) if ($filters[$dateKey] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$dateKey])) $filters[$dateKey] = '';

$dashboardStart = $filters['dashboard_range'] === 'all'
    ? '1970-01-01 00:00:00'
    : ($filters['dashboard_range'] === 'today'
        ? date('Y-m-d 00:00:00')
        : date('Y-m-d 00:00:00', strtotime($dashboardRanges[$filters['dashboard_range']][1])));

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
if ($filters['dashboard_range'] !== 'all') {
    $where[] = 't.created_at >= :dashboard_start';
    $params['dashboard_start'] = $dashboardStart;
}
if ($filters['dashboard_view'] !== '') {
    if ($filters['dashboard_view'] === 'open_work') $where[] = "t.status IN ('open','in_progress','pending')";
    if ($filters['dashboard_view'] === 'urgent') $where[] = 't.status <> "closed" AND t.priority = "urgent"';
    if ($filters['dashboard_view'] === 'overdue') $where[] = 't.status <> "closed" AND TIMESTAMPDIFF(DAY,t.created_at,NOW()) >= COALESCE((SELECT open_days FROM sla_rules sr WHERE sr.priority=t.priority),7)';
    if ($filters['dashboard_view'] === 'idle') $where[] = 't.status <> "closed" AND TIMESTAMPDIFF(DAY,t.updated_at,NOW()) >= COALESCE((SELECT idle_days FROM sla_rules sr WHERE sr.priority=t.priority),3) AND TIMESTAMPDIFF(DAY,t.created_at,NOW()) < COALESCE((SELECT open_days FROM sla_rules sr2 WHERE sr2.priority=t.priority),7)';
    if ($filters['dashboard_view'] === 'unassigned') $where[] = 't.assignee_id IS NULL AND NOT EXISTS (SELECT 1 FROM ticket_assignees ta_view WHERE ta_view.ticket_id = t.id)';
}
$scopeSql = ticket_scope_sql($user, $params);
$whereSql = implode(' AND ', $where) . $scopeSql;

$perPage = 10;
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
$allScopeFilters = $filters;
$allScopeFilters['dashboard_view'] = '';
$unassignedScopeFilters = $filters;
$unassignedScopeFilters['dashboard_view'] = 'unassigned';
$allScopeQuery = ticket_filter_query($allScopeFilters);
$unassignedScopeQuery = ticket_filter_query($unassignedScopeFilters);
$allScopeUrl = 'index.php' . ($allScopeQuery ? '?' . $allScopeQuery : '');
$unassignedScopeUrl = 'index.php' . ($unassignedScopeQuery ? '?' . $unassignedScopeQuery : '');
page_start('Tickets', $user);
?>
<div class="tickets-screen">
<?php if ($notice): ?><p class="action-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($externalResult['errors']): ?><p class="external-source-notice" role="status">Some external ticket sources are temporarily unavailable. Available native and external tickets are still shown.</p><?php endif; ?>
<div class="tickets-control-grid">
<form method="get" class="filter-panel ticket-filter-panel" data-filter-form data-submit-on-change="true">
    <input type="hidden" name="dashboard_range" value="<?= e($filters['dashboard_range']) ?>">
    <input type="hidden" name="dashboard_view" value="<?= e($filters['dashboard_view']) ?>">
    <div class="ticket-search-toolbar"><label class="filter-search ticket-search-field"><span class="ticket-search-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg></span><input name="search" value="<?= e($filters['search']) ?>" aria-label="Search tickets" placeholder="Search tickets..."></label><nav class="ticket-scope-control" aria-label="Ticket ownership"><a class="ticket-scope-option<?= $filters['dashboard_view'] === '' ? ' is-active' : '' ?>" href="<?= e($allScopeUrl) ?>"<?= $filters['dashboard_view'] === '' ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4zM4 12h4l2 3h4l2-3h4"/></svg>All</a><a class="ticket-scope-option" href="my-work.php"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3"/><path d="M5.5 19a6.5 6.5 0 0 1 13 0"/></svg>Mine</a><a class="ticket-scope-option<?= $filters['dashboard_view'] === 'unassigned' ? ' is-active' : '' ?>" href="<?= e($unassignedScopeUrl) ?>"<?= $filters['dashboard_view'] === 'unassigned' ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 19a6 6 0 0 1 10.5-4M16 15l5 5m0-5-5 5"/></svg>Unassigned</a></nav></div>
    <div class="filter-grid ticket-filter-grid">
        <span class="ticket-filter-row-title">Filter</span>
        <div class="ticket-filter-field"><span class="ticket-filter-label">Status</span><div class="filter-picker" data-filter-picker><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-copy"><strong data-filter-label><?= e($filters['status'] ? ($statuses[$filters['status']] ?? $filters['status']) : 'All statuses') ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Status</p><button type="button" class="filter-option<?= $filters['status'] === '' ? ' is-selected' : '' ?>" data-filter-target="status" data-filter-value="" data-filter-label="All statuses"><span>All statuses</span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php foreach ($statuses as $value => $label): ?><button type="button" class="filter-option<?= $filters['status'] === $value ? ' is-selected' : '' ?>" data-filter-target="status" data-filter-value="<?= e($value) ?>" data-filter-label="<?= e($label) ?>"><span><?= e($label) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?></div></div></div>
        <div class="ticket-filter-field"><span class="ticket-filter-label">Priority</span><div class="filter-picker" data-filter-picker><input type="hidden" name="priority" value="<?= e($filters['priority']) ?>"><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-copy"><strong data-filter-label><?= e($filters['priority'] ? (TICKET_PRIORITIES[$filters['priority']] ?? $filters['priority']) : 'All priorities') ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Priority</p><button type="button" class="filter-option<?= $filters['priority'] === '' ? ' is-selected' : '' ?>" data-filter-target="priority" data-filter-value="" data-filter-label="All priorities"><span>All priorities</span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php foreach (TICKET_PRIORITIES as $value => $label): ?><button type="button" class="filter-option<?= $filters['priority'] === $value ? ' is-selected' : '' ?>" data-filter-target="priority" data-filter-value="<?= e($value) ?>" data-filter-label="<?= e($label) ?>"><span><?= e($label) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?></div></div></div>
        <div class="ticket-filter-field"><span class="ticket-filter-label">Department</span><div class="filter-picker" data-filter-picker><input type="hidden" name="department" value="<?= e($filters['department']) ?>"><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-copy"><strong data-filter-label><?= e($filters['department'] ? 'Selected department' : 'All departments') ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Department</p><button type="button" class="filter-option<?= $filters['department'] === '' ? ' is-selected' : '' ?>" data-filter-target="department" data-filter-value="" data-filter-label="All departments"><span>All departments</span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php foreach ($departments as $department): ?><button type="button" class="filter-option<?= $filters['department'] === (string) $department['id'] ? ' is-selected' : '' ?>" data-filter-target="department" data-filter-value="<?= (int) $department['id'] ?>" data-filter-label="<?= e($department['name']) ?>"><span><?= e($department['name']) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?></div></div></div>
        <div class="ticket-filter-field"><span class="ticket-filter-label">Category</span><div class="filter-picker" data-filter-picker><input type="hidden" name="category" value="<?= e($filters['category']) ?>"><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-copy"><strong data-filter-label><?= e($filters['category'] ?: 'All categories') ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Category</p><button type="button" class="filter-option<?= $filters['category'] === '' ? ' is-selected' : '' ?>" data-filter-target="category" data-filter-value="" data-filter-label="All categories"><span>All categories</span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php foreach (array_keys(TICKET_CATEGORIES) as $category): ?><button type="button" class="filter-option<?= $filters['category'] === $category ? ' is-selected' : '' ?>" data-filter-target="category" data-filter-value="<?= e($category) ?>" data-filter-label="<?= e($category) ?>"><span><?= e($category) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?></div></div></div>
        <div class="ticket-filter-field"><span class="ticket-filter-label">Subcategory</span><div class="filter-picker" data-filter-picker><input type="hidden" name="subcategory" value="<?= e($filters['subcategory']) ?>"><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-copy"><strong data-filter-label><?= e($filters['subcategory'] ?: 'All subcategories') ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Subcategory</p><button type="button" class="filter-option<?= $filters['subcategory'] === '' ? ' is-selected' : '' ?>" data-filter-target="subcategory" data-filter-value="" data-filter-label="All subcategories"><span>All subcategories</span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php foreach ($subcategoryOptions as $subcategory): ?><button type="button" class="filter-option<?= $filters['subcategory'] === $subcategory ? ' is-selected' : '' ?>" data-filter-target="subcategory" data-filter-value="<?= e($subcategory) ?>" data-filter-label="<?= e($subcategory) ?>"><span><?= e($subcategory) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?></div></div></div>
        <div class="ticket-date-range-field" data-date-range-picker><input type="hidden" name="date_from" data-date-from value="<?= e($filters['date_from']) ?>"><input type="hidden" name="date_to" data-date-to value="<?= e($filters['date_to']) ?>"><button type="button" class="filter-picker-trigger date-range-trigger" data-date-range-trigger aria-label="Created date range" aria-expanded="false"><span class="filter-picker-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M8 3.5v3M16 3.5v3M3.5 9.5h17"/></svg></span><span class="filter-picker-copy"><strong data-date-range-label><?= e($filters['date_from'] && $filters['date_to'] ? date('M j, Y', strtotime($filters['date_from'])) . ' - ' . date('M j, Y', strtotime($filters['date_to'])) : ($filters['date_from'] ? 'From ' . date('M j, Y', strtotime($filters['date_from'])) : ($filters['date_to'] ? 'Until ' . date('M j, Y', strtotime($filters['date_to'])) : 'Any date range'))) ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu date-range-menu" data-date-range-menu hidden></div></div>
        <div class="filter-actions ticket-filter-actions" data-filter-actions hidden><a class="button button-secondary" href="index.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>Clear</a></div>
    </div>
</form>
<?php if (user_can('bulk_ticket_actions')): ?><section class="bulk-actions-panel" aria-labelledby="bulk-actions-title"><div class="bulk-actions-head"><h2 id="bulk-actions-title">Bulk actions</h2><p>Update selected tickets</p></div><form method="post" action="bulk-tickets.php" class="bulk-actions-form" data-feedback data-confirm="Apply this action to the selected ticket IDs?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label class="bulk-ticket-ids">Ticket IDs<input name="ticket_ids[]" placeholder="Enter ticket IDs, one at a time"></label><label>Action<select name="bulk_action"><option value="status">Set status</option><option value="priority">Set priority</option><option value="delete">Soft-delete</option></select></label><label>Status<select name="status"><option value="open">Open</option><option value="in_progress">In Progress</option><option value="pending">Pending</option><option value="closed">Closed</option></select></label><label>Priority<select name="priority"><option value="normal">Normal</option><option value="low">Low</option><option value="high">High</option><option value="urgent">Urgent</option></select></label><button class="button" type="submit">Apply</button></form></section><?php endif; ?>
 </div>
<div class="table-wrap"><table class="ticket-table"><thead><tr><?php foreach (['ID', 'Title', 'Requestor', 'Status', 'Priority', 'Assignee', 'Assigned Department', 'Category', 'SLA', 'Created', 'Last update'] as $heading): ?><th><?= e($heading) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($tickets as $ticket): $sla = ticket_sla_state($ticket); $priority = $ticket['priority'] ?? 'normal'; $statusLabel = $ticket['status_label'] ?? ($statuses[$ticket['status']] ?? $ticket['status']); $priorityLabel = $ticket['priority_label'] ?? (TICKET_PRIORITIES[$priority] ?? ucfirst($priority)); $ticketUrl = !empty($ticket['is_external']) ? 'ticket.php?external=' . rawurlencode($ticket['external_key'] . ':' . $ticket['external_id']) : 'ticket.php?id=' . (int) $ticket['id']; $createdAt = (string) ($ticket['created_at'] ?? ''); $updatedAt = (string) ($ticket['closed_at'] ?? $ticket['updated_at'] ?? $createdAt); ?><tr class="ticket-row-<?= e($sla[0]) ?>" data-ticket-href="<?= e($ticketUrl) ?>" tabindex="0" aria-label="Open <?= e($ticket['ticket_number']) ?>: <?= e($ticket['subject']) ?>"><td class="ticket-id"><a href="<?= e($ticketUrl) ?>"><?= e(ticket_display_id($ticket)) ?></a></td><td class="subject"><a href="<?= e($ticketUrl) ?>"><?= e($ticket['subject']) ?></a></td><td class="ticket-requestor"><?= e($ticket['employee_name'] ?? $ticket['requester'] ?? '—') ?></td><td><span class="pill pill-<?= e(str_replace('_', '-', $ticket['status'])) ?>"><?= e($statusLabel) ?></span></td><td><span class="pill pill-priority-<?= e($priority) ?>"><?= e($priorityLabel) ?></span></td><td class="ticket-assignee"><?= e($ticket['assignees'] ?? $ticket['agent'] ?? 'Unassigned') ?></td><td class="ticket-department"><?= e($ticket['departments'] ?? $ticket['department'] ?? '—') ?></td><td class="ticket-category"><?= e($ticket['category'] ?? '—') ?></td><td class="ticket-sla"><span class="sla-badge sla-<?= e($sla[0]) ?>"><?= e($sla[1]) ?></span><small><?= e($sla[2]) ?> · <?= ticket_age_days($ticket, 'created_at') ?>d open</small></td><td class="ticket-date ticket-date-inline"><?= e($createdAt ? date('M j, Y', strtotime($createdAt)) . ' · ' . date('g:i A', strtotime($createdAt)) : '—') ?></td><td class="ticket-date ticket-date-inline"><?= e($updatedAt ? date('M j, Y', strtotime($updatedAt)) . ' · ' . date('g:i A', strtotime($updatedAt)) : '—') ?></td></tr><?php endforeach; ?><?php if (!$tickets): ?><tr><td colspan="11" class="muted">No tickets match the selected filters.</td></tr><?php endif; ?></tbody></table></div>
<?php if ($totalTickets > 0): ?><nav class="pagination" aria-label="Ticket pages"><?php if ($currentPage > 1): ?><a href="<?= e(ticket_page_url($currentPage - 1, $filters)) ?>">Previous</a><?php else: ?><span class="disabled">Previous</span><?php endif; ?><?php if ($pageStart > 1): ?><?= pagination_link(1, '1', false, $filters) ?><?php if ($pageStart > 2): ?><span class="disabled">...</span><?php endif; ?><?php endif; ?><?php for ($page = $pageStart; $page <= $pageEnd; $page++): ?><?= pagination_link($page, (string) $page, $page === $currentPage, $filters) ?><?php endfor; ?><?php if ($pageEnd < $totalPages): ?><?php if ($pageEnd < $totalPages - 1): ?><span class="disabled">...</span><?php endif; ?><?= pagination_link($totalPages, (string) $totalPages, false, $filters) ?><?php endif; ?><?php if ($currentPage < $totalPages): ?><a href="<?= e(ticket_page_url($currentPage + 1, $filters)) ?>">Next</a><?php else: ?><span class="disabled">Next</span><?php endif; ?></nav><?php endif; ?>
</div><?php page_end();
