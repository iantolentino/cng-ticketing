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
$expectedNumbers = range(2, 32);
check($migrationNumbers === $expectedNumbers, 'migrations 002 through 032 are present without gaps');

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
    '025' => 'cng-admin',
    '026' => 'employment_status',
    '027' => 'disabled_at',
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
    'recruitment.php' => 'Super Admin access required.',
    'calendar-admin.php' => 'Super Admin access required.',
    'health.php' => 'Super Admin access required.',
    'bulk-tickets.php' => "require_permission('bulk_ticket_actions'",
    'import.php' => "require_permission('manage_users'",
    'export-tickets.php' => "require_permission('export_tickets'",
];
foreach ($authorizationChecks as $file => $marker) {
    check(source_contains($file, $marker), "authorization boundary present: {$file}");
}
check(source_contains('restore-ticket.php', 'verify_csrf'), 'ticket restore requires CSRF validation');
check(source_contains('calendar-admin.php', 'verify_csrf'), 'calendar administration requires CSRF validation');
check(source_contains('create-ticket.php', "require_permission('create_tickets'"), 'ticket creation route requires the create_tickets permission');
check(source_contains('app/tickets.php', "if (\$role === 'team-member')"), 'Team Member ticket scope is handled explicitly');
check(source_contains('app/tickets.php', 't.created_by = :scope_user_id'), 'Team Member ticket lists are limited to tickets they created');
check(source_contains('app/tickets.php', "if (\$role !== 'team-member') return;"), 'Only Team Members have restricted ticket detail visibility');
check(source_contains('admin.php', "role_slug'] ?? '') !== 'super-admin'"), 'role permission changes require Super Admin');
check(source_contains('admin.php', "isset(\$_POST['granted']) ? 1 : 0"), 'Super Admin permission toggles persist On and Off states');
check(source_contains('app/auth.php', 'COALESCE(uo.granted, rp.granted, 0)'), 'permission checks use the saved role grant and user override');
check(source_contains('admin.php', 'token_hash'), 'API tokens are stored by hash');
check(source_contains('api/feed.php', 'hash(\'sha256\''), 'API feed validates hashed tokens');
check(source_contains('ticket.php', 'unlink(ROOT_PATH'), 'failed attachment metadata inserts remove orphaned files');
check(source_contains('edit-ticket.php', "user_can('close_tickets')"), 'ticket edit close transitions require close_tickets');
check(source_contains('ticket.php', '$_POST[\'status\']'), 'ticket workflow uses submitted status');
check(source_contains('users.php', 'At least one active Super Admin must remain.'), 'user management protects the last active Super Admin');
check(source_contains('users.php', 'Only a Super Admin can assign the Super Admin role.'), 'user management protects Super Admin role assignment');
check(source_contains('leave-requests.php', 'DateTimeImmutable'), 'leave dates are calendar-valid, not only regex-valid');
check(source_contains('leave-requests.php', 'unlink(ROOT_PATH'), 'failed leave attachment inserts remove orphaned files');
check(source_contains('app/report_csv.php', 'count($row) !== count($expected)'), 'CSV import rejects malformed column counts');
check(source_contains('app/security.php', 'function request_string'), 'authentication input rejects array-valued request fields');
check(source_contains('app/bootstrap.php', "session.use_strict_mode"), 'sessions use strict mode');
check((bool) preg_match("~\\['approval_status'\\]\\s*!==\\s*'approved'~", (string) file_get_contents($root . '/login.php')), 'login rejects non-approved account states');
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
check(source_contains('export-tickets.php', "\$filters['employees']"), 'ticket exports accept multiple employee filters');
check(source_contains('export-tickets.php', 'employee_name IN'), 'ticket exports filter the native ticket list by employees');
check(source_contains('export-tickets.php', "\$filters['department_name']"), 'ticket exports map department filters for external records');
check(source_contains('health.php', 'Required database tables'), 'health page checks required database tables');
check(source_contains('health.php', 'Private storage web protection'), 'health page checks private storage protection');
check(source_contains('create-ticket.php', "'issue_escalator' => \$user['full_name']"), 'ticket escalator is captured from submitter');
check(!source_contains('create-ticket.php', 'name="issue_escalator"'), 'ticket escalator is not manually editable on create');
check(source_contains('app/tickets.php', 'TICKET_CATEGORY_DEPARTMENT_CODES'), 'ticket categories define department mappings');
check(source_contains('create-ticket.php', 'categoryDepartmentMap'), 'ticket create filters departments and assignees by category');
check(source_contains('edit-ticket.php', 'categoryDepartmentMap'), 'ticket edit filters departments and assignees by category');
check(source_contains('create-ticket.php', "subcategory.dispatchEvent(new Event('ui-options-changed'))"), 'ticket create refreshes dependent subcategory options');
check(source_contains('edit-ticket.php', "subcategory.dispatchEvent(new Event('ui-options-changed'))"), 'ticket edit refreshes dependent subcategory options');
check(source_contains('create-ticket.php', 'value="<?= e($values[\'subject\'] ?? \'\') ?>"'), 'ticket create preserves entered values after validation errors');
check(source_contains('app/roster.php', "'half_day' => 'Half-day'"), 'attendance uses requested leave statuses');
check(source_contains('team-attendance.php', 'ROSTER_LEAVE_TYPES'), 'Team Attendance offers the shared leave statuses');
// The department attendance log is retired: the logging form and its per-date table
// are gone, its history stays readable, and the summary is driven by the roster plus
// the Team Calendar leave entries instead.
check(!source_contains('team-attendance.php', 'attendance-form-panel'), 'department attendance logging is retired');
check(!source_contains('team-attendance.php', 'Log department attendance'), 'the department attendance form is gone');
check(source_contains('team-attendance.php', 'Recent department logs'), 'department attendance history stays readable');
check(source_contains('team-attendance.php', 'team_attendance_leave'), 'department attendance history still resolves its staff list');
check(source_contains('team-attendance.php', 'headcountStaff'), 'attendance headcount counts the working roster');
check(source_contains('team-attendance.php', 'roster_leave_rows'), 'attendance on-leave comes from the Team Calendar');
check(source_contains('team-attendance.php', 'attendance-roster-card'), 'attendance lists staff as selectable cards');
check(source_contains('team-attendance.php', 'data-employment-status'), 'attendance sets employment status per staff card');
// The staff editor is a modal, and leave is filed only on the Team Calendar, so the
// two screens cannot hold competing copies of the same leave.
check(source_contains('team-attendance.php', 'attendance-staff-modal'), 'the staff editor opens as a modal');
check(source_contains('team-attendance.php', 'aria-modal="true"'), 'the staff modal is announced as a dialog');
check(source_contains('team-attendance.php', 'data-attendance-modal-close'), 'the staff modal can be closed');
check(source_contains('team-attendance.php', "event.key === 'Escape'"), 'the staff modal closes on Escape');
check(!source_contains('team-attendance.php', 'attendance-leave-form'), 'Team Attendance no longer files leave');
check(!source_contains('team-attendance.php', 'attendance-leave-table'), 'Team Attendance no longer keeps its own leave table');
check(!source_contains('team-attendance.php', "'save_leave'"), 'Team Attendance no longer handles save_leave');
check(!source_contains('team-attendance.php', "'delete_leave'"), 'Team Attendance no longer handles delete_leave');
check(source_contains('team-attendance.php', 'Manage leave on the Team Calendar'), 'Team Attendance points at the Team Calendar for leave');
check(source_contains('team-calendar.php', "'save_leave'"), 'the Team Calendar owns leave filing');
check(source_contains('team-calendar.php', "'delete_leave'"), 'the Team Calendar owns leave removal');
check(source_contains('team-attendance.php', 'days_off[]') && source_contains('team-attendance.php', 'roster_shift_days_from_day_off_post'), 'Team Attendance derives editable working days from days off');
check(source_contains('team-attendance.php', 'attendance-roster-panel'), 'Team Attendance exposes the staff roster panel');
check(!source_contains('team-attendance.php', "=== 'cng-admin'"), 'CNG Admin is not hard-blocked from Team Attendance');
check(source_contains('app/roster.php', 'roster_leave_save'), 'staff leave is stored once for attendance and the calendar');
check(source_contains('app/roster.php', 'roster_can_manage_attendance'), 'attendance management is capability-gated');
check(source_contains('app/roster.php', 'ROSTER_STATUSES'), 'roster tracks resigned, pulled-out, and replacement states');
check(source_contains('migrations/026_roster_leave_and_staff_status.sql', 'uq_calendar_events_staff_leave_start'), 'linked staff leave cannot duplicate a start date and type');
check(source_contains('app/roster.php', 'roster_event_load'), 'an existing calendar event can be converted into staff leave');
check(source_contains('team-calendar.php', 'leave_staff'), 'calendar displays staff on leave with attendance');
check(source_contains('migrations/022_staff_leave_attendance_updates.sql', 'stratastaffglobal.com'), 'staff directory records TL email domain');
check(source_contains('migrations/022_staff_leave_attendance_updates.sql', 'jamesons.com.au'), 'staff directory records employee email domain');
check(source_contains('register.php', "'jamesons.com.au'"), 'registration accepts Jamesons staff email domain');
check(source_contains('register.php', "'stratastaffglobal.com'"), 'registration accepts Strata Staff Global TL email domain');
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
check(source_contains('app/layout.php', 'header-settings-link') && source_contains('app/layout.php', 'href="change-password.php"'), 'authenticated users have a direct Settings icon link');
check(source_contains('app/layout.php', 'header-signout-link') && source_contains('app/layout.php', 'href="logout.php"'), 'authenticated users have a direct Sign out icon link');
check(source_contains('app/layout.php', '<div class="sidebar-footer"') && source_contains('app/layout.php', 'sidebar-account-copy'), 'the sidebar footer shows the signed-in account identity');
check(!source_contains('login.php', 'ACCOUNT ACCESS'), 'login card omits the redundant access eyebrow');
check(!source_contains('login.php', 'Access the CNG / Jamesons ticketing portal.'), 'login card omits the redundant portal subtitle');
check(!source_contains('login.php', 'Access is managed through your assigned role.'), 'login card omits the redundant role footnote');
check(source_contains('assets/css/app.css', '.auth-strata-logo{width:122px;max-width:122px;max-height:28px}'), 'login Strata Staff logo is aligned with the Jamesons logo');
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
check(source_contains('index.php', 'class="bulk-actions-menu"'), 'bulk actions are inside the ticket filter toolbar');
check(source_contains('index.php', 'action="bulk-tickets.php"'), 'bulk actions keep their existing endpoint');
check(source_contains('index.php', 'name="csrf_token"'), 'bulk actions keep CSRF protection');
check(source_contains('assets/css/ui-fixes.css', 'bulk-actions-form'), 'bulk actions use compact horizontal control sizing');
check(source_contains('index.php', 'data-bulk-ticket-checkbox'), 'native ticket rows provide bulk-selection checkboxes');
check(source_contains('index.php', 'Select visible'), 'bulk actions provide visible-row selection');
check(source_contains('assets/js/app.js', 'syncSelectedInputs'), 'selected rows sync into the existing bulk form');
check(source_contains('assets/js/app.js', 'bulk-selection-mode'), 'bulk row selection mode follows the bulk-actions menu');
check(source_contains('assets/css/ui-fixes.css', '.bulk-selection-mode input[type="checkbox"].bulk-ticket-checkbox'), 'bulk row checkboxes appear only in bulk-selection mode');
check(source_contains('index.php', 'name="scope"'), 'ticket ownership scope is preserved in the Tickets filter');
check(source_contains('index.php', '$mineScopeUrl'), 'Mine filters the current Tickets page');
check(source_contains('app/tickets.php', 'string $scope ='), 'ticket scope helper accepts the in-place Mine filter');
check(!source_contains('app/layout.php', "['work', 'my-work.php'"), 'My Work is removed from the sidebar');
check(source_contains('calendar-admin.php', "action === 'edit'"), 'Calendar Administration retains unique edit controls');
check(source_contains('calendar-admin.php', "action === 'delete'"), 'Calendar Administration retains unique delete controls');
check(source_contains('ticket.php', 'External tickets are read-only.'), 'external ticket detail blocks writes');
check(source_contains('ticket.php', 'Back to tickets'), 'ticket detail provides a return link to the ticket register');
check(source_contains('ticket.php', 'M19 12H5M12 19l-7-7 7-7'), 'ticket detail back link uses the shared arrow icon treatment');
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
$testUsers = [['id' => 10, 'department_id' => 1, 'role_slug' => 'team-member','is_assignable'=>false], ['id' => 11, 'department_id' => null, 'role_slug' => 'team-leader','is_assignable'=>true], ['id' => 12, 'department_id' => 2, 'role_slug' => 'team-member','is_assignable'=>false], ['id'=>13,'department_id'=>null,'role_slug'=>'super-admin','is_assignable'=>true]];
check(category_assignee_ids('Resignation', $testDepartments, $testUsers) === [11, 13], 'assignee choices retain TL and approved admin roles and exclude Team Members');
$teamLeaderScopeParams = [];
check(str_contains(ticket_scope_sql(['id' => 11, 'role_slug' => 'team-leader'], $teamLeaderScopeParams), 'NOT IN') && array_values($teamLeaderScopeParams) === ticket_visibility_ids(), 'Team Leaders see the full ticket register by default');
$teamLeaderMineParams = [];
check(str_contains(ticket_scope_sql(['id' => 11, 'role_slug' => 'team-leader'], $teamLeaderMineParams, 'mine'), 't.assignee_id') && count($teamLeaderMineParams) === count(ticket_visibility_ids()) + 2, 'Team Leader Mine scope remains assignment-based');
check(external_ticket_visible_to_user(['agent' => 'Another Agent'], ['role_slug' => 'team-leader', 'full_name' => 'Codex TL Test']), 'Team Leaders see all external tickets');
check(!external_ticket_visible_to_user(['agent' => 'Another Agent'], ['role_slug' => 'team-member']), 'Team Members remain excluded from external tickets');
check(source_contains('migrations/024_recruitment_directory.sql', "'team-leader'"), 'Team Leaders retain Recruitment permission');
check(source_contains('migrations/025_cng_admin_recruitment_export_permissions.sql', "'manage_recruitment'"), 'CNG Admin receives Recruitment and export permissions');
check(!source_contains('recruitment.php', "'cng-admin'"), 'Recruitment access follows the manage_recruitment permission for CNG Admin');
check(!source_contains('index.php', 'Export Tickets CSV'), 'Ticket page does not render export controls');
check(source_contains('reports.php', 'Export Tickets CSV'), 'Ticket CSV export is available from Reports & CSV');
check(source_contains('reports.php', 'Export Tickets Excel'), 'Ticket Excel export is available from Reports & CSV');
check(source_contains('reports.php', 'name="employees[]"'), 'Reports & CSV provides multiple employee selection');
check(source_contains('reports.php', 'All employees'), 'Reports & CSV provides an all-employees option');
check(source_contains('assets/js/app.js', 'reportEmployeeSelect'), 'Reports employee selection keeps All employees exclusive');
check(source_contains('reports.php', 'external_ticket_load_all'), 'Reports employee options include external ticket requesters');
check(source_contains('reports.php', 'name="department"'), 'Reports & CSV provides department selection');
check(source_contains('reports.php', '$selectedEmployees'), 'Reports & CSV preserves employee export filters');
check(source_contains('app/layout.php', "\$canViewCalendar = \$user && user_can('view_all_tickets')"), 'CNG Admin and Team Members both receive the Team Calendar navigation item');
check(source_contains('app/layout.php', '$canViewAttendance = $canViewCalendar'), 'Team Attendance remains independently restricted from Team Members');
check(source_contains('team-calendar.php', '$canManageCalendar'), 'Team Calendar distinguishes read-only viewers from calendar managers');
check(source_contains('team-calendar.php', 'leave-requests.php?start_date='), 'Team Member calendar dates link to a preselected leave request');
check(source_contains('team-calendar.php', 'permission to update the team calendar'), 'Read-only calendar viewers cannot submit calendar changes');
check(source_contains('team-calendar.php', 'calendar-day-card'), 'clicking a calendar date opens the add-entry card');
check(source_contains('team-calendar.php', 'calendar-day-card-kind'), 'the date card offers holiday, company event, and leave');
check(source_contains('team-calendar.php', 'calendar-modal-leave'), 'the date card opens a leave modal with staff and dates');
check(source_contains('team-calendar.php', 'data-calendar-edit'), 'calendar entries offer an edit action');
check(source_contains('team-calendar.php', "action=delete_leave") || source_contains('team-calendar.php', "value=\"delete_leave\""), 'calendar leave entries can be deleted');
check(source_contains('leave-requests.php', '$requestedStartDate'), 'Leave requests accept a validated calendar date selection');
check(source_contains('leave-requests.php', 'File leave for a staff member'), 'Team Leaders can file leave on behalf of a staff member');
check(!source_contains('leave-requests.php', 'leave-filter-bar'), 'Leave requests no longer filter the list by department');
check(!source_contains('leave-requests.php', '$departmentFilter'), 'the leave department filter plumbing is gone');
check(source_contains('leave-requests.php', "\$roleSlug === 'team-leader'"), 'My requests is limited to Team Leader accounts');
check(source_contains('leave-requests.php', 'leave_request_decide') && source_contains('app/leave_workflow.php', 'roster_leave_save'), 'final Team Leader approval creates the linked shared staff leave entry');
check(source_contains('index.php', 'name="requestor"'), 'Tickets filter by requestor');
check(source_contains('dashboard.php', 'data-date-range-picker'), 'Dashboard uses a calendar start and end date range');
check(source_contains('department-workload.php', 'department-filter-bar'), 'Departments filter each workload category');
check(source_contains('recruitment.php', 'employment_status'), 'Recruitment marks staff as resigned, pulled out, or for replacement');
check(source_contains('recruitment.php', 'replacement_for_id'), 'Recruitment records who a replacement covers');
check(source_contains('assets/js/app.js', "'requestor'"), 'ticket filter actions react to the requestor filter');
check(source_contains('app/layout.php', "\$canImportReports ? 'Reports & CSV import' : 'Reports'"), 'Reports navigation reflects each role’s import capability');
check(source_contains('reports.php', '$canExportReports'), 'Report exports are capability-gated');
check(source_contains('reports.php', '$canImportReports'), 'CSV import controls are capability-gated');
check(source_contains('migrations/027_user_disabled_at.sql', 'disabled_at'), 'migration 027 records when an account was disabled');
check(source_contains('users.php', 'user_active_state_update_sql'), 'Users writes the disable timestamp alongside the enabled state');
check(source_contains('users.php', 'Only a Super Admin can enable or disable an account.'), 'Users hides account enabling from non-Super-Admin viewers');
check(source_contains('users.php', "\$canApproveAccounts): ?><th>Last updated</th>"), 'Users shows the last-updated and last-disabled columns to Super Admin only');
check(source_contains('users.php', "\$canApproveAccounts): ?><span class=\"pill pill-"), 'Users replaces Inactive with Disabled for Super Admin only');
check(!source_contains('users.php', 'These are the 70 employee rows from the staff list.'), 'Team Member setup omits the retired explainer paragraph');
check(source_contains('users.php', 'data-team-member-row'), 'Team Member setup lists one searchable card per staff member');
check(source_contains('users.php', 'id="team-member-search"'), 'Team Member setup provides a staff search box');
check(source_contains('users.php', 'row.hidden = !match'), 'Team Member search hides rows instead of removing the submitted fields');
check(source_contains('users.php', '$teamMemberDirectory && $canApproveAccounts'), 'Team Member setup is Super Admin only');
check(source_contains('users.php', "notice = 'User deleted.'"), 'Users keeps the delete account action');
check(source_contains('users.php', "if (\$action === 'delete' || \$action === 'activate')"), 'deleting an account is limited to the Super Admin');
check(source_contains('users.php', "Reset password</a><?php if (\$canApproveAccounts): ?><form method=\"post\""), 'the Delete button is hidden from non-Super-Admin viewers');
check(source_contains('app/notifications.php', 'notification_recipient_is_active'), 'notifications skip disabled or deleted recipients');
check(source_contains('app/layout.php', "'Team Attendance'"), 'attendance navigation uses the requested Team Attendance label');
check(source_contains('app/roster.php', "'resigned' => 'Resigned'"), 'the roster offers a resigned employment status');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
