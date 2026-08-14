<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';
$admin = require_login();
if (($admin['role_slug'] ?? '') !== 'super-admin') { http_response_code(403); exit('Super Admin access required.'); }
$events = db()->query('SELECT a.*,u.full_name FROM admin_audit_log a LEFT JOIN users u ON u.id=a.actor_id ORDER BY a.created_at DESC, a.id DESC LIMIT 500')->fetchAll();
page_start('Audit log', $admin); ?>
<div class="page-head"><div><p class="eyebrow">Super Admin</p><h1>Administrative audit log</h1><p class="page-subtitle">Recent user, permission, restore, leave, attachment, and calendar administration actions.</p></div></div>
<section><div class="table-wrap"><table class="ticket-table"><thead><tr><th>Date</th><th>Actor</th><th>Action</th><th>Entity</th><th>Details</th></tr></thead><tbody><?php foreach ($events as $event): ?><tr><td><?= e($event['created_at']) ?></td><td><?= e($event['full_name'] ?? 'System') ?></td><td><?= e($event['action']) ?></td><td><?= e($event['entity_type']) ?><?= $event['entity_id'] ? ' #' . (int) $event['entity_id'] : '' ?></td><td><code><?= e((string) ($event['details'] ?? '')) ?></code></td></tr><?php endforeach; ?><?php if (!$events): ?><tr><td colspan="5" class="muted">No administrative actions recorded.</td></tr><?php endif; ?></tbody></table></div></section>
<?php page_end();
