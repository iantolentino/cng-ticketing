<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('view_all_tickets');
$statuses = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'closed' => 'Closed'];
$filters = [
    'dashboard_range' => trim((string) ($_GET['dashboard_range'] ?? '7d')),
    'dashboard_view' => trim((string) ($_GET['dashboard_view'] ?? '')),
    'trend_grain' => trim((string) ($_GET['trend_grain'] ?? 'monthly')),
    'trend_range' => trim((string) ($_GET['trend_range'] ?? '6m')),
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
$trendGrains = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];
$trendRanges = ['3m' => ['Last 3 months', '-3 months'], '6m' => ['Last 6 months', '-6 months'], '9m' => ['Last 9 months', '-9 months'], '12m' => ['Last 12 months', '-12 months']];
if (!isset($dashboardRanges[$filters['dashboard_range']])) $filters['dashboard_range'] = '7d';
if (!isset($dashboardViews[$filters['dashboard_view']])) $filters['dashboard_view'] = '';
if (!isset($trendGrains[$filters['trend_grain']])) $filters['trend_grain'] = 'monthly';
if (!isset($trendRanges[$filters['trend_range']])) $filters['trend_range'] = '6m';

$dashboardStart = $filters['dashboard_range'] === 'today'
    ? date('Y-m-d 00:00:00')
    : date('Y-m-d 00:00:00', strtotime($dashboardRanges[$filters['dashboard_range']][1]));

$dashboardParams = ['dashboard_start' => $dashboardStart];
$dashboardWhere = ['t.deleted_at IS NULL', 't.created_at >= :dashboard_start'];
$dashboardScopeSql = ticket_scope_sql($user, $dashboardParams);
$dashboardWhereSql = implode(' AND ', $dashboardWhere) . $dashboardScopeSql;

