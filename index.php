<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';
$internalDepartmentSql = ticket_internal_department_sql('dep');
require __DIR__ . '/app/external_tickets.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('view_all_tickets');
$canBulkActions = user_can('bulk_ticket_actions');
$statuses = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'closed' => 'Closed'];
$departments = active_internal_departments();
$departmentFilterOptions = ticket_department_filter_options($departments);
// External sources are read once per request: the requestor filter and the row list share the result.
$externalResult = external_ticket_load_all($user);
$allExternalTickets = $externalResult['tickets'];
$categoryOptions = ticket_category_options($allExternalTickets);
$filters = [
    'dashboard_range' => trim((string) ($_GET['dashboard_range'] ?? 'all')),
    'dashboard_view' => trim((string) ($_GET['dashboard_view'] ?? '')),
    'dashboard_from' => trim((string) ($_GET['dashboard_from'] ?? '')),
    'dashboard_to' => trim((string) ($_GET['dashboard_to'] ?? '')),
    'scope' => trim((string) ($_GET['scope'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'priority' => trim((string) ($_GET['priority'] ?? '')),
    'department' => trim((string) ($_GET['department'] ?? '')),
    'category' => trim((string) ($_GET['category'] ?? '')),
    'requestor' => trim((string) ($_GET['requestor'] ?? '')),
    'search' => trim((string) ($_GET['search'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
];
$dashboardViews = [
    'total' => 'Total created',
    'open_work' => 'Open work',
    'urgent' => 'Urgent',
    'overdue' => 'Overdue',
    'idle' => 'Idle watch',
    'unassigned' => 'Unassigned',
];
if (!isset($dashboardViews[$filters['dashboard_view']])) $filters['dashboard_view'] = '';
if (!in_array($filters['scope'], ['', 'mine'], true)) $filters['scope'] = '';
if (!isset($statuses[$filters['status']])) $filters['status'] = '';
if (!isset(TICKET_PRIORITIES[$filters['priority']])) $filters['priority'] = '';
if (!array_key_exists($filters['department'], $departmentFilterOptions)) $filters['department'] = '';
if (!in_array($filters['category'], $categoryOptions, true)) $filters['category'] = '';
$filters['department_name'] = $departmentFilterOptions[$filters['department']]['name'] ?? '';
$requestorOptions = ticket_requestor_options($user, $allExternalTickets);
if ($filters['requestor'] !== '' && !in_array($filters['requestor'], $requestorOptions, true)) $filters['requestor'] = '';
foreach (['date_from', 'date_to'] as $dateKey) if ($filters[$dateKey] && !ticket_valid_date($filters[$dateKey])) $filters[$dateKey] = '';

$dashboardWindow = ticket_window_bounds($filters['dashboard_from'], $filters['dashboard_to'], $filters['dashboard_range']);
$filters['dashboard_from'] = $dashboardWindow['from'];
$filters['dashboard_to'] = $dashboardWindow['to'];
$filters['dashboard_range'] = $dashboardWindow['preset'];
$dateWindow = ticket_window_bounds($filters['date_from'], $filters['date_to'], 'all');

$where = ['t.deleted_at IS NULL'];
$params = [];
foreach (['status', 'priority'] as $key) {
    if ($filters[$key] !== '') { $where[] = 't.' . $key . ' = :' . $key; $params[$key] = $filters[$key]; }
}
if ($filters['category'] !== '') { $where[] = 'LOWER(TRIM(t.category)) = LOWER(:category)'; $params['category'] = trim($filters['category']); }
if ($filters['department'] !== '') {
    $departmentId = $departmentFilterOptions[$filters['department']]['department_id'];
    if ($departmentId === null) {
        $where[] = '1 = 0';
    } else {
        $where[] = '(t.department_id = :department OR EXISTS (SELECT 1 FROM ticket_departments td_filter WHERE td_filter.ticket_id = t.id AND td_filter.department_id = :department_pivot))';
        $params['department'] = $departmentId;
        $params['department_pivot'] = $departmentId;
    }
}
if ($filters['search'] !== '') {
    $where[] = '(t.ticket_number LIKE :ticket_search OR t.subject LIKE :subject_search OR creator.full_name LIKE :requestor_search OR t.employee_name LIKE :employee_search OR t.description LIKE :description_search OR EXISTS (SELECT 1 FROM ticket_comments sc WHERE sc.ticket_id=t.id AND sc.body LIKE :comment_search) OR EXISTS (SELECT 1 FROM ticket_assignees sa JOIN users su ON su.id=sa.user_id WHERE sa.ticket_id=t.id AND su.full_name LIKE :assignee_search) OR EXISTS (SELECT 1 FROM ticket_departments sd JOIN departments sdep ON sdep.id=sd.department_id WHERE sd.ticket_id=t.id AND sdep.name LIKE :department_search))';
    $search = '%' . $filters['search'] . '%';
    $params['requestor_search'] = $params['ticket_search'] = $params['subject_search'] = $params['employee_search'] = $params['description_search'] = $params['comment_search'] = $params['assignee_search'] = $params['department_search'] = $search;
}
if ($filters['requestor'] !== '') { $where[] = 'creator.full_name = :requestor'; $params['requestor'] = $filters['requestor']; }
$where = array_merge($where, ticket_window_sql($dateWindow, $params, 't.created_at', 'date_window'));
$where = array_merge($where, ticket_window_sql($dashboardWindow, $params));
if ($filters['dashboard_view'] !== '') {
    if ($filters['dashboard_view'] === 'open_work') $where[] = "t.status IN ('open','in_progress','pending')";
    if ($filters['dashboard_view'] === 'urgent') $where[] = 't.status <> "closed" AND t.priority = "urgent"';
    if ($filters['dashboard_view'] === 'overdue') $where[] = 't.status <> "closed" AND TIMESTAMPDIFF(DAY,t.created_at,NOW()) >= COALESCE((SELECT open_days FROM sla_rules sr WHERE sr.priority=t.priority),7)';
    if ($filters['dashboard_view'] === 'idle') $where[] = 't.status <> "closed" AND TIMESTAMPDIFF(DAY,t.updated_at,NOW()) >= COALESCE((SELECT idle_days FROM sla_rules sr WHERE sr.priority=t.priority),3) AND TIMESTAMPDIFF(DAY,t.created_at,NOW()) < COALESCE((SELECT open_days FROM sla_rules sr2 WHERE sr2.priority=t.priority),7)';
    if ($filters['dashboard_view'] === 'unassigned') $where[] = 't.assignee_id IS NULL AND NOT EXISTS (SELECT 1 FROM ticket_assignees ta_view WHERE ta_view.ticket_id = t.id)';
}
$scopeSql = ticket_scope_sql($user, $params, $filters['scope']);
$whereSql = implode(' AND ', $where) . $scopeSql;

$perPage = 10;
$notice = match ($_GET['notice'] ?? '') { 'ticket_deleted' => 'Ticket deleted.', 'bulk_updated' => 'Selected tickets updated.', 'bulk_empty' => 'No active tickets were selected.', default => null };

$query = db()->prepare("SELECT t.*, creator.full_name AS creator_name, d.name AS department,
    COALESCE((SELECT GROUP_CONCAT(DISTINCT dep.name ORDER BY dep.name SEPARATOR ', ') FROM ticket_departments td JOIN departments dep ON dep.id = td.department_id AND {$internalDepartmentSql} WHERE td.ticket_id = t.id), d.name) AS departments,
    COALESCE((SELECT GROUP_CONCAT(DISTINCT u.full_name ORDER BY u.full_name SEPARATOR ', ') FROM ticket_assignees ta JOIN users u ON u.id = ta.user_id WHERE ta.ticket_id = t.id), a.full_name) AS assignees
    FROM tickets t
    JOIN departments d ON d.id = t.department_id
    LEFT JOIN users a ON a.id = t.assignee_id LEFT JOIN users creator ON creator.id = t.created_by
    WHERE $whereSql
    ORDER BY t.updated_at DESC, t.id DESC");
foreach ($params as $key => $value) $query->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
$query->execute();
$nativeTickets = $query->fetchAll();
$externalTickets = array_values(array_filter($allExternalTickets, static fn(array $ticket): bool => external_ticket_matches_filters($ticket, $filters, $user)));
$tickets = array_merge($nativeTickets, $externalTickets);
usort($tickets, static function (array $left, array $right): int {
    $leftTime = strtotime((string) ($left['sort_at'] ?? $left['updated_at'] ?? $left['created_at'] ?? '')) ?: 0;
    $rightTime = strtotime((string) ($right['sort_at'] ?? $right['updated_at'] ?? $right['created_at'] ?? '')) ?: 0;
    return ($rightTime <=> $leftTime) ?: ((int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0));
});
$totalTickets = count($tickets);
$pagination = pagination_state($totalTickets, $perPage, pagination_requested_page());
$tickets = array_slice($tickets, $pagination['offset'], $pagination['perPage']);

function ticket_filter_query(array $filters): string
{
    return http_build_query(array_filter($filters, static fn($value): bool => $value !== ''));
}
$allScopeFilters = $filters;
$allScopeFilters['scope'] = '';
$allScopeFilters['dashboard_view'] = '';
$mineScopeFilters = $filters;
$mineScopeFilters['scope'] = 'mine';
$mineScopeFilters['dashboard_view'] = '';
$unassignedScopeFilters = $filters;
$unassignedScopeFilters['scope'] = '';
$unassignedScopeFilters['dashboard_view'] = 'unassigned';
$allScopeQuery = ticket_filter_query($allScopeFilters);
$mineScopeQuery = ticket_filter_query($mineScopeFilters);
$unassignedScopeQuery = ticket_filter_query($unassignedScopeFilters);
$allScopeUrl = 'index.php' . ($allScopeQuery ? '?' . $allScopeQuery : '');
$mineScopeUrl = 'index.php' . ($mineScopeQuery ? '?' . $mineScopeQuery : '');
$unassignedScopeUrl = 'index.php' . ($unassignedScopeQuery ? '?' . $unassignedScopeQuery : '');
page_start('Tickets', $user);
?>
<div class="tickets-screen">
<?php if ($notice): ?><p class="action-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
<div class="tickets-control-grid">
<form method="get" class="filter-panel ticket-filter-panel" data-filter-form data-submit-on-change="true">
    <input type="hidden" name="dashboard_range" value="<?= e($filters['dashboard_range']) ?>">
    <input type="hidden" name="dashboard_view" value="<?= e($filters['dashboard_view']) ?>">
    <input type="hidden" name="dashboard_from" value="<?= e($filters['dashboard_from']) ?>">
    <input type="hidden" name="dashboard_to" value="<?= e($filters['dashboard_to']) ?>">
    <input type="hidden" name="scope" value="<?= e($filters['scope']) ?>">
    <div class="ticket-search-toolbar"><label class="filter-search ticket-search-field"><span class="ticket-search-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg></span><input name="search" value="<?= e($filters['search']) ?>" aria-label="Search tickets" placeholder="Search tickets..."></label><nav class="ticket-scope-control" aria-label="Ticket ownership"><a class="ticket-scope-option<?= $filters['scope'] === '' && $filters['dashboard_view'] === '' ? ' is-active' : '' ?>" href="<?= e($allScopeUrl) ?>"<?= $filters['scope'] === '' && $filters['dashboard_view'] === '' ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4zM4 12h4l2 3h4l2-3h4"/></svg>All</a><a class="ticket-scope-option<?= $filters['scope'] === 'mine' ? ' is-active' : '' ?>" href="<?= e($mineScopeUrl) ?>"<?= $filters['scope'] === 'mine' ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3"/><path d="M5.5 19a6.5 6.5 0 0 1 13 0"/></svg>Mine</a><a class="ticket-scope-option<?= $filters['scope'] === '' && $filters['dashboard_view'] === 'unassigned' ? ' is-active' : '' ?>" href="<?= e($unassignedScopeUrl) ?>"<?= $filters['scope'] === '' && $filters['dashboard_view'] === 'unassigned' ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 19a6 6 0 0 1 10.5-4M16 15l5 5m0-5-5 5"/></svg>Unassigned</a></nav></div>
    <div class="filter-grid ticket-filter-grid">
        <span class="ticket-filter-row-title">Filter</span>
        <div class="ticket-filter-field"><span class="ticket-filter-label">Status</span><div class="filter-picker" data-filter-picker><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-copy"><strong data-filter-label><?= e($filters['status'] ? ($statuses[$filters['status']] ?? $filters['status']) : 'All statuses') ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Status</p><button type="button" class="filter-option<?= $filters['status'] === '' ? ' is-selected' : '' ?>" data-filter-target="status" data-filter-value="" data-filter-label="All statuses"><span>All statuses</span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php foreach ($statuses as $value => $label): ?><button type="button" class="filter-option<?= $filters['status'] === $value ? ' is-selected' : '' ?>" data-filter-target="status" data-filter-value="<?= e($value) ?>" data-filter-label="<?= e($label) ?>"><span><?= e($label) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?></div></div></div>
        <div class="ticket-filter-field"><span class="ticket-filter-label">Priority</span><div class="filter-picker" data-filter-picker><input type="hidden" name="priority" value="<?= e($filters['priority']) ?>"><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-copy"><strong data-filter-label><?= e($filters['priority'] ? (TICKET_PRIORITIES[$filters['priority']] ?? $filters['priority']) : 'All priorities') ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Priority</p><button type="button" class="filter-option<?= $filters['priority'] === '' ? ' is-selected' : '' ?>" data-filter-target="priority" data-filter-value="" data-filter-label="All priorities"><span>All priorities</span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php foreach (TICKET_PRIORITIES as $value => $label): ?><button type="button" class="filter-option<?= $filters['priority'] === $value ? ' is-selected' : '' ?>" data-filter-target="priority" data-filter-value="<?= e($value) ?>" data-filter-label="<?= e($label) ?>"><span><?= e($label) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?></div></div></div>
        <div class="ticket-filter-field"><span class="ticket-filter-label">Department</span><div class="filter-picker" data-filter-picker><input type="hidden" name="department" value="<?= e($filters['department']) ?>"><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-copy"><strong data-filter-label><?= e($filters['department'] ? ($departmentFilterOptions[$filters['department']]['name'] ?? 'All departments') : 'All departments') ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Department</p><button type="button" class="filter-option<?= $filters['department'] === '' ? ' is-selected' : '' ?>" data-filter-target="department" data-filter-value="" data-filter-label="All departments"><span>All departments</span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php foreach ($departmentFilterOptions as $departmentKey => $departmentOption): ?><button type="button" class="filter-option<?= $filters['department'] === $departmentKey ? ' is-selected' : '' ?>" data-filter-target="department" data-filter-value="<?= e($departmentKey) ?>" data-filter-label="<?= e($departmentOption['name']) ?>"><span><?= e($departmentOption['name']) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?></div></div></div>
        <div class="ticket-filter-field"><span class="ticket-filter-label">Requestor</span><div class="filter-picker" data-filter-picker><input type="hidden" name="requestor" value="<?= e($filters['requestor']) ?>"><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-copy"><strong data-filter-label><?= e($filters['requestor'] ?: 'All requestors') ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Requestor</p><button type="button" class="filter-option<?= $filters['requestor'] === '' ? ' is-selected' : '' ?>" data-filter-target="requestor" data-filter-value="" data-filter-label="All requestors"><span>All requestors</span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php foreach ($requestorOptions as $requestor): ?><button type="button" class="filter-option<?= $filters['requestor'] === $requestor ? ' is-selected' : '' ?>" data-filter-target="requestor" data-filter-value="<?= e($requestor) ?>" data-filter-label="<?= e($requestor) ?>"><span><?= e($requestor) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?><?php if (!$requestorOptions): ?><p class="filter-menu-empty">No requestors yet.</p><?php endif; ?></div></div></div>
        <div class="ticket-filter-field"><span class="ticket-filter-label">Category</span><div class="filter-picker" data-filter-picker><input type="hidden" name="category" value="<?= e($filters['category']) ?>"><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-copy"><strong data-filter-label><?= e($filters['category'] ?: 'All categories') ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Category</p><button type="button" class="filter-option<?= $filters['category'] === '' ? ' is-selected' : '' ?>" data-filter-target="category" data-filter-value="" data-filter-label="All categories"><span>All categories</span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php foreach ($categoryOptions as $category): ?><button type="button" class="filter-option<?= $filters['category'] === $category ? ' is-selected' : '' ?>" data-filter-target="category" data-filter-value="<?= e($category) ?>" data-filter-label="<?= e($category) ?>"><span><?= e($category) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?></div></div></div>
        <div class="ticket-date-range-field" data-date-range-picker><input type="hidden" name="date_from" data-date-from value="<?= e($filters['date_from']) ?>"><input type="hidden" name="date_to" data-date-to value="<?= e($filters['date_to']) ?>"><button type="button" class="filter-picker-trigger date-range-trigger" data-date-range-trigger aria-label="Created date range" aria-expanded="false"><span class="filter-picker-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M8 3.5v3M16 3.5v3M3.5 9.5h17"/></svg></span><span class="filter-picker-copy"><strong data-date-range-label>Date Range</strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu date-range-menu" data-date-range-menu hidden></div></div>
        <div class="filter-actions ticket-filter-actions" data-filter-actions hidden><a class="button button-secondary" href="index.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>Clear</a></div>
    </div>
</form>
<?php if ($canBulkActions): ?>
<details class="bulk-actions-menu" data-bulk-actions>
    <summary class="bulk-actions-trigger"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v.01M12 12v.01M12 19v.01"/></svg><span>Bulk actions</span></summary>
    <section class="bulk-actions-panel" aria-labelledby="bulk-actions-title"><div class="bulk-actions-head"><h2 id="bulk-actions-title">Bulk actions</h2><div class="bulk-actions-head-tools"><p data-bulk-selection-count aria-live="polite">0 selected</p><button class="bulk-selection-button" type="button" data-bulk-select-visible>Select visible</button><button class="bulk-selection-button" type="button" data-bulk-clear-selection>Clear</button></div></div><form method="post" action="bulk-tickets.php" class="bulk-actions-form" data-bulk-form data-feedback data-confirm="Apply this action to the selected ticket IDs?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label class="bulk-ticket-ids">Ticket ID<input name="ticket_ids[]" placeholder="Optional ID (e.g. 13)"></label><label>Action<select name="bulk_action"><option value="status">Set status</option><option value="priority">Set priority</option><option value="delete">Soft-delete</option></select></label><label>Status<select name="status"><option value="open">Open</option><option value="in_progress">In Progress</option><option value="pending">Pending</option><option value="closed">Closed</option></select></label><label>Priority<select name="priority"><option value="normal">Normal</option><option value="low">Low</option><option value="high">High</option><option value="urgent">Urgent</option></select></label><div data-bulk-selected-inputs hidden></div><button class="button" type="submit">Apply</button></form></section>
</details>
<?php endif; ?>
 </div>
<div class="table-wrap"><table class="ticket-table"><thead><tr><?php foreach (['ID', 'Title', 'Requestor', 'Status', 'Priority', 'Assignee', 'Assigned Department', 'Category', 'SLA', 'Created', 'Last update'] as $heading): ?><th><?= e($heading) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($tickets as $ticket): $sla = ticket_sla_state($ticket); $priority = $ticket['priority'] ?? 'normal'; $statusLabel = $ticket['status_label'] ?? ($statuses[$ticket['status']] ?? $ticket['status']); $priorityLabel = $ticket['priority_label'] ?? (TICKET_PRIORITIES[$priority] ?? ucfirst($priority)); $ticketUrl = !empty($ticket['is_external']) ? 'ticket.php?external=' . rawurlencode($ticket['external_key'] . ':' . $ticket['external_id']) : 'ticket.php?id=' . (int) $ticket['id']; $createdAt = (string) ($ticket['created_at'] ?? ''); $updatedAt = (string) ($ticket['closed_at'] ?? $ticket['updated_at'] ?? $createdAt); ?><tr class="ticket-row-<?= e($sla[0]) ?>" <?= ticket_row_navigation_attributes($ticketUrl) ?> aria-label="Open <?= e($ticket['ticket_number']) ?>: <?= e($ticket['subject']) ?>"><td class="ticket-id"><?php if ($canBulkActions && empty($ticket['is_external']) && ticket_access_allowed((int) $ticket['id'])): ?><input class="bulk-ticket-checkbox" type="checkbox" data-bulk-ticket-checkbox value="<?= (int) $ticket['id'] ?>" aria-label="Select <?= e($ticket['ticket_number']) ?>"><?php endif; ?><a <?= ticket_link_attributes($ticketUrl) ?>><?= e(ticket_display_id($ticket)) ?></a></td><td class="subject"><a <?= ticket_link_attributes($ticketUrl) ?>><?= e($ticket['subject']) ?></a></td><td class="ticket-requestor"><?= e(ticket_requestor_name($ticket)) ?></td><td><span class="pill pill-<?= e(str_replace('_', '-', $ticket['status'])) ?>"><?= e($statusLabel) ?></span></td><td><span class="pill pill-priority-<?= e($priority) ?>"><?= e($priorityLabel) ?></span></td><td class="ticket-assignee"><?= e($ticket['assignees'] ?? $ticket['agent'] ?? 'Unassigned') ?></td><td class="ticket-department"><?= e($ticket['departments'] ?? $ticket['department'] ?? '—') ?></td><td class="ticket-category"><?= e($ticket['category'] ?? '—') ?></td><td class="ticket-sla"><span class="sla-badge sla-<?= e($sla[0]) ?>"><?= e($sla[1]) ?></span><small><?= e($sla[2]) ?> · <?= ticket_age_days($ticket, 'created_at') ?>d open</small></td><td class="ticket-date ticket-date-inline"><?= e($createdAt ? app_datetime_display($createdAt) : '—') ?></td><td class="ticket-date ticket-date-inline"><?= e($updatedAt ? app_datetime_display($updatedAt) : '—') ?></td></tr><?php endforeach; ?><?php if (!$tickets): ?><tr><td colspan="11" class="muted">No tickets match the selected filters.</td></tr><?php endif; ?></tbody></table></div>
<?= pagination_nav('index.php', $filters, $pagination, 'Ticket pages') ?>
</div><?php page_end();
