<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('view_all_tickets');
if (($user['role_slug'] ?? '') === 'cng-admin') { http_response_code(403); exit('You do not have permission to access this action.'); }
require_non_team_member($user);

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
<div class="my-work-screen">
<section class="my-work-summary-panel" aria-label="Assigned ticket summary">
    <div class="my-work-metric-grid">
        <article class="my-work-metric-card"><span>Open work</span><strong><?= (int) $openWork ?></strong></article>
        <article class="my-work-metric-card is-alert"><span>Urgent</span><strong><?= (int) $urgentCount ?></strong></article>
        <article class="my-work-metric-card is-alert"><span>Overdue</span><strong><?= (int) $overdueCount ?></strong></article>
        <article class="my-work-metric-card"><span>Idle watch</span><strong><?= (int) $idleCount ?></strong></article>
        <article class="my-work-metric-card"><span>Closed</span><strong><?= (int) $counts['closed'] ?></strong></article>
        <article class="my-work-metric-card"><span>Total assigned</span><strong><?= array_sum($counts) ?></strong></article>
    </div>
</section>
<form method="get" class="filter-panel my-work-filter-panel" data-filter-form data-submit-on-change="true">
    <span class="my-work-filter-title">Filter</span>
    <div class="ticket-filter-field"><div class="filter-picker" data-filter-picker><input type="hidden" name="status" value="<?= e($status) ?>"><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-copy"><strong data-filter-label><?= e($status ? ($statuses[$status] ?? $status) : 'All statuses') ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Status</p><button type="button" class="filter-option<?= $status === '' ? ' is-selected' : '' ?>" data-filter-target="status" data-filter-value="" data-filter-label="All statuses"><span>All statuses</span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php foreach ($statuses as $key => $label): ?><button type="button" class="filter-option<?= $status === $key ? ' is-selected' : '' ?>" data-filter-target="status" data-filter-value="<?= e($key) ?>" data-filter-label="<?= e($label) ?>"><span><?= e($label) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?></div></div></div>
    <div class="ticket-filter-field"><div class="filter-picker" data-filter-picker><input type="hidden" name="priority" value="<?= e($priority) ?>"><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-copy"><strong data-filter-label><?= e($priority ? (TICKET_PRIORITIES[$priority] ?? $priority) : 'All priorities') ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Priority</p><button type="button" class="filter-option<?= $priority === '' ? ' is-selected' : '' ?>" data-filter-target="priority" data-filter-value="" data-filter-label="All priorities"><span>All priorities</span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php foreach (TICKET_PRIORITIES as $key => $label): ?><button type="button" class="filter-option<?= $priority === $key ? ' is-selected' : '' ?>" data-filter-target="priority" data-filter-value="<?= e($key) ?>" data-filter-label="<?= e($label) ?>"><span><?= e($label) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?></div></div></div>
    <div class="filter-actions my-work-filter-actions" data-filter-actions hidden><a class="button button-secondary" href="my-work.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>Clear</a></div>
</form>
<div class="table-wrap my-work-table-wrap"><table class="ticket-table my-work-table"><thead><tr><?php foreach (['ID', 'Title', 'Status', 'Priority', 'SLA', 'Age', 'Idle', 'Assignees', 'Last update'] as $heading): ?><th><?= e($heading) ?></th><?php endforeach; ?><th aria-label="Open ticket"></th></tr></thead><tbody><?php foreach ($tickets as $ticket): $sla = ticket_sla_state($ticket); $ticketPriority = $ticket['priority'] ?? 'normal'; ?><tr class="ticket-row-<?= e($sla[0]) ?>" data-ticket-href="ticket.php?id=<?= (int) $ticket['id'] ?>" tabindex="0" aria-label="Open <?= e($ticket['ticket_number']) ?>: <?= e($ticket['subject']) ?>"><td class="ticket-id"><a href="ticket.php?id=<?= (int) $ticket['id'] ?>"><?= e(ticket_display_id($ticket)) ?></a></td><td class="subject"><a href="ticket.php?id=<?= (int) $ticket['id'] ?>"><?= e($ticket['subject']) ?></a><span class="ticket-row-meta"><?= e($ticket['employee_name']) ?> · <?= e($ticket['departments']) ?> · <?= e($ticket['category']) ?><?= !empty($ticket['subcategory']) ? ' · ' . e($ticket['subcategory']) : '' ?></span></td><td><span class="pill pill-<?= e(str_replace('_', '-', $ticket['status'])) ?>"><?= e($statuses[$ticket['status']] ?? $ticket['status']) ?></span></td><td><span class="pill pill-priority-<?= e($ticketPriority) ?>"><?= e(TICKET_PRIORITIES[$ticketPriority] ?? ucfirst($ticketPriority)) ?></span></td><td class="ticket-sla"><span class="sla-badge sla-<?= e($sla[0]) ?>"><?= e($sla[1]) ?></span><small><?= e($sla[2]) ?></small></td><td class="ticket-date"><?= ticket_age_days($ticket, 'created_at') ?>d<small>Open</small></td><td class="ticket-date"><?= ticket_age_days($ticket, 'updated_at') ?>d<small>Since update</small></td><td class="ticket-assignee"><?= e($ticket['assignees'] ?? 'Unassigned') ?></td><td class="ticket-date"><?= e(date('M j, Y', strtotime((string) $ticket['updated_at']))) ?><small><?= e(date('g:i A', strtotime((string) $ticket['updated_at']))) ?></small></td><td class="row-arrow"><a href="ticket.php?id=<?= (int) $ticket['id'] ?>" aria-label="Open <?= e($ticket['ticket_number']) ?>">&rsaquo;</a></td></tr><?php endforeach; ?><?php if (!$tickets): ?><tr><td colspan="10" class="muted">No tickets are assigned to you for this view.</td></tr><?php endif; ?></tbody></table></div>
</div>
<?php page_end();
