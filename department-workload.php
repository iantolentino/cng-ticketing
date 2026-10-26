<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
// This route also loads pagination directly so it works with older live bootstraps.
require_once __DIR__ . '/app/pagination.php';
require __DIR__ . '/app/tickets.php';
$internalDepartmentSql = ticket_internal_department_sql('dep');
require __DIR__ . '/app/external_tickets.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('view_all_tickets');
if (($user['role_slug'] ?? '') === 'cng-admin') { http_response_code(403); exit('You do not have permission to access this action.'); }
require_non_team_member($user);

$departments = active_internal_departments();
$departmentIds = array_map('intval', array_column($departments, 'id'));
$departmentFilterOptions = ticket_department_filter_options($departments);
$departmentFilterGroups = [];
$departmentFilterNames = [];
foreach ($departmentFilterOptions as $key => $departmentOption) {
    $departmentId = $departmentOption['department_id'];
    $departmentFilterGroups[$key] = $departmentId === null ? [] : [(int) $departmentId];
    $departmentFilterNames[$key] = (string) $departmentOption['name'];
}
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
    if (!$ticketId || !$currentDepartmentId || !valid_ids($selection, $departmentIds)) {
        $error = 'Choose valid departments for the selected ticket.';
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $previous = $pdo->prepare('SELECT department_id FROM ticket_departments WHERE ticket_id=? ORDER BY department_id');
            $previous->execute([$ticketId]);
            $before = array_map('intval', $previous->fetchAll(PDO::FETCH_COLUMN));
            sync_ticket_departments($ticketId, $selection);
            activity($ticketId, (int) $user['id'], 'departments_logged', ['before' => ['department_ids' => $before], 'after' => ['department_ids' => $selection]]);
            $pdo->commit();
            redirect('department-workload.php?notice=departments_logged');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Department changes could not be saved.';
        }
    }
}

