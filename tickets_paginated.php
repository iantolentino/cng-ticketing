<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('view_all_tickets');
$perPage = 15;
$requestedPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$totalTickets = (int) db()->query('SELECT COUNT(*) FROM tickets WHERE deleted_at IS NULL')->fetchColumn();
$totalPages = max(1, (int) ceil($totalTickets / $perPage));
$currentPage = min($requestedPage, $totalPages);
$offset = ($currentPage - 1) * $perPage;
$pageStart = max(1, $currentPage - 2);
$pageEnd = min($totalPages, $currentPage + 2);

$query = db()->prepare('SELECT t.*, d.name AS department, a.full_name AS assignee FROM tickets t JOIN departments d ON d.id = t.department_id LEFT JOIN users a ON a.id = t.assignee_id WHERE t.deleted_at IS NULL ORDER BY t.updated_at DESC, t.id DESC LIMIT :limit OFFSET :offset');
$query->bindValue(':limit', $perPage, PDO::PARAM_INT);
$query->bindValue(':offset', $offset, PDO::PARAM_INT);
$query->execute();
$tickets = $query->fetchAll();
$status = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'closed' => 'Closed'];

function pagination_link(int $page, string $label, bool $current = false): string
{
    if ($current) return '<span class="current" aria-current="page">' . e($label) . '</span>';
    return '<a href="tickets_paginated.php?page=' . $page . '">' . e($label) . '</a>';
}

page_start('Issues register', $user);
?>
<div class="page-head"><div><p class="eyebrow">CNG / Jamesons Ticketing System</p><h1>Issues register</h1><p class="page-subtitle">Shared account concerns and escalations.</p></div><div class="page-head-actions"><?php if (user_can('export_tickets')): ?><a class="button button-secondary" href="export-tickets.php?format=csv">Export CSV</a><a class="button button-secondary" href="export-tickets.php?format=xlsx">Export Excel</a><?php endif; ?><?php if (user_can('create_tickets')): ?><a class="button" href="create-ticket.php">Create ticket</a><?php endif; ?></div></div>
<div class="table-wrap"><table class="ticket-table"><thead><tr><?php foreach (['Status', 'Subject', 'Department', 'Category', 'Subcategory', 'Date Created', 'Date Updated', 'Date Closed', 'Assignee', 'Employee'] as $heading): ?><th><?= e($heading) ?></th><?php endforeach; ?><th aria-label="Open ticket"></th></tr></thead><tbody><?php foreach ($tickets as $ticket): ?><tr><td><span class="pill pill-<?= e(str_replace('_', '-', $ticket['status'])) ?>"><?= e($status[$ticket['status']] ?? $ticket['status']) ?></span></td><td class="subject"><a href="ticket.php?id=<?= (int) $ticket['id'] ?>"><?= e($ticket['subject']) ?></a></td><td><?= e($ticket['department']) ?></td><td><?= e($ticket['category']) ?></td><td><?= e($ticket['subcategory'] ?? '—') ?></td><td class="muted"><?= e($ticket['created_at']) ?></td><td class="muted"><?= e($ticket['updated_at']) ?></td><td class="muted"><?= e($ticket['closed_at'] ?? '—') ?></td><td><?= e($ticket['assignee'] ?? 'Unassigned') ?></td><td><?= e($ticket['employee_name']) ?></td><td class="row-arrow"><a href="ticket.php?id=<?= (int) $ticket['id'] ?>" aria-label="Open <?= e($ticket['ticket_number']) ?>">›</a></td></tr><?php endforeach; ?><?php if (!$tickets): ?><tr><td colspan="11" class="muted">No tickets have been created yet.</td></tr><?php endif; ?></tbody></table></div>
<?php if ($totalTickets > 0): ?><nav class="pagination" aria-label="Ticket pages"><?php if ($currentPage > 1): ?><a href="tickets_paginated.php?page=<?= $currentPage - 1 ?>">Previous</a><?php else: ?><span class="disabled">Previous</span><?php endif; ?><?php if ($pageStart > 1): ?><?= pagination_link(1, '1') ?><?php if ($pageStart > 2): ?><span class="disabled">…</span><?php endif; ?><?php endif; ?><?php for ($page = $pageStart; $page <= $pageEnd; $page++): ?><?= pagination_link($page, (string) $page, $page === $currentPage) ?><?php endfor; ?><?php if ($pageEnd < $totalPages): ?><?php if ($pageEnd < $totalPages - 1): ?><span class="disabled">…</span><?php endif; ?><?= pagination_link($totalPages, (string) $totalPages) ?><?php endif; ?><?php if ($currentPage < $totalPages): ?><a href="tickets_paginated.php?page=<?= $currentPage + 1 ?>">Next</a><?php else: ?><span class="disabled">Next</span><?php endif; ?></nav><?php endif; ?>
<?php page_end();
