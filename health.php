<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';

$admin = require_login();
if (($admin['role_slug'] ?? '') !== 'super-admin') {
    http_response_code(403);
    exit('Super Admin access required.');
}

$checks = [];
$pdo = null;
try {
    $pdo = db();
    $pdo->query('SELECT 1');
    $checks[] = ['Database connection', 'Healthy'];

    $requiredTables = ['users', 'roles', 'tickets', 'ticket_comments', 'ticket_attachments', 'leave_request_attachments', 'password_reset_tokens', 'notifications', 'system_logs'];
    $placeholders = implode(',', array_fill(0, count($requiredTables), '?'));
    $schemaCheck = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN (' . $placeholders . ')');
    $schemaCheck->execute($requiredTables);
    $schemaReady = (int) $schemaCheck->fetchColumn() === count($requiredTables);
    $checks[] = ['Required database tables', $schemaReady ? 'Ready' : 'Missing required tables'];
} catch (Throwable) {
    $checks[] = ['Database connection', 'Unavailable'];
    $checks[] = ['Required database tables', 'Not checked'];
}

$checks[] = ['SMTP', empty($config['smtp']['host']) || empty($config['smtp']['from_email']) ? 'Not configured' : 'Configured'];
$checks[] = ['PHP file uploads', extension_loaded('fileinfo') ? 'Ready' : 'Missing fileinfo extension'];
$checks[] = ['Excel export', extension_loaded('zip') ? 'Ready' : 'Unavailable: zip extension missing'];
foreach (['storage/private/ticket-attachments', 'storage/private/leave-attachments', '.local/sessions'] as $path) {
    $checks[] = [$path, is_dir(ROOT_PATH . '/' . $path) && is_writable(ROOT_PATH . '/' . $path) ? 'Writable' : 'Missing or not writable'];
}
$checks[] = ['Private storage web protection', is_file(ROOT_PATH . '/storage/private/.htaccess') ? 'Configured' : 'Missing .htaccess'];

$logs = [];
try {
    $logs = db()->query('SELECT level,event,message,created_at FROM system_logs ORDER BY created_at DESC LIMIT 50')->fetchAll();
} catch (Throwable) {
}

page_start('System health', $admin);
?>
<div class="page-head"><div><p class="eyebrow">Super Admin</p><h1>System health</h1><p class="page-subtitle">Application checks and recent operational failures.</p></div></div>
<section><h2>Checks</h2><div class="table-wrap"><table class="ticket-table"><thead><tr><th>Component</th><th>Status</th></tr></thead><tbody><?php foreach ($checks as $check): ?><tr><td><?= e($check[0]) ?></td><td><?= e($check[1]) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section><h2>Recent system logs</h2><div class="table-wrap"><table class="ticket-table health-log-table"><thead><tr><th>Date</th><th>Level</th><th>Event</th><th>Message</th></tr></thead><tbody><?php foreach ($logs as $log): ?><tr><td><?= e($log['created_at']) ?></td><td><?= e($log['level']) ?></td><td><?= e($log['event']) ?></td><td><?= e($log['message']) ?></td></tr><?php endforeach; ?><?php if (!$logs): ?><tr><td colspan="4" class="muted">No system logs available yet.</td></tr><?php endif; ?></tbody></table></div></section>
<?php page_end();
