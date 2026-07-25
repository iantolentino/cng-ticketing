<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('view_all_tickets');
$status = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'closed' => 'Closed'];
$departments = db()->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
$departmentIds = array_map('intval', array_column($departments, 'id'));
$filters = [
    'status' => trim((string) ($_GET['status'] ?? '')),
    'department' => trim((string) ($_GET['department'] ?? '')),
    'category' => trim((string) ($_GET['category'] ?? '')),
    'subcategory' => trim((string) ($_GET['subcategory'] ?? '')),
    'search' => trim((string) ($_GET['search'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
];
if (!isset($status[$filters['status']])) $filters['status'] = '';
if (!ctype_digit($filters['department']) || !in_array((int) $filters['department'], $departmentIds, true)) $filters['department'] = '';
if (!array_key_exists($filters['category'], TICKET_CATEGORIES)) $filters['category'] = '';
$subcategoryOptions = $filters['category'] ? TICKET_CATEGORIES[$filters['category']] : array_merge(...array_values(TICKET_CATEGORIES));
if (!in_array($filters['subcategory'], $subcategoryOptions, true)) $filters['subcategory'] = '';
foreach (['date_from', 'date_to'] as $dateKey) if ($filters[$dateKey] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$dateKey])) $filters[$dateKey] = '';

$where = ['t.deleted_at IS NULL'];
$params = [];
foreach (['status', 'category', 'subcategory'] as $key) if ($filters[$key] !== '') { $where[] = 't.' . $key . ' = :' . $key; $params[$key] = $filters[$key]; }
if ($filters['department'] !== '') { $where[] = 't.department_id = :department'; $params['department'] = (int) $filters['department']; }
if ($filters['search'] !== '') { $where[] = '(t.subject LIKE :subject_search OR t.employee_name LIKE :employee_search)'; $params['subject_search'] = $params['employee_search'] = '%' . $filters['search'] . '%'; }
if ($filters['date_from'] !== '') { $where[] = 't.created_at >= :date_from'; $params['date_from'] = $filters['date_from'] . ' 00:00:00'; }
if ($filters['date_to'] !== '') { $where[] = 't.created_at < DATE_ADD(:date_to, INTERVAL 1 DAY)'; $params['date_to'] = $filters['date_to']; }
$whereSql = implode(' AND ', $where);

$perPage = 15;
$requestedPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$count = db()->prepare('SELECT COUNT(*) FROM tickets t WHERE ' . $whereSql);
$count->execute($params);
$totalTickets = (int) $count->fetchColumn();
$totalPages = max(1, (int) ceil($totalTickets / $perPage));
$currentPage = min($requestedPage, $totalPages);
$offset = ($currentPage - 1) * $perPage;
$pageStart = max(1, $currentPage - 2);
$pageEnd = min($totalPages, $currentPage + 2);
$notice = ($_GET['notice'] ?? '') === 'ticket_deleted' ? 'Ticket deleted.' : null;

$query = db()->prepare('SELECT t.*, d.name AS department, a.full_name AS assignee FROM tickets t JOIN departments d ON d.id = t.department_id LEFT JOIN users a ON a.id = t.assignee_id WHERE ' . $whereSql . ' ORDER BY t.updated_at DESC, t.id DESC LIMIT :limit OFFSET :offset');
foreach ($params as $key => $value) $query->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
$query->bindValue(':limit', $perPage, PDO::PARAM_INT);
$query->bindValue(':offset', $offset, PDO::PARAM_INT);
$query->execute();
$tickets = $query->fetchAll();

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

page_start('Issues register', $user);
?>
<?php if ($notice): ?><p class="action-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
<div class="page-head"><div><p class="eyebrow">CNG / Jamesons Ticketing System</p><h1>Issues register</h1><p class="page-subtitle">Shared account concerns and escalations.</p></div><div class="page-head-actions"><?php if (user_can('export_tickets')): ?><a class="button button-secondary" href="export-tickets.php?format=csv">Export CSV</a><a class="button button-secondary" href="export-tickets.php?format=xlsx">Export Excel</a><?php endif; ?><?php if (user_can('create_tickets')): ?><a class="button" href="create-ticket.php">Create ticket</a><?php endif; ?></div></div>
<form method="get" class="ticket-form"><label>Status<select name="status"><option value="">All statuses</option><?php foreach ($status as $value => $label): ?><option value="<?= e($value) ?>"<?= $filters['status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><label>Department<select name="department"><option value="">All departments</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"<?= $filters['department'] === (string) $department['id'] ? ' selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></label><label>Category<select name="category"><option value="">All categories</option><?php foreach (array_keys(TICKET_CATEGORIES) as $category): ?><option value="<?= e($category) ?>"<?= $filters['category'] === $category ? ' selected' : '' ?>><?= e($category) ?></option><?php endforeach; ?></select></label><label>Subcategory<select name="subcategory"><option value="">All subcategories</option><?php foreach ($subcategoryOptions as $subcategory): ?><option value="<?= e($subcategory) ?>"<?= $filters['subcategory'] === $subcategory ? ' selected' : '' ?>><?= e($subcategory) ?></option><?php endforeach; ?></select></label><label>Subject or employee<input name="search" value="<?= e($filters['search']) ?>" placeholder="Search subject or employee"></label><label>Created from<input type="date" name="date_from" value="<?= e($filters['date_from']) ?>"></label><label>Created to<input type="date" name="date_to" value="<?= e($filters['date_to']) ?>"></label><div><button class="button">Apply filters</button> <a class="button button-secondary" href="index.php">Clear</a></div></form>
<div class="table-wrap"><table class="ticket-table"><thead><tr><?php foreach (['Status', 'Subject', 'Department', 'Category', 'Subcategory', 'Date Created', 'Date Updated', 'Date Closed', 'Assignee', 'Employee'] as $heading): ?><th><?= e($heading) ?></th><?php endforeach; ?><th aria-label="Open ticket"></th></tr></thead><tbody><?php foreach ($tickets as $ticket): ?><tr><td><span class="pill pill-<?= e(str_replace('_', '-', $ticket['status'])) ?>"><?= e($status[$ticket['status']] ?? $ticket['status']) ?></span></td><td class="subject"><a href="ticket.php?id=<?= (int) $ticket['id'] ?>"><?= e($ticket['subject']) ?></a></td><td><?= e($ticket['department']) ?></td><td><?= e($ticket['category']) ?></td><td><?= e($ticket['subcategory'] ?? '—') ?></td><td class="muted"><?= e($ticket['created_at']) ?></td><td class="muted"><?= e($ticket['updated_at']) ?></td><td class="muted"><?= e($ticket['closed_at'] ?? '—') ?></td><td><?= e($ticket['assignee'] ?? 'Unassigned') ?></td><td><?= e($ticket['employee_name']) ?></td><td class="row-arrow"><a href="ticket.php?id=<?= (int) $ticket['id'] ?>" aria-label="Open <?= e($ticket['ticket_number']) ?>">›</a></td></tr><?php endforeach; ?><?php if (!$tickets): ?><tr><td colspan="11" class="muted">No tickets match the selected filters.</td></tr><?php endif; ?></tbody></table></div>
<?php if ($totalTickets > 0): ?><nav class="pagination" aria-label="Ticket pages"><?php if ($currentPage > 1): ?><a href="<?= e(ticket_page_url($currentPage - 1, $filters)) ?>">Previous</a><?php else: ?><span class="disabled">Previous</span><?php endif; ?><?php if ($pageStart > 1): ?><?= pagination_link(1, '1', false, $filters) ?><?php if ($pageStart > 2): ?><span class="disabled">…</span><?php endif; ?><?php endif; ?><?php for ($page = $pageStart; $page <= $pageEnd; $page++): ?><?= pagination_link($page, (string) $page, $page === $currentPage, $filters) ?><?php endfor; ?><?php if ($pageEnd < $totalPages): ?><?php if ($pageEnd < $totalPages - 1): ?><span class="disabled">…</span><?php endif; ?><?= pagination_link($totalPages, (string) $totalPages, false, $filters) ?><?php endif; ?><?php if ($currentPage < $totalPages): ?><a href="<?= e(ticket_page_url($currentPage + 1, $filters)) ?>">Next</a><?php else: ?><span class="disabled">Next</span><?php endif; ?></nav><?php endif; ?>
<?php page_end();
