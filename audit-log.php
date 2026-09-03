<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';
$admin = require_login();
if (($admin['role_slug'] ?? '') !== 'super-admin') { http_response_code(403); exit('Super Admin access required.'); }
$events = db()->query('SELECT a.*,u.full_name FROM admin_audit_log a LEFT JOIN users u ON u.id=a.actor_id ORDER BY a.created_at DESC, a.id DESC LIMIT 500')->fetchAll();
$tickets = db()->query("SELECT t.id,t.ticket_number,t.subject,t.deleted_at,u.full_name AS deleted_by_name FROM tickets t LEFT JOIN users u ON u.id=t.deleted_by WHERE t.deleted_at IS NOT NULL ORDER BY t.deleted_at DESC")->fetchAll();
page_start('Admin history', $admin); ?>
<div class="admin-history-grid">
    <section class="admin-history-panel">
        <div class="section-head"><div><p class="eyebrow">Ticket recovery</p><h2>Deleted tickets</h2><p>Restore a soft-deleted ticket and return it to the active register.</p></div></div>
        <div class="table-wrap"><table class="ticket-table"><thead><tr><th>Ticket</th><th>Subject</th><th>Deleted at</th><th>Deleted by</th><th>Action</th></tr></thead><tbody><?php foreach ($tickets as $ticket): ?><tr><td><?= e($ticket['ticket_number']) ?></td><td><?= e($ticket['subject']) ?></td><td><?= e($ticket['deleted_at']) ?></td><td><?= e($ticket['deleted_by_name'] ?? 'Unknown') ?></td><td><form method="post" action="restore-ticket.php" data-feedback data-confirm="Restore this ticket?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>"><button class="button">Restore</button></form></td></tr><?php endforeach; ?><?php if (!$tickets): ?><tr><td colspan="5" class="muted">No deleted tickets.</td></tr><?php endif; ?></tbody></table></div>
    </section>
    <section class="admin-history-panel">
        <div class="section-head"><div><p class="eyebrow">Account activity</p><h2>Audit log</h2><p>Recent user, permission, restore, leave, attachment, and calendar administration actions.</p></div></div>
        <div class="table-wrap"><table class="ticket-table"><thead><tr><th>Date</th><th>Actor</th><th>Action</th><th>Entity</th><th>Details</th></tr></thead><tbody><?php foreach ($events as $event): ?><tr><td><?= e($event['created_at']) ?></td><td><?= e($event['full_name'] ?? 'System') ?></td><td><?= e($event['action']) ?></td><td><?= e($event['entity_type']) ?><?= $event['entity_id'] ? ' #' . (int) $event['entity_id'] : '' ?></td><td><code><?= e((string) ($event['details'] ?? '')) ?></code></td></tr><?php endforeach; ?><?php if (!$events): ?><tr><td colspan="5" class="muted">No administrative actions recorded.</td></tr><?php endif; ?></tbody></table></div>
    </section>
</div>
<?php page_end();
