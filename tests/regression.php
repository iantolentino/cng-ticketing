<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function check(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS: {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL: {$message}\n";
}

function source_contains(string $relativePath, string $needle): bool
{
    global $root;
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $source = is_file($path) ? file_get_contents($path) : false;
    return $source !== false && str_contains($source, $needle);
}

$requiredFiles = [
    'login.php', 'forgot-password.php', 'reset-password.php', 'users.php',
    'deleted-tickets.php', 'restore-ticket.php', 'audit-log.php', 'dashboard.php',
    'index.php', 'ticket.php', 'bulk-tickets.php', 'calendar-admin.php',
    'import.php', 'reports.php', 'health.php', 'app/security.php', 'app/auth.php',
    'app/audit.php', 'app/logging.php',
];
foreach ($requiredFiles as $file) {
    check(is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file)), "required file exists: {$file}");
}

$migrationFiles = glob($root . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '[0-9][0-9][0-9]_*.sql') ?: [];
$migrationNumbers = [];
foreach ($migrationFiles as $file) {
    $migrationNumbers[] = (int) basename($file, '.sql');
}
sort($migrationNumbers);
$expectedNumbers = range(2, 21);
check($migrationNumbers === $expectedNumbers, 'migrations 002 through 021 are present without gaps');

$migrationMarkers = [
    '011' => 'password_reset_tokens',
    '012' => 'admin_audit_log',
    '013' => 'sla_rules',
    '014' => 'bulk_ticket_actions',
    '015' => 'visibility',
    '016' => 'system_logs',
    '017' => 'team-leader',
    '018' => 'approval_status',
    '019' => 'api_tokens',
    '020' => 'resolution',
    '021' => 'token_hash',
];
foreach ($migrationMarkers as $number => $marker) {
    $matches = glob($root . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . $number . '_*.sql') ?: [];
    check(count($matches) === 1 && str_contains((string) file_get_contents($matches[0]), $marker), "migration {$number} contains {$marker}");
}

check(source_contains('app/security.php', 'random_bytes'), 'CSRF tokens use cryptographically secure randomness');
check(source_contains('app/security.php', 'hash_equals'), 'CSRF validation uses constant-time comparison');
check(source_contains('app/security.php', 'htmlspecialchars'), 'HTML output is escaped centrally');

$authorizationChecks = [
    'users.php' => "require_permission('manage_users'",
    'deleted-tickets.php' => "role_slug'] ?? '') !== 'super-admin'",
    'restore-ticket.php' => "role_slug'] ?? '') !== 'super-admin'",
    'audit-log.php' => "role_slug'] ?? '') !== 'super-admin'",
    'calendar-admin.php' => 'Super Admin access required.',
    'health.php' => 'Super Admin access required.',
    'bulk-tickets.php' => "require_permission('bulk_ticket_actions'",
    'import.php' => "require_permission('manage_users'",
];
foreach ($authorizationChecks as $file => $marker) {
    check(source_contains($file, $marker), "authorization boundary present: {$file}");
}
check(source_contains('restore-ticket.php', 'verify_csrf'), 'ticket restore requires CSRF validation');
check(source_contains('calendar-admin.php', 'verify_csrf'), 'calendar administration requires CSRF validation');
check(source_contains('create-ticket.php', "require_permission('create_tickets'"), 'ticket creation route requires the create_tickets permission');
check(source_contains('app/tickets.php', "role_slug'] ?? '') !== 'team-leader'"), 'Team Leader ticket scope is limited to assigned tickets');
check(source_contains('admin.php', "role_slug'] ?? '') !== 'super-admin'"), 'role permission changes require Super Admin');
check(source_contains('admin.php', 'token_hash'), 'API tokens are stored by hash');
check(source_contains('api/feed.php', 'hash(\'sha256\''), 'API feed validates hashed tokens');
check(source_contains('ticket.php', 'unlink(ROOT_PATH'), 'failed attachment metadata inserts remove orphaned files');
check(source_contains('edit-ticket.php', "user_can('close_tickets')"), 'ticket edit close transitions require close_tickets');
check(source_contains('ticket.php', '$_POST[\'status\']'), 'ticket workflow uses submitted status');
check(source_contains('users.php', 'At least one active Super Admin must remain.'), 'user management protects the last active Super Admin');
check(source_contains('users.php', 'Only a Super Admin can assign the Super Admin role.'), 'user management protects Super Admin role assignment');
check(source_contains('leave-requests.php', 'DateTimeImmutable'), 'leave dates are calendar-valid, not only regex-valid');
check(source_contains('leave-requests.php', 'unlink(ROOT_PATH'), 'failed leave attachment inserts remove orphaned files');
check(source_contains('import.php', 'count($row)!==count($expected)'), 'CSV import rejects malformed column counts');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
