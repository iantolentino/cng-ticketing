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
    'login.php', 'forgot-password.php', 'reset-password.php', 'users.php', 'recruitment.php',
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
$expectedNumbers = range(2, 24);
check($migrationNumbers === $expectedNumbers, 'migrations 002 through 024 are present without gaps');

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
    '023' => 'team-member',
    '024' => 'manage_recruitment',
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
    'recruitment.php' => "user_can('manage_recruitment'",
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
check(source_contains('app/tickets.php', "if (\$role === 'team-member')"), 'Team Member ticket scope is handled explicitly');
check(source_contains('app/tickets.php', 't.created_by = :scope_user_id'), 'Team Member ticket lists are limited to tickets they created');
check(source_contains('app/tickets.php', "['team-leader', 'team-member']"), 'Ticket detail visibility covers Team Members and Team Leaders');
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
check(source_contains('reports.php', 'count($row) !== count($expected)'), 'CSV import rejects malformed column counts');
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
check(source_contains('team-attendance.php', 'attendance-staff-field'), 'attendance staff picker has a labeled field wrapper');
check(source_contains('team-calendar.php', 'leave_staff'), 'calendar displays staff on leave with attendance');
check(source_contains('migrations/022_staff_leave_attendance_updates.sql', 'stratastaffglobal.com'), 'staff directory records TL email domain');
check(source_contains('migrations/022_staff_leave_attendance_updates.sql', 'jamesons.com.au'), 'staff directory records employee email domain');
check(source_contains('register.php', 'jamesons\\.com\\.au'), 'registration accepts Jamesons staff email domain');
check(source_contains('register.php', 'stratastaffglobal\\.com'), 'registration accepts Strata Staff Global TL email domain');
check(source_contains('users.php', "create_team_members"), 'Super Admin Users page provides bulk Team Member setup');
check(source_contains('recruitment.php', 'hired_date'), 'Recruitment page provides hire dates');
check(source_contains('recruitment.php', 'shift_schedule'), 'Recruitment page provides shift schedules');
check(source_contains('recruitment.php', 'is_in_training'), 'Recruitment page provides training status');
check(source_contains('recruitment.php', 'position_title'), 'Recruitment page provides positions');
check(source_contains('recruitment.php', 'recruitment-card-grid'), 'Recruitment page uses the compact employee card grid');
check(source_contains('recruitment.php', 'recruitment-edit-icon'), 'Recruitment page provides employee edit icons');
check(source_contains('users.php', "team_member_credentials"), 'Team Member setup keeps temporary credentials in the Super Admin session only');
check(source_contains('users.php', 'must_change_password'), 'Team Member setup forces a password change');
check(source_contains('app/auth.php', 'require_non_team_member'), 'restricted workspace routes block Team Members');
check(source_contains('app/layout.php', "'change-password.php'"), 'authenticated users have a Settings password-change link');
check(source_contains('app/layout.php', 'data-account-menu-trigger'), 'authenticated users have an upper-right account menu trigger');
check(source_contains('app/layout.php', 'data-account-menu'), 'the account menu exposes Settings');
check(source_contains('app/layout.php', 'href="logout.php"'), 'the account menu exposes Sign out');
check(!source_contains('app/layout.php', '<div class="sidebar-footer">'), 'the sidebar no longer renders the account footer');
check(!source_contains('app/layout.php', "['settings', 'change-password.php'"), 'Settings is not rendered as a sidebar item');
check(source_contains('change-password.php', 'data-theme-choice="dark"'), 'Settings provides a dark theme choice');
check(source_contains('assets/js/app.js', 'cng-ticketing-theme'), 'theme selection uses browser-local persistence');
check(source_contains('assets/css/ui-fixes.css', 'scrollbar-width:none'), 'sidebar scrollbar is visually hidden');
check(source_contains('dashboard.php', "in_array(\$filters['trend_range'], ['7d', '30d']"), 'short dashboard trends use daily buckets');
check(source_contains('health.php', 'health-log-table'), 'health log table has dedicated layout columns');
check(!source_contains('index.php', 'external-source-notice'), 'ticket register hides transient external-source warning text');
check(!source_contains('department-workload.php', 'external-source-notice'), 'department workload hides transient external-source warning text');
foreach (['calendar-admin.php', 'users.php', 'recruitment.php', 'audit-log.php', 'health.php'] as $pageWithoutRedundantHeader) {
    check(!source_contains($pageWithoutRedundantHeader, 'class="page-head"><div><p class="eyebrow">'), $pageWithoutRedundantHeader . ' omits the redundant main-content page heading block');
}
check(source_contains('users.php', '<div class="page-actions"><button'), 'Users keeps the New user action after removing the redundant heading');
check(source_contains('recruitment.php', '<div class="page-actions">'), 'Recruitment keeps the team-member count after removing the redundant heading');
check(is_file($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'external_tickets.php'), 'external ticket adapters are present');
check(is_file($root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.external.example.php'), 'external credential template is present');
check(source_contains('app/external_tickets.php', 'PDO::ATTR_EMULATE_PREPARES'), 'external connections disable emulated prepares');
check(!preg_match('/\\b(?:INSERT|UPDATE|DELETE)\\b/i', (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'external_tickets.php')), 'external adapters contain no write SQL');
check(source_contains('app/external_tickets.php', 'function external_department_label'), 'external sources have an explicit department mapping');
check(source_contains('index.php', 'external_ticket_load_all'), 'ticket register merges external sources');
check(source_contains('index.php', 'tickets-control-grid'), 'ticket filters and bulk actions share a compact control layout');
check(source_contains('index.php', 'class="bulk-actions-panel"'), 'bulk actions retain a dedicated compact panel');
check(source_contains('assets/css/ui-fixes.css', 'bulk-actions-form'), 'bulk actions use compact baseline control sizing');
check(source_contains('ticket.php', 'External tickets are read-only.'), 'external ticket detail blocks writes');
check(source_contains('ticket.php', 'Back to tickets'), 'ticket detail provides a return link to the ticket register');
check(source_contains('ticket.php', 'external_thread_body_html'), 'external conversation bodies use safe HTML formatting');
check(source_contains('app/external_tickets.php', 'DOMDocument'), 'external conversation sanitizer parses HTML');
check(source_contains('config/config.external.example.php', 'escalations.stratastaffglobal.com'), 'external source links are configurable');
check(source_contains('export-tickets.php', 'external_ticket_matches_filters'), 'ticket exports include external source filtering');
check(source_contains('.gitignore', 'config.external.php'), 'external credentials are ignored by Git');
require_once $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'external_tickets.php';
$safeThreadHtml = external_thread_body_html('<p><strong>Name:</strong> Stefani</p><script>alert(1)</script><p><a href="javascript:alert(1)">Details</a></p>');
check(str_contains($safeThreadHtml, '<strong>Name:</strong>') && str_contains($safeThreadHtml, 'Stefani'), 'external conversation preserves safe formatting');
check(!str_contains(strtolower($safeThreadHtml), '<script') && !str_contains(strtolower($safeThreadHtml), 'javascript:'), 'external conversation removes unsafe markup and links');
check(external_source_url('stratast_escalations', []) === 'https://escalations.stratastaffglobal.com/', 'Escalations uses its configured source link');
check(external_source_url('stratast_support', []) === 'http://support.stratastaffglobal.com/', 'IT Department uses its configured source link');
check(external_source_url('stratast_wp346', []) === 'https://learning.stratastaffglobal.com/', 'Learning uses its configured source link');
check(external_source_url('stratast_requisition', []) === 'https://requisition.stratastaffglobal.com/', 'Requisition uses its configured source link');
check(external_department_label('stratast_support') === 'IT Department', 'support tickets map to IT Department');
check(external_department_label('stratast_escalations') === 'HR Department', 'escalation tickets map to HR Department');
check(external_department_label('stratast_requisition') === 'Finance Department', 'requisition tickets map to Finance Department');
check(external_department_label('stratast_wp346') === 'L&D', 'training desk tickets map to L&D');
check(source_contains('dashboard.php', '$dashboardOtherStatusCount'), 'dashboard totals include external statuses outside the native four');
check(source_contains('department-workload.php', 'external_ticket_load_all'), 'department workload includes external tickets');
require_once $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'tickets.php';
$testDepartments = [['id' => 1, 'name' => 'R&M', 'code' => 'rm'], ['id' => 2, 'name' => 'Admin', 'code' => 'admin'], ['id' => 3, 'name' => 'Compliance', 'code' => 'compliance'], ['id' => 4, 'name' => 'Customer Care', 'code' => 'customer-care'], ['id' => 5, 'name' => 'Insurance', 'code' => 'insurance']];
check(count(category_department_ids('Attendance', $testDepartments)) === 5, 'Attendance category selects all departments');
check(category_department_ids('Resignation', $testDepartments) === [2], 'Resignation category selects Admin');
$testUsers = [['id' => 10, 'department_id' => 1, 'role_slug' => 'team-member'], ['id' => 11, 'department_id' => null, 'role_slug' => 'team-leader'], ['id' => 12, 'department_id' => 2, 'role_slug' => 'team-member']];
check(category_assignee_ids('Resignation', $testDepartments, $testUsers) === [11, 12], 'category assignee mapping keeps TLs and matching department users');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