$notice = ($_GET['notice'] ?? '') === 'departments_logged' ? 'Department log updated.' : '';
$externalResult = external_ticket_load_all($user);
$categoryOptions = ticket_category_options($externalResult['tickets']);
$statuses = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'closed' => 'Closed'];
$filters = [
    'department' => trim((string) ($_GET['department'] ?? '')),
    'category' => trim((string) ($_GET['category'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
];
if (!array_key_exists($filters['department'], $departmentFilterOptions)) $filters['department'] = '';
if (!in_array($filters['category'], $categoryOptions, true)) $filters['category'] = '';
if (!isset($statuses[$filters['status']])) $filters['status'] = '';
$hasFilters = $filters['department'] !== '' || $filters['category'] !== '' || $filters['status'] !== '';
$departmentFilterName = $filters['department'] !== '' ? $departmentFilterNames[$filters['department']] : '';
$externalDepartmentNames = $departmentFilterName !== '' ? [$departmentFilterName] : [];
// The same filters narrow both the native query and the federated external list.
$filteredExternalTickets = array_values(array_filter($externalResult['tickets'], static function (array $ticket) use ($filters, $departmentFilterName, $externalDepartmentNames, $user): bool {
    if (!external_ticket_matches_filters($ticket, [
        'status' => $filters['status'], 'priority' => '', 'department' => '', 'department_name' => '',
        'category' => $filters['category'], 'subcategory' => '', 'scope' => '',
        'search' => '', 'date_from' => '', 'date_to' => '',
        'dashboard_range' => 'all', 'dashboard_view' => '',
    ], $user)) return false;
    if ($departmentFilterName === '') return true;
    $departmentName = trim((string) ($ticket['department'] ?? ''));
    return strcasecmp($departmentName, $departmentFilterName) === 0
        || in_array($departmentName, $externalDepartmentNames, true);
}));

$scopeParams = [];
$scopeSql = ticket_scope_sql($user, $scopeParams);
$filterSql = '';
$filterParams = [];
if ($filters['department'] !== '') {
    $departmentValues = $departmentFilterGroups[$filters['department']];
    if (!$departmentValues) {
        $filterSql .= ' AND 1 = 0';
    } else {
    $currentPlaceholders = [];
    $involvedPlaceholders = [];
    foreach ($departmentValues as $index => $departmentId) {
        $currentKey = 'filter_department_current_' . $index;
        $involvedKey = 'filter_department_involved_' . $index;
        $currentPlaceholders[] = ':' . $currentKey;
        $involvedPlaceholders[] = ':' . $involvedKey;
        $filterParams[$currentKey] = $departmentId;
        $filterParams[$involvedKey] = $departmentId;
    }
    $filterSql .= ' AND (t.department_id IN (' . implode(',', $currentPlaceholders) . ') OR EXISTS (SELECT 1 FROM ticket_departments td_filter_department WHERE td_filter_department.ticket_id=t.id AND td_filter_department.department_id IN (' . implode(',', $involvedPlaceholders) . ')))';
    }
}
if ($filters['category'] !== '') { $filterSql .= ' AND LOWER(TRIM(t.category)) = LOWER(:filter_category)'; $filterParams['filter_category'] = trim($filters['category']); }
if ($filters['status'] !== '') { $filterSql .= ' AND t.status = :filter_status'; $filterParams['filter_status'] = $filters['status']; }

$summary = db()->prepare("SELECT d.id, d.name, d.code,
    COUNT(DISTINCT CASE WHEN t.department_id = d.id THEN t.id END) AS current_count,
    COUNT(DISTINCT td.ticket_id) AS involved_count,
    COUNT(DISTINCT CASE WHEN t.status <> 'closed' AND (t.department_id = d.id OR td.department_id = d.id) THEN t.id END) AS open_count,
    COUNT(DISTINCT CASE WHEN t.status <> 'closed' AND t.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) AND (t.department_id = d.id OR td.department_id = d.id) THEN t.id END) AS overdue_count
    FROM departments d
    LEFT JOIN tickets t ON t.deleted_at IS NULL AND (t.department_id = d.id OR EXISTS (SELECT 1 FROM ticket_departments td_match WHERE td_match.ticket_id = t.id AND td_match.department_id = d.id)) $scopeSql$filterSql
    LEFT JOIN ticket_departments td ON td.ticket_id = t.id AND td.department_id = d.id
    GROUP BY d.id, d.name, d.code
    ORDER BY open_count DESC, involved_count DESC, d.name");
$summary->execute($scopeParams + $filterParams);
$departmentSummary = $summary->fetchAll();
// Keep the marked QA fixture out of the user-facing summary; retain its row and
// keep the full department list available to existing ticket routing controls.
$departmentSummary = array_values(array_filter($departmentSummary, static function (array $department): bool {
    $testMarker = 'qa-codex-all-20261023';
    return stripos((string) ($department['name'] ?? ''), $testMarker) !== 0
        && stripos((string) ($department['code'] ?? ''), $testMarker) !== 0;
}));
$departmentSummaryByName = [];
$cngStaffDepartmentCodes = ['admin', 'compliance', 'customer-care', 'insurance'];
$cngStaffDepartmentIds = [];
foreach ($departmentSummary as $department) {
    $displayName = in_array((string) ($department['code'] ?? ''), $cngStaffDepartmentCodes, true)
        ? 'CNG Staff Departments'
        : ((string) ($department['code'] ?? '') === 'rm' ? 'R&M' : (string) $department['name']);
    if ($displayName === 'CNG Staff Departments') $cngStaffDepartmentIds[] = (int) $department['id'];
    if (!isset($departmentSummaryByName[$displayName])) {
        $departmentSummaryByName[$displayName] = [
            'id' => (int) $department['id'],
            'name' => $displayName,
            'current_count' => 0,
            'involved_count' => 0,
            'open_count' => 0,
            'overdue_count' => 0,
        ];
    }
    if ($displayName !== 'CNG Staff Departments') {
        foreach (['current_count', 'involved_count', 'open_count', 'overdue_count'] as $countKey) {
            $departmentSummaryByName[$displayName][$countKey] += (int) $department[$countKey];
        }
    }
}
if ($cngStaffDepartmentIds) {
    $cngDepartmentIdList = implode(',', array_map('intval', $cngStaffDepartmentIds));
    $cngTicketCondition = "(t.department_id IN ($cngDepartmentIdList) OR EXISTS (SELECT 1 FROM ticket_departments td_cng WHERE td_cng.ticket_id=t.id AND td_cng.department_id IN ($cngDepartmentIdList)))";
    $cngSummary = db()->prepare("SELECT
        COUNT(DISTINCT CASE WHEN t.department_id IN ($cngDepartmentIdList) THEN t.id END) AS current_count,
        COUNT(DISTINCT t.id) AS involved_count,
        COUNT(DISTINCT CASE WHEN t.status <> 'closed' THEN t.id END) AS open_count,
        COUNT(DISTINCT CASE WHEN t.status <> 'closed' AND t.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN t.id END) AS overdue_count
        FROM tickets t
        WHERE t.deleted_at IS NULL AND $cngTicketCondition $scopeSql$filterSql");
    $cngSummary->execute($scopeParams + $filterParams);
    $departmentSummaryByName['CNG Staff Departments'] = array_merge(
        $departmentSummaryByName['CNG Staff Departments'],
        $cngSummary->fetch()
    );
}
foreach (['IT Department', 'Finance Department', 'HR Department', 'L&D', 'R&M', 'CNG Staff Departments'] as $departmentName) {
    if (!isset($departmentSummaryByName[$departmentName])) {
        $departmentSummaryByName[$departmentName] = [
            'id' => 0,
            'name' => $departmentName,
            'current_count' => 0,
            'involved_count' => 0,
            'open_count' => 0,
            'overdue_count' => 0,
        ];
    }
}
foreach ($filteredExternalTickets as $ticket) {
    $departmentName = trim((string) ($ticket['department'] ?? '')) ?: 'External source';
    if (!isset($departmentSummaryByName[$departmentName])) {
        $departmentSummaryByName[$departmentName] = [
            'id' => 0,
            'name' => $departmentName,
            'current_count' => 0,
            'involved_count' => 0,
            'open_count' => 0,
            'overdue_count' => 0,
        ];
    }
    $departmentSummaryByName[$departmentName]['current_count']++;
    $departmentSummaryByName[$departmentName]['involved_count']++;
    if (($ticket['status'] ?? '') !== 'closed') $departmentSummaryByName[$departmentName]['open_count']++;
}
$departmentSummary = array_values($departmentSummaryByName);
usort($departmentSummary, static function (array $left, array $right): int {
    $order = ['IT Department' => 0, 'Finance Department' => 1, 'HR Department' => 2, 'L&D' => 3, 'R&M' => 4, 'CNG Staff Departments' => 5];
    return ($order[$left['name']] ?? 99) <=> ($order[$right['name']] ?? 99)
        ?: strcasecmp((string) $left['name'], (string) $right['name']);
});

$ticketParams = [];
$ticketScopeSql = ticket_scope_sql($user, $ticketParams);
$ticketQuery = db()->prepare("SELECT t.id, t.ticket_number, t.subject, t.status, t.department_id, t.created_at, t.updated_at, d.name AS current_department,
    COALESCE(GROUP_CONCAT(DISTINCT CASE WHEN {$internalDepartmentSql} THEN dep.name END ORDER BY dep.name SEPARATOR ', '), '') AS departments,
    COALESCE(GROUP_CONCAT(DISTINCT CASE WHEN {$internalDepartmentSql} THEN dep.id END ORDER BY dep.id SEPARATOR ','), '') AS department_ids,
    COALESCE((SELECT GROUP_CONCAT(DISTINCT u.full_name ORDER BY u.full_name SEPARATOR ', ') FROM ticket_assignees ta JOIN users u ON u.id = ta.user_id WHERE ta.ticket_id = t.id), a.full_name) AS assignees
    FROM tickets t
    JOIN departments d ON d.id = t.department_id
    LEFT JOIN ticket_departments td ON td.ticket_id = t.id
    LEFT JOIN departments dep ON dep.id = td.department_id
    LEFT JOIN users a ON a.id = t.assignee_id
    WHERE t.deleted_at IS NULL $ticketScopeSql$filterSql
    GROUP BY t.id, t.ticket_number, t.subject, t.status, t.department_id, t.created_at, t.updated_at, d.name, a.full_name
    ORDER BY FIELD(t.status,'open','in_progress','pending','closed'), t.updated_at DESC, t.id DESC");
$ticketQuery->execute($ticketParams + $filterParams);
$tickets = $ticketQuery->fetchAll();
$tickets = array_merge($tickets, $filteredExternalTickets);
usort($tickets, static function (array $left, array $right): int {
    $leftTime = strtotime((string) ($left['sort_at'] ?? $left['updated_at'] ?? $left['created_at'] ?? '')) ?: 0;
    $rightTime = strtotime((string) ($right['sort_at'] ?? $right['updated_at'] ?? $right['created_at'] ?? '')) ?: 0;
    return ($rightTime <=> $leftTime) ?: ((int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0));
});
$perPage = 20;
$pagination = pagination_state(count($tickets), $perPage, pagination_requested_page());
$tickets = array_slice($tickets, $pagination['offset'], $pagination['perPage']);

function department_ticket_url(array $ticket): string
{
    if (!empty($ticket['is_external'])) return 'ticket.php?external=' . rawurlencode((string) $ticket['external_key'] . ':' . (int) $ticket['external_id']);
    return 'ticket.php?id=' . (int) ($ticket['id'] ?? 0);
}

page_start('Departments', $user);
?>
<div class="departments-screen">
    <?php if ($notice): ?><p class="action-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="auth-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <section class="department-summary-panel" aria-labelledby="department-summary-title">
        <div class="department-section-head"><div><h2 id="department-summary-title">Department workload</h2><p>Open means not closed. Involved and current totals include closed tickets; overdue means a non-closed ticket created over 7 days ago.</p></div><span class="department-section-count"><?= count($departmentSummary) ?> departments</span></div>
        <div class="department-metric-grid">
            <?php foreach ($departmentSummary as $department): ?>
                <article class="department-metric-card<?= (int) $department['overdue_count'] > 0 ? ' is-alert' : '' ?>">
                    <span class="department-metric-name"><?= e($department['name']) ?></span>
                    <strong><?= (int) $department['open_count'] ?></strong>
                    <small>Open tickets</small>
                    <div class="department-metric-meta"><span><b><?= (int) $department['involved_count'] ?></b> involved</span><span><b><?= (int) $department['current_count'] ?></b> current</span><span class="<?= (int) $department['overdue_count'] > 0 ? 'is-overdue' : '' ?>"><b><?= (int) $department['overdue_count'] ?></b> overdue</span></div>
                </article>
            <?php endforeach; ?>
            <?php if (!$departmentSummary): ?><p class="muted department-empty-state">No departments are available.</p><?php endif; ?>
        </div>
    </section>
    <section class="department-ticket-panel" aria-labelledby="department-ticket-title">
        <div class="department-section-head"><div><h2 id="department-ticket-title">Ticket involvement</h2><p>Review routing and update supporting departments.</p></div><span class="department-section-count"><?= (int) $pagination['total'] ?> tickets<?= $pagination['totalPages'] > 1 ? ' · page ' . (int) $pagination['current'] . ' of ' . (int) $pagination['totalPages'] : '' ?></span></div>
        <form method="get" class="department-filter-bar" data-filter-form>
            <div class="department-filter-field"><span class="ticket-filter-label">Department involved</span><div class="filter-picker" data-filter-picker><input type="hidden" name="department" value="<?= e($filters['department']) ?>"><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-copy"><strong data-filter-label><?= e($departmentFilterName ?: 'All departments') ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Department involved</p><button type="button" class="filter-option<?= $filters['department'] === '' ? ' is-selected' : '' ?>" data-filter-target="department" data-filter-value="" data-filter-label="All departments"><span>All departments</span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php foreach ($departmentFilterOptions as $departmentOptionId => $departmentOption): $departmentOptionId = (string) $departmentOptionId; $departmentOptionName = (string) $departmentOption['name']; ?><button type="button" class="filter-option<?= $filters['department'] === (string) $departmentOptionId ? ' is-selected' : '' ?>" data-filter-target="department" data-filter-value="<?= e($departmentOptionId) ?>" data-filter-label="<?= e($departmentOptionName) ?>"><span><?= e($departmentOptionName) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?></div></div></div>
            <div class="department-filter-field"><span class="ticket-filter-label">Category</span><div class="filter-picker" data-filter-picker><input type="hidden" name="category" value="<?= e($filters['category']) ?>"><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-copy"><strong data-filter-label><?= e($filters['category'] ?: 'All categories') ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Category</p><button type="button" class="filter-option<?= $filters['category'] === '' ? ' is-selected' : '' ?>" data-filter-target="category" data-filter-value="" data-filter-label="All categories"><span>All categories</span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php foreach ($categoryOptions as $category): ?><button type="button" class="filter-option<?= $filters['category'] === $category ? ' is-selected' : '' ?>" data-filter-target="category" data-filter-value="<?= e($category) ?>" data-filter-label="<?= e($category) ?>"><span><?= e($category) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?></div></div></div>
            <div class="department-filter-field"><span class="ticket-filter-label">Status</span><div class="filter-picker" data-filter-picker><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-copy"><strong data-filter-label><?= e($filters['status'] ? ($statuses[$filters['status']] ?? $filters['status']) : 'All statuses') ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Status</p><button type="button" class="filter-option<?= $filters['status'] === '' ? ' is-selected' : '' ?>" data-filter-target="status" data-filter-value="" data-filter-label="All statuses"><span>All statuses</span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php foreach ($statuses as $value => $label): ?><button type="button" class="filter-option<?= $filters['status'] === $value ? ' is-selected' : '' ?>" data-filter-target="status" data-filter-value="<?= e($value) ?>" data-filter-label="<?= e($label) ?>"><span><?= e($label) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?></div></div></div>
            <?php if ($hasFilters): ?><a class="button button-secondary department-filter-clear" href="department-workload.php">Clear</a><?php endif; ?>
        </form>
        <div class="table-wrap department-table-wrap"><table class="ticket-table department-table"><thead><tr><?php foreach (['ID', 'Title', 'Status', 'Role', 'Departments involved', 'Assignees', 'Manage'] as $heading): ?><th><?= e($heading) ?></th><?php endforeach; ?></tr></thead><tbody>
        <?php foreach ($tickets as $ticket): $isExternal = !empty($ticket['is_external']); $ticketUrl = department_ticket_url($ticket); $currentDepartment = (string) ($ticket['department'] ?? $ticket['current_department'] ?? '-'); $ticket['current_department'] = $currentDepartment; $ticket['department_ids'] = $ticket['department_ids'] ?? ''; $candidateIds = array_values(array_unique(array_map('intval', array_filter(explode(',', (string) $ticket['department_ids']))))); $selected = array_values(array_intersect($candidateIds, $departmentIds)); $involvedNames = array_filter(array_map('trim', explode(',', (string) ($ticket['departments'] ?? '')))); $statusKey = (string) ($ticket['status'] ?? 'external'); $statusLabel = (string) ($ticket['status_label'] ?? ucwords(str_replace('_', ' ', $statusKey))); ?>
            <tr <?= ticket_row_navigation_attributes($ticketUrl) ?> aria-label="Open <?= e($ticket['ticket_number']) ?>: <?= e($ticket['subject']) ?>">
                <td class="ticket-id"><a <?= ticket_link_attributes($ticketUrl) ?>><?= e(ticket_display_id($ticket)) ?></a></td>
                <td class="subject"><a <?= ticket_link_attributes($ticketUrl) ?>><?= e($ticket['subject']) ?></a><span class="ticket-row-meta"><?= e($ticket['current_department']) ?> &middot; <?= e($ticket['assignees'] ?? $ticket['agent'] ?? 'Unassigned') ?></span></td>
                <td><span class="pill pill-<?= e(str_replace('_', '-', $statusKey)) ?>"><?= e($statusLabel) ?></span></td>
                <td><span class="department-primary-chip"><?= e($ticket['current_department']) ?></span></td>
                <td><div class="department-chip-list"><?php if ($involvedNames): foreach ($involvedNames as $departmentName): ?><span><?= e($departmentName) ?></span><?php endforeach; else: ?><span class="muted">—</span><?php endif; ?></div></td>
                <td class="department-assignee"><?= e($ticket['assignees'] ?? 'Unassigned') ?></td>
                <td class="department-manage-cell">
<?php if (!$isExternal): ?>
                    <form method="post" class="department-log-form" data-department-form>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
                        <span data-department-inputs><?php foreach ($selected as $departmentId): ?><input type="hidden" name="department_ids[]" value="<?= (int) $departmentId ?>"><?php endforeach; ?></span>
                        <div class="department-picker" data-department-picker data-current-department="<?= (int) $ticket['department_id'] ?>">
                            <button type="button" class="department-picker-trigger" data-department-trigger aria-expanded="false" aria-haspopup="dialog"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.75 19.25V8.75h6.5v10.5M12.75 19.25V4.75h6.5v14.5M3.75 19.25h16.5"/></svg><span data-department-trigger-label><?= count($selected) ?> selected</span><svg class="department-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button>
                            <div class="department-picker-menu" data-department-menu role="dialog" aria-label="Departments for <?= e($ticket['ticket_number']) ?>" hidden>
                                <div class="department-picker-head"><div><strong>Manage departments</strong><small><?= e($ticket['ticket_number']) ?></small></div><button type="button" class="department-picker-close" data-department-close aria-label="Close department selector"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>
                                <div class="department-option-list" role="group" aria-label="Available departments">
                                    <?php foreach ($departments as $department): $departmentId = (int) $department['id']; $isSelected = in_array($departmentId, $selected, true); ?><button type="button" class="department-option<?= $isSelected ? ' is-selected' : '' ?>" data-department-option data-department-id="<?= $departmentId ?>" data-department-name="<?= e($department['name']) ?>" aria-pressed="<?= $isSelected ? 'true' : 'false' ?>"><span class="department-option-check" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m6 12 4 4 8-8"/></svg></span><span><strong><?= e($department['name']) ?></strong></span></button><?php endforeach; ?>
                                </div>
                                <div class="department-picker-foot"><span data-department-selection-summary aria-live="polite"><?= count($selected) ?> selected</span><button class="button department-save-button" data-department-save>Save changes</button></div>
                            </div>
                        </div>
                    </form>
                    <?php else: ?><span class="muted department-external-note">External source &middot; Read-only</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$tickets): ?><tr><td colspan="7" class="muted department-empty-row">No tickets are available for department logging.</td></tr><?php endif; ?>
        </tbody></table></div>
        <?= pagination_nav('department-workload.php', $filters, $pagination, 'Department ticket pages') ?>
    </section>
</div>
<?php page_end();
