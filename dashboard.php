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
$trendGrains = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];
$trendRanges = ['7d' => ['Last 7 days', '-6 days'], '30d' => ['Last 30 days', '-29 days'], '3m' => ['Last 3 months', '-3 months'], '6m' => ['Last 6 months', '-6 months'], '9m' => ['Last 9 months', '-9 months'], '12m' => ['Last 12 months', '-12 months'], '1y' => ['Last 1 year', '-1 year']];
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
$dashboardOverdueQuery = db()->prepare('SELECT COUNT(*) FROM tickets t WHERE ' . $dashboardWhereSql . ' AND t.status <> "closed" AND TIMESTAMPDIFF(DAY,t.created_at,NOW()) >= COALESCE((SELECT open_days FROM sla_rules sr WHERE sr.priority=t.priority),7)');
$dashboardOverdueQuery->execute($dashboardParams);
$dashboardOverdue = (int) $dashboardOverdueQuery->fetchColumn();
$dashboardIdleQuery = db()->prepare('SELECT COUNT(*) FROM tickets t WHERE ' . $dashboardWhereSql . ' AND t.status <> "closed" AND TIMESTAMPDIFF(DAY,t.updated_at,NOW()) >= COALESCE((SELECT idle_days FROM sla_rules sr WHERE sr.priority=t.priority),3) AND TIMESTAMPDIFF(DAY,t.created_at,NOW()) < COALESCE((SELECT open_days FROM sla_rules sr2 WHERE sr2.priority=t.priority),7)');
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
$trendChartCoordinates = [];
$trendChartWidth = 1000;
$trendChartTop = 24;
$trendChartBaseline = 198;
$trendChartPlotHeight = 154;
$trendChartHorizontalPadding = 24;
$trendChartStep = count($trendBuckets) > 1
    ? ($trendChartWidth - ($trendChartHorizontalPadding * 2)) / (count($trendBuckets) - 1)
    : 0;
foreach (array_values($trendBuckets) as $index => $bucket) {
    $trendChartCoordinates[] = [
        'x' => round($trendChartHorizontalPadding + ($index * $trendChartStep), 2),
        'y' => round($trendChartTop + (($trendMax - $bucket['count']) / $trendMax * $trendChartPlotHeight), 2),
        'count' => (int) $bucket['count'],
        'label' => (string) $bucket['label'],
    ];
}
$trendChartPointString = implode(' ', array_map(static fn(array $point): string => $point['x'] . ',' . $point['y'], $trendChartCoordinates));
$trendChartAreaPath = '';
$trendChartLinePath = '';
if ($trendChartCoordinates) {
    $trendChartLinePath = 'M ' . $trendChartCoordinates[0]['x'] . ' ' . $trendChartCoordinates[0]['y'];
    for ($index = 0, $coordinateCount = count($trendChartCoordinates); $index < $coordinateCount - 1; $index++) {
        $currentPoint = $trendChartCoordinates[$index];
        $nextPoint = $trendChartCoordinates[$index + 1];
        $midpointX = round(($currentPoint['x'] + $nextPoint['x']) / 2, 2);
        $midpointY = round(($currentPoint['y'] + $nextPoint['y']) / 2, 2);
        $trendChartLinePath .= ' Q ' . $currentPoint['x'] . ' ' . $currentPoint['y'] . ' ' . $midpointX . ' ' . $midpointY;
    }
    $lastPoint = $trendChartCoordinates[count($trendChartCoordinates) - 1];
    $trendChartLinePath .= ' Q ' . $lastPoint['x'] . ' ' . $lastPoint['y'] . ' ' . $lastPoint['x'] . ' ' . $lastPoint['y'];
    $trendChartAreaPath = $trendChartLinePath
        . ' L ' . $lastPoint['x'] . ' ' . $trendChartBaseline
        . ' L ' . $trendChartCoordinates[0]['x'] . ' ' . $trendChartBaseline . ' Z';
}

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

$statusDonutColors = ['open' => '#3B82F6', 'in_progress' => '#F59E0B', 'pending' => '#94A3B8', 'closed' => '#10B981'];
$statusDonutTotal = max(1, $dashboardTotal);
$statusDonutStops = [];
$statusDonutPosition = 0.0;
foreach ($statuses as $status => $label) {
    $statusDonutShare = ((int) $dashboardStatusCounts[$status] / $statusDonutTotal) * 100;
    $statusDonutEnd = $statusDonutPosition + $statusDonutShare;
    $statusDonutStops[] = $statusDonutColors[$status] . ' ' . round($statusDonutPosition, 2) . '% ' . round($statusDonutEnd, 2) . '%';
    $statusDonutPosition = $statusDonutEnd;
}
$statusDonutStyle = 'conic-gradient(' . implode(', ', $statusDonutStops) . ')';

