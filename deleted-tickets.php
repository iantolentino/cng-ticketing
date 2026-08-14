<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';
$admin = require_login();
if (($admin['role_slug'] ?? '') !== 'super-admin') { http_response_code(403); exit('Super Admin access required.'); }
$tickets = db()->query("SELECT t.id,t.ticket_number,t.subject,t.deleted_at,u.full_name AS deleted_by_name FROM tickets t LEFT JOIN users u ON u.id=t.deleted_by WHERE t.deleted_at IS NOT NULL ORDER BY t.deleted_at DESC")->fetchAll();
page_start('Deleted tickets', $admin); ?>
<div class="page-head"><div><p class="eyebrow">Super Admin</p><h1>Deleted tickets</h1><p class="page-subtitle">Restore a soft-deleted ticket and return it to the active register.</p></div></div>
<section><div class="table-wrap"><table class="ticket-table"><thead><tr><th>Ticket</th><th>Subject</th><th>Deleted at</th><th>Deleted by</th><th>Action</th></tr></thead><tbody><?php foreach ($tickets as $ticket): ?><tr><td><?= e($ticket['ticket_number']) ?></td><td><?= e($ticket['subject']) ?></td><td><?= e($ticket['deleted_at']) ?></td><td><?= e($ticket['deleted_by_name'] ?? 'Unknown') ?></td><td><form method="post" action="restore-ticket.php" data-feedback data-confirm="Restore this ticket?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>"><button class="button">Restore</button></form></td></tr><?php endforeach; ?><?php if (!$tickets): ?><tr><td colspan="5" class="muted">No deleted tickets.</td></tr><?php endif; ?></tbody></table></div></section>
<?php page_end();