$dashboardStatusQuery = db()->prepare('SELECT t.status, COUNT(*) AS count FROM tickets t WHERE ' . $dashboardWhereSql . ' GROUP BY t.status');
$dashboardStatusQuery->execute($dashboardParams);
$dashboardStatusCounts = array_fill_keys(array_keys($statuses), 0);
foreach ($dashboardStatusQuery->fetchAll() as $row) if (isset($dashboardStatusCounts[$row['status']])) $dashboardStatusCounts[$row['status']] = (int) $row['count'];
$dashboardTotal = array_sum($dashboardStatusCounts);
$dashboardOpenWork = $dashboardStatusCounts['open'] + $dashboardStatusCounts['in_progress'] + $dashboardStatusCounts['pending'];
$dashboardUnassignedQuery = db()->prepare('SELECT COUNT(*) FROM tickets t WHERE ' . $dashboardWhereSql . ' AND t.assignee_id IS NULL AND NOT EXISTS (SELECT 1 FROM ticket_assignees ta_dash WHERE ta_dash.ticket_id = t.id)');
$dashboardUnassignedQuery->execute($dashboardParams);
$dashboardUnassigned = (int) $dashboardUnassignedQuery->fetchColumn();
$dashboardUrgentQuery = db()->prepare('SELECT COUNT(*) FROM tickets t WHERE ' . $dashboardWhereSql . ' AND t.status <> "closed" AND t.priority = "urgent"');
$dashboardUrgentQuery->execute($dashboardParams);
$dashboardUrgent = (int) $dashboardUrgentQuery->fetchColumn();
$dashboardOverdueQuery = db()->prepare('SELECT COUNT(*) FROM tickets t WHERE ' . $dashboardWhereSql . ' AND t.status <> "closed" AND t.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
$dashboardOverdueQuery->execute($dashboardParams);
$dashboardOverdue = (int) $dashboardOverdueQuery->fetchColumn();
$dashboardIdleQuery = db()->prepare('SELECT COUNT(*) FROM tickets t WHERE ' . $dashboardWhereSql . ' AND t.status <> "closed" AND t.updated_at < DATE_SUB(NOW(), INTERVAL 3 DAY) AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
$dashboardIdleQuery->execute($dashboardParams);
$dashboardIdle = (int) $dashboardIdleQuery->fetchColumn();

$trendStart = date('Y-m-d 00:00:00', strtotime($trendRanges[$filters['trend_range']][1]));
$trendParams = ['trend_start' => $trendStart];
$trendScopeSql = ticket_scope_sql($user, $trendParams);
$trendFormat = $filters['trend_grain'] === 'daily' ? '%Y-%m-%d' : ($filters['trend_grain'] === 'weekly' ? '%x-W%v' : '%Y-%m');
$trendQuery = db()->prepare("SELECT DATE_FORMAT(t.created_at, '$trendFormat') AS bucket, COUNT(*) AS count
    FROM tickets t
    WHERE t.deleted_at IS NULL AND t.created_at >= :trend_start $trendScopeSql
    GROUP BY bucket
    ORDER BY bucket");
$trendQuery->execute($trendParams);
$trendRows = [];
foreach ($trendQuery->fetchAll() as $row) $trendRows[(string) $row['bucket']] = (int) $row['count'];
$trendBuckets = [];
$cursor = strtotime($trendStart);
$end = time();
while ($cursor <= $end) {
    if ($filters['trend_grain'] === 'daily') {
        $key = date('Y-m-d', $cursor);
        $label = date('M j', $cursor);
        $cursor = strtotime('+1 day', $cursor);
    } elseif ($filters['trend_grain'] === 'weekly') {
        $key = date('o-\WW', $cursor);
        $label = 'W' . date('W', $cursor);
        $cursor = strtotime('+1 week', $cursor);
    } else {
        $key = date('Y-m', $cursor);
        $label = date('M Y', $cursor);
        $cursor = strtotime('+1 month', $cursor);
    }
    $trendBuckets[$key] = ['label' => $label, 'count' => $trendRows[$key] ?? 0];
}
$trendMax = max(1, ...array_column($trendBuckets, 'count'));

$activityParams = [];
$activityScopeSql = ticket_scope_sql($user, $activityParams);
$recentCommentsQuery = db()->prepare("SELECT c.body, c.created_at, u.full_name, t.id AS ticket_id, t.ticket_number, t.subject
    FROM ticket_comments c
    JOIN tickets t ON t.id = c.ticket_id
    JOIN users u ON u.id = c.user_id
    WHERE t.deleted_at IS NULL $activityScopeSql
    ORDER BY c.created_at DESC
    LIMIT 5");
$recentCommentsQuery->execute($activityParams);
$recentComments = $recentCommentsQuery->fetchAll();
$recentUpdatesQuery = db()->prepare("SELECT t.id AS ticket_id, t.ticket_number, t.subject, t.status, t.updated_at
    FROM tickets t
    WHERE t.deleted_at IS NULL $activityScopeSql
    ORDER BY t.updated_at DESC, t.id DESC
    LIMIT 5");
$recentUpdatesQuery->execute($activityParams);
$recentUpdates = $recentUpdatesQuery->fetchAll();
$recentClosedQuery = db()->prepare("SELECT t.id AS ticket_id, t.ticket_number, t.subject, t.closed_at
    FROM tickets t
    WHERE t.deleted_at IS NULL AND t.status = 'closed' AND t.closed_at IS NOT NULL $activityScopeSql
    ORDER BY t.closed_at DESC, t.id DESC
    LIMIT 5");
$recentClosedQuery->execute($activityParams);
$recentClosed = $recentClosedQuery->fetchAll();

function dashboard_card_url(string $view, array $filters): string
{
    $filters['dashboard_view'] = $view;
    return 'index.php?' . http_build_query(array_filter($filters, static fn($value): bool => $value !== ''));
}
function activity_excerpt(string $value, int $limit = 78): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return strlen($value) > $limit ? substr($value, 0, $limit - 3) . '...' : $value;
}

page_start('Dashboard', $user);
?>
<div class="page-head"><div><p class="eyebrow">Overview</p><h1>Dashboard</h1><p class="page-subtitle">Ticket workload and recent activity for records you can access.</p></div></div>
<section class="dashboard-panel" aria-labelledby="dashboard-title">
    <div class="dashboard-head"><div><h2 id="dashboard-title">Ticket dashboard</h2><p class="muted">Created tickets counted from <?= e(date('M j, Y', strtotime($dashboardStart))) ?><?= $filters['dashboard_view'] ? ' - viewing ' . e($dashboardViews[$filters['dashboard_view']]) : '' ?>.</p></div><form method="get" class="range-form"><label>Range<select name="dashboard_range" onchange="this.form.submit()"><?php foreach ($dashboardRanges as $value => $range): ?><option value="<?= e($value) ?>"<?= $filters['dashboard_range'] === $value ? ' selected' : '' ?>><?= e($range[0]) ?></option><?php endforeach; ?></select></label><?php if ($filters['dashboard_view']): ?><input type="hidden" name="dashboard_view" value="<?= e($filters['dashboard_view']) ?>"><?php endif; ?></form></div>
    <div class="metric-grid">
        <a class="metric-card metric-link<?= $filters['dashboard_view'] === 'total' ? ' active' : '' ?>" href="<?= e(dashboard_card_url('total', $filters)) ?>"><span>Total created</span><strong><?= (int) $dashboardTotal ?></strong><small><?= e($dashboardRanges[$filters['dashboard_range']][0]) ?></small></a>
        <a class="metric-card metric-link<?= $filters['dashboard_view'] === 'open_work' ? ' active' : '' ?>" href="<?= e(dashboard_card_url('open_work', $filters)) ?>"><span>Open work</span><strong><?= (int) $dashboardOpenWork ?></strong><small>Open, in progress, pending</small></a>
        <a class="metric-card metric-card-alert metric-link<?= $filters['dashboard_view'] === 'urgent' ? ' active' : '' ?>" href="<?= e(dashboard_card_url('urgent', $filters)) ?>"><span>Urgent</span><strong><?= (int) $dashboardUrgent ?></strong><small>Open urgent tickets</small></a>
        <a class="metric-card metric-card-alert metric-link<?= $filters['dashboard_view'] === 'overdue' ? ' active' : '' ?>" href="<?= e(dashboard_card_url('overdue', $filters)) ?>"><span>Overdue</span><strong><?= (int) $dashboardOverdue ?></strong><small>7+ days open</small></a>
        <a class="metric-card metric-link<?= $filters['dashboard_view'] === 'idle' ? ' active' : '' ?>" href="<?= e(dashboard_card_url('idle', $filters)) ?>"><span>Idle watch</span><strong><?= (int) $dashboardIdle ?></strong><small>3+ days without update</small></a>
        <a class="metric-card metric-link<?= $filters['dashboard_view'] === 'unassigned' ? ' active' : '' ?>" href="<?= e(dashboard_card_url('unassigned', $filters)) ?>"><span>Unassigned</span><strong><?= (int) $dashboardUnassigned ?></strong><small>Needs owner review</small></a>
    </div>
    <div class="status-strip"><?php foreach ($statuses as $status => $label): ?><div><span class="pill pill-<?= e(str_replace('_', '-', $status)) ?>"><?= e($label) ?></span><strong><?= (int) $dashboardStatusCounts[$status] ?></strong></div><?php endforeach; ?></div>
</section>
<section class="dashboard-panel" aria-labelledby="trend-title">
    <div class="dashboard-head"><div><h2 id="trend-title">Ticket trend</h2><p class="muted">Created ticket volume by <?= e(strtolower($trendGrains[$filters['trend_grain']])) ?> bucket.</p></div><form method="get" class="range-form trend-form"><label>Range<select name="trend_range" onchange="this.form.submit()"><?php foreach ($trendRanges as $value => $range): ?><option value="<?= e($value) ?>"<?= $filters['trend_range'] === $value ? ' selected' : '' ?>><?= e($range[0]) ?></option><?php endforeach; ?></select></label><label>View<select name="trend_grain" onchange="this.form.submit()"><?php foreach ($trendGrains as $value => $label): ?><option value="<?= e($value) ?>"<?= $filters['trend_grain'] === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><input type="hidden" name="dashboard_range" value="<?= e($filters['dashboard_range']) ?>"><?php if ($filters['dashboard_view']): ?><input type="hidden" name="dashboard_view" value="<?= e($filters['dashboard_view']) ?>"><?php endif; ?></form></div>
    <div class="trend-chart" role="img" aria-label="Ticket trend chart"><?php foreach ($trendBuckets as $bucket): ?><div class="trend-bar-item"><div class="trend-bar-track"><span class="trend-bar" style="height:<?= max(4, (int) round(($bucket['count'] / $trendMax) * 100)) ?>%"></span></div><strong><?= (int) $bucket['count'] ?></strong><small><?= e($bucket['label']) ?></small></div><?php endforeach; ?></div>
</section>
<section class="activity-panel" aria-labelledby="activity-title">
    <div class="section-head"><div><h2 id="activity-title">Recent activity</h2><p class="muted">Latest comments, updates, and closures across tickets you can access.</p></div></div>
    <div class="activity-grid">
        <div class="activity-card"><h3>New comments</h3><?php foreach ($recentComments as $comment): ?><a class="activity-item" href="ticket.php?id=<?= (int) $comment['ticket_id'] ?>"><strong><?= e($comment['ticket_number']) ?></strong><span><?= e($comment['subject']) ?></span><small><?= e($comment['full_name']) ?> - <?= e(date('M j, g:i A', strtotime($comment['created_at']))) ?></small><em><?= e(activity_excerpt((string) $comment['body'])) ?></em></a><?php endforeach; ?><?php if (!$recentComments): ?><p class="muted">No recent comments yet.</p><?php endif; ?></div>
        <div class="activity-card"><h3>Recently updated</h3><?php foreach ($recentUpdates as $ticket): ?><a class="activity-item" href="ticket.php?id=<?= (int) $ticket['ticket_id'] ?>"><strong><?= e($ticket['ticket_number']) ?></strong><span><?= e($ticket['subject']) ?></span><small><span class="pill pill-<?= e(str_replace('_', '-', $ticket['status'])) ?>"><?= e($statuses[$ticket['status']] ?? $ticket['status']) ?></span> <?= e(date('M j, g:i A', strtotime($ticket['updated_at']))) ?></small></a><?php endforeach; ?><?php if (!$recentUpdates): ?><p class="muted">No ticket updates yet.</p><?php endif; ?></div>
        <div class="activity-card"><h3>Recently closed</h3><?php foreach ($recentClosed as $ticket): ?><a class="activity-item" href="ticket.php?id=<?= (int) $ticket['ticket_id'] ?>"><strong><?= e($ticket['ticket_number']) ?></strong><span><?= e($ticket['subject']) ?></span><small><?= e(date('M j, g:i A', strtotime($ticket['closed_at']))) ?></small></a><?php endforeach; ?><?php if (!$recentClosed): ?><p class="muted">No closed tickets yet.</p><?php endif; ?></div>
    </div>
</section>
<?php page_end();