page_start('Dashboard', $user);
?>
<div class="dashboard-canvas">
    <section class="dashboard-panel dashboard-kpi-panel" aria-labelledby="dashboard-title">
        <div class="dashboard-head dashboard-greeting-head"><div><h2 id="dashboard-title">Greetings, <?= e((string) ($user['username'] ?? 'there')) ?>!</h2><p class="dashboard-date">Your ticket workload at a glance</p></div><form method="get" class="dashboard-filter-form" data-filter-form><input type="hidden" name="dashboard_range" value="<?= e($filters['dashboard_range']) ?>"><?php if ($filters['dashboard_view']): ?><input type="hidden" name="dashboard_view" value="<?= e($filters['dashboard_view']) ?>"><?php endif; ?><div class="filter-picker" data-filter-picker><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="5.5" width="16" height="14" rx="2"/><path d="M8 3.5v4M16 3.5v4M4 10h16"/></svg></span><span class="filter-picker-copy"><small>Date range</small><strong data-filter-label><?= e($dashboardRanges[$filters['dashboard_range']][0]) ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Ticket window</p><?php foreach ($dashboardRanges as $value => $range): ?><button type="button" class="filter-option<?= $filters['dashboard_range'] === $value ? ' is-selected' : '' ?>" data-filter-target="dashboard_range" data-filter-value="<?= e($value) ?>" data-filter-label="<?= e($range[0]) ?>"><span><?= e($range[0]) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?></div></div></form></div>
        <div class="metric-grid metric-grid-reference">
            <a class="metric-card metric-reference-card" href="index.php?status=open"><span>Open tickets</span><strong><?= (int) $dashboardStatusCounts['open'] ?></strong></a>
            <a class="metric-card metric-reference-card" href="index.php?status=in_progress"><span>In progress</span><strong><?= (int) $dashboardStatusCounts['in_progress'] ?></strong></a>
            <a class="metric-card metric-reference-card" href="index.php?status=closed"><span>Resolved</span><strong><?= (int) $dashboardStatusCounts['closed'] ?></strong></a>
            <a class="metric-card metric-card-alert metric-reference-card" href="<?= e(dashboard_card_url('urgent', $filters)) ?>"><span>Critical</span><strong><?= (int) $dashboardUrgent ?></strong></a>
            <a class="metric-card metric-reference-card" href="index.php?status=pending"><span>Pending</span><strong><?= (int) $dashboardStatusCounts['pending'] ?></strong></a>
            <a class="metric-card metric-card-alert metric-reference-card" href="<?= e(dashboard_card_url('overdue', $filters)) ?>"><span>Overdue</span><strong><?= (int) $dashboardOverdue ?></strong></a>
            <a class="metric-card metric-reference-card" href="<?= e(dashboard_card_url('idle', $filters)) ?>"><span>Idle watch</span><strong><?= (int) $dashboardIdle ?></strong></a>
            <a class="metric-card metric-reference-card" href="<?= e(dashboard_card_url('unassigned', $filters)) ?>"><span>Unassigned</span><strong><?= (int) $dashboardUnassigned ?></strong></a>
        </div>
    </section>

    <div class="dashboard-chart-grid">
        <section class="dashboard-panel dashboard-trend-panel" aria-labelledby="trend-title">
            <div class="dashboard-head"><div><h2 id="trend-title">Tickets created</h2><p class="muted"><?= e($trendRanges[$filters['trend_range']][0]) ?></p></div><form method="get" class="dashboard-filter-form trend-filter-form" data-filter-form><input type="hidden" name="trend_range" value="<?= e($filters['trend_range']) ?>"><input type="hidden" name="trend_grain" value="<?= e($filters['trend_grain']) ?>"><input type="hidden" name="dashboard_range" value="<?= e($filters['dashboard_range']) ?>"><?php if ($filters['dashboard_view']): ?><input type="hidden" name="dashboard_view" value="<?= e($filters['dashboard_view']) ?>"><?php endif; ?><div class="filter-picker" data-filter-picker><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-copy"><small>Range</small><strong data-filter-label><?= e($trendRanges[$filters['trend_range']][0]) ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Trend window</p><?php foreach ($trendRanges as $value => $range): ?><button type="button" class="filter-option<?= $filters['trend_range'] === $value ? ' is-selected' : '' ?>" data-filter-target="trend_range" data-filter-value="<?= e($value) ?>" data-filter-label="<?= e($range[0]) ?>"><span><?= e($range[0]) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?></div></div><div class="filter-picker" data-filter-picker><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-copy"><small>View</small><strong data-filter-label><?= e($trendGrains[$filters['trend_grain']]) ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Group by</p><?php foreach ($trendGrains as $value => $label): ?><button type="button" class="filter-option<?= $filters['trend_grain'] === $value ? ' is-selected' : '' ?>" data-filter-target="trend_grain" data-filter-value="<?= e($value) ?>" data-filter-label="<?= e($label) ?>"><span><?= e($label) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?></div></div></form></div>
            <div class="trend-chart" role="img" aria-label="Ticket trend chart"><div class="trend-chart-shell"><div class="trend-visual"><svg viewBox="0 0 1000 230" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="trend-area-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#1E5AA8" stop-opacity=".22"/><stop offset="100%" stop-color="#1E5AA8" stop-opacity=".02"/></linearGradient></defs><g class="trend-grid-lines"><line x1="24" y1="24" x2="976" y2="24"/><line x1="24" y1="81" x2="976" y2="81"/><line x1="24" y1="138" x2="976" y2="138"/><line x1="24" y1="198" x2="976" y2="198"/></g><path class="trend-area" d="<?= e($trendChartAreaPath) ?>"/><path class="trend-line" d="<?= e($trendChartLinePath) ?>"/><?php foreach ($trendChartCoordinates as $point): ?><circle class="trend-point" cx="<?= e((string) $point['x']) ?>" cy="<?= e((string) $point['y']) ?>" r="5"><title><?= e($point['label']) ?>: <?= (int) $point['count'] ?> tickets</title></circle><?php endforeach; ?></svg><div class="trend-axis"><?php foreach ($trendChartCoordinates as $point): ?><span><?= e($point['label']) ?></span><?php endforeach; ?></div></div></div></div>
        </section>
        <section class="dashboard-panel dashboard-status-panel" aria-labelledby="status-title">
            <div class="dashboard-head"><div><h2 id="status-title">By status</h2><p class="muted">Ticket distribution</p></div></div>
            <div class="status-donut-wrap"><div class="status-donut" style="--status-donut:<?= e($statusDonutStyle) ?>"><span><?= (int) $dashboardTotal ?><small>Total</small></span></div></div>
            <div class="status-legend"><?php foreach ($statuses as $status => $label): ?><div><span><i class="status-dot status-dot-<?= e(str_replace('_', '-', $status)) ?>"></i><?= e($label) ?></span><strong><?= (int) $dashboardStatusCounts[$status] ?></strong></div><?php endforeach; ?></div>
        </section>
    </div>

    <div class="dashboard-lower-grid">
        <section class="dashboard-panel dashboard-signals-panel" aria-labelledby="signals-title">
            <div class="dashboard-head"><div><h2 id="signals-title">Workload signals</h2><p class="muted">Where follow-up attention is needed.</p></div></div>
            <div class="signal-list"><div class="signal-row"><span>Open work</span><strong><?= (int) $dashboardOpenWork ?></strong><span class="signal-track"><i style="width:<?= $dashboardTotal ? min(100, (int) round(($dashboardOpenWork / $dashboardTotal) * 100)) : 0 ?>%"></i></span></div><div class="signal-row"><span>Urgent</span><strong><?= (int) $dashboardUrgent ?></strong><span class="signal-track signal-track-alert"><i style="width:<?= $dashboardTotal ? min(100, (int) round(($dashboardUrgent / $dashboardTotal) * 100)) : 0 ?>%"></i></span></div><div class="signal-row"><span>Overdue</span><strong><?= (int) $dashboardOverdue ?></strong><span class="signal-track signal-track-alert"><i style="width:<?= $dashboardTotal ? min(100, (int) round(($dashboardOverdue / $dashboardTotal) * 100)) : 0 ?>%"></i></span></div><div class="signal-row"><span>Unassigned</span><strong><?= (int) $dashboardUnassigned ?></strong><span class="signal-track"><i style="width:<?= $dashboardTotal ? min(100, (int) round(($dashboardUnassigned / $dashboardTotal) * 100)) : 0 ?>%"></i></span></div></div>
        </section>
        <section class="dashboard-panel dashboard-recent-panel" aria-labelledby="recent-title">
            <div class="dashboard-head"><div><h2 id="recent-title">Recent tickets</h2><p class="muted">Latest activity</p></div></div>
            <div class="recent-ticket-list"><?php foreach (array_slice($recentUpdates, 0, 4) as $ticket): ?><a class="recent-ticket-item" href="ticket.php?id=<?= (int) $ticket['ticket_id'] ?>"><span class="recent-ticket-number"><?= e($ticket['ticket_number']) ?></span><span class="recent-ticket-status"><span class="pill pill-<?= e(str_replace('_', '-', $ticket['status'])) ?>"><?= e($statuses[$ticket['status']] ?? $ticket['status']) ?></span><small><?= e(date('M j, g:i A', strtotime($ticket['updated_at']))) ?></small></span></a><?php endforeach; ?><?php if (!$recentUpdates): ?><p class="muted">No recent tickets yet.</p><?php endif; ?></div>
        </section>
    </div>
</div>
<?php page_end();
