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
$expectedNumbers = range(2, 22);
check($migrationNumbers === $expectedNumbers, 'migrations 002 through 022 are present without gaps');

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
    '022' => 'staff_directory',
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
check(source_contains('app/security.php', 'function request_string'), 'authentication input rejects array-valued request fields');
check(source_contains('app/bootstrap.php', "session.use_strict_mode"), 'sessions use strict mode');
check(source_contains('login.php', "approval_status']!=='approved'"), 'login rejects non-approved account states');
check(source_contains('app/layout.php', 'Skip to main content'), 'layout provides a keyboard skip link');
check(source_contains('app/layout.php', 'id="main-content"'), 'layout exposes a main-content landmark');
check(source_contains('app/layout.php', 'window.sessionStorage'), 'layout tolerates unavailable session storage');
check(source_contains('assets/css/app.css', 'prefers-reduced-motion'), 'UI honors reduced-motion preferences');
check(is_file($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . '.htaccess'), 'private storage has a web-server guard');
check(source_contains('storage/private/.htaccess', 'Require all denied'), 'private storage denies direct web access');
check(source_contains('api/feed.php', "request_string(\$_GET, 'token')"), 'API rejects array-valued token input');
check(source_contains('api/feed.php', ':ticket_search'), 'API search uses distinct prepared placeholders');
check(source_contains('api/feed.php', 'offset must be between 0 and 100000'), 'API bounds pagination offset');
check(source_contains('export-tickets.php', 'MAX_EXPORT_ROWS'), 'exports enforce a bounded row count');
check(source_contains('export-tickets.php', 'date_from cannot be after date_to'), 'exports reject reversed date ranges');
check(source_contains('health.php', 'Required database tables'), 'health page checks required database tables');
check(source_contains('health.php', 'Private storage web protection'), 'health page checks private storage protection');
check(source_contains('create-ticket.php', "'issue_escalator' => \$user['full_name']"), 'ticket escalator is captured from submitter');
check(!source_contains('create-ticket.php', 'name="issue_escalator"'), 'ticket escalator is not manually editable on create');
check(source_contains('app/tickets.php', 'TICKET_CATEGORY_DEPARTMENT_CODES'), 'ticket categories define department mappings');
check(source_contains('create-ticket.php', 'categoryDepartmentMap'), 'ticket create filters departments and assignees by category');
check(source_contains('edit-ticket.php', 'categoryDepartmentMap'), 'ticket edit filters departments and assignees by category');
check(source_contains('team-attendance.php', "'half_day' => 'Half-day'"), 'attendance uses requested leave statuses');
check(source_contains('team-attendance.php', "action === 'update'"), 'attendance records support editing');
check(source_contains('team-attendance.php', 'team_attendance_leave'), 'attendance saves multiple staff on leave');
check(source_contains('team-attendance.php', 'staff-picker'), 'attendance provides a staff checkbox picker');
check(source_contains('team-calendar.php', 'leave_staff'), 'calendar displays staff on leave with attendance');
check(source_contains('migrations/022_staff_leave_attendance_updates.sql', 'stratastaffglobal.com'), 'staff directory records TL email domain');
check(source_contains('migrations/022_staff_leave_attendance_updates.sql', 'jamesons.com.au'), 'staff directory records employee email domain');
check(source_contains('register.php', 'jamesons\\.com\\.au'), 'registration accepts Jamesons staff email domain');
check(source_contains('register.php', 'stratastaffglobal\\.com'), 'registration accepts Strata Staff Global TL email domain');
check(is_file($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'external_tickets.php'), 'external ticket adapters are present');
check(is_file($root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.external.example.php'), 'external credential template is present');
check(source_contains('app/external_tickets.php', 'PDO::ATTR_EMULATE_PREPARES'), 'external connections disable emulated prepares');
check(!preg_match('/\\b(?:INSERT|UPDATE|DELETE)\\b/i', (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'external_tickets.php')), 'external adapters contain no write SQL');
check(source_contains('index.php', 'external_ticket_load_all'), 'ticket register merges external sources');
check(source_contains('ticket.php', 'External tickets are read-only.'), 'external ticket detail blocks writes');
check(source_contains('export-tickets.php', 'external_ticket_matches_filters'), 'ticket exports include external source filtering');
check(source_contains('.gitignore', 'config.external.php'), 'external credentials are ignored by Git');
require_once $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'tickets.php';
$testDepartments = [['id' => 1, 'name' => 'R&M', 'code' => 'rm'], ['id' => 2, 'name' => 'Admin', 'code' => 'admin'], ['id' => 3, 'name' => 'Compliance', 'code' => 'compliance'], ['id' => 4, 'name' => 'Customer Care', 'code' => 'customer-care'], ['id' => 5, 'name' => 'Insurance', 'code' => 'insurance']];
check(count(category_department_ids('Attendance', $testDepartments)) === 5, 'Attendance category selects all departments');
check(category_department_ids('Resignation', $testDepartments) === [2], 'Resignation category selects Admin');
$testUsers = [['id' => 10, 'department_id' => 1, 'role_slug' => 'team-member'], ['id' => 11, 'department_id' => null, 'role_slug' => 'team-leader'], ['id' => 12, 'department_id' => 2, 'role_slug' => 'team-member']];
check(category_assignee_ids('Resignation', $testDepartments, $testUsers) === [11, 12], 'category assignee mapping keeps TLs and matching department users');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
