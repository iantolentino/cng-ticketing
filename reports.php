<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';
require __DIR__ . '/app/external_tickets.php';
require __DIR__ . '/app/layout.php';
require __DIR__ . '/app/report_csv.php';
require __DIR__ . '/app/report_workload.php';

$user = require_permission('view_all_tickets');
require_non_team_member($user);
$canExportReports = user_can('export_tickets');
$canImportReports = user_can('manage_users');
$pdo = db();
$hasDepartmentTypes = app_schema_has('departments', 'department_type', $pdo);
$error = '';
$preview = $_SESSION['csv_preview'] ?? null;
$types = ['users' => 'Users', 'departments' => 'Departments', 'holidays' => 'Holidays'];
if (($user['role_slug'] ?? '') !== 'super-admin') unset($types['users']);
$headers = [
    'users' => ['full_name', 'username', 'email', 'role', 'department'],
    'departments' => ['name', 'code', 'department_type'],
    'holidays' => ['date', 'label', 'country_code'],
];
if (!$hasDepartmentTypes) $headers['departments'] = ['name', 'code'];

$importNotice = $_SESSION['csv_import_notice'] ?? '';
unset($_SESSION['csv_import_notice']);
$importCredentials = $_SESSION['csv_import_credentials'] ?? [];
unset($_SESSION['csv_import_credentials']);
if (!is_array($preview) || !isset($types[$preview['type'] ?? '']) || !isset($preview['token'])) {
    $preview = null;
    unset($_SESSION['csv_preview']);
}
$selectedImportType = request_string($_POST, 'type') ?: ($preview['type'] ?? 'users');
if (!isset($types[$selectedImportType])) $selectedImportType = array_key_first($types);
$templateType = request_string($_GET, 'csv_template');
if ($templateType !== '') {
    if (!$canImportReports) { http_response_code(403); exit('You do not have permission to download import templates.'); }
    if (!isset($types[$templateType])) { http_response_code(400); exit('Choose a supported import type.'); }
    audit_admin_action((int) $user['id'], 'import_template_downloaded', 'csv_template', null, ['type' => $templateType]);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $templateType . '-import-template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers[$templateType]);
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canImportReports) { http_response_code(403); exit('You do not have permission to access this action.'); }
    verify_csrf();
    $action = request_string($_POST, 'action');
    $type = request_string($_POST, 'type');
    if ($action === 'preview') {
        unset($_SESSION['csv_preview']);
        $preview = null;
        $file = $_FILES['csv'] ?? [];
        if (!isset($types[$type])) {
            $error = 'Choose Users, Departments or Holidays. Ticket import is not available.';
        } elseif (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = in_array($file['error'] ?? UPLOAD_ERR_NO_FILE, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'The file exceeds the server upload limit. Choose a smaller CSV file (maximum 5 MB).'
                : 'The upload did not complete. Choose a CSV file and try again.';
        } elseif (($file['size'] ?? 0) > 5 * 1024 * 1024 || strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION)) !== 'csv') {
            $error = 'Choose a .csv file no larger than 5 MB.';
        } elseif (!is_uploaded_file($file['tmp_name'] ?? '') || !($handle = fopen($file['tmp_name'], 'rb'))) {
            $error = 'The uploaded file could not be read. Please upload it again.';
        } else {
            try {
                $preview = report_csv_preview($handle, $type, $headers[$type]);
            } finally {
                fclose($handle);
            }
            $preview['token'] = bin2hex(random_bytes(16));
            $preview['filename'] = basename($file['name']);
            $_SESSION['csv_preview'] = $preview;
        }
    } elseif (in_array($action, ['import', 'cancel'], true)) {
        if (!$preview || !hash_equals($preview['token'], request_string($_POST, 'preview_token'))) {
            $error = 'This preview has expired or was replaced in another tab. Validate your file again.';
        } elseif ($action === 'cancel') {
            unset($_SESSION['csv_preview']);
            $preview = null;
            $importNotice = 'Preview discarded. No records were imported.';
        } elseif (empty($preview['rows']) || !empty($preview['errors'])) {
            $error = 'Correct all validation errors and upload the file again before importing.';
        } else {
            try {
                $pdo->beginTransaction();
                $createdCredentials = [];
                foreach ($preview['rows'] as $row) {
                    if ($preview['type'] === 'departments') {
                        if ($hasDepartmentTypes) $pdo->prepare('INSERT INTO departments(name,code,department_type) VALUES(?,?,?)')->execute([$row['name'], $row['code'], $row['department_type']]);
                        else $pdo->prepare('INSERT INTO departments(name,code) VALUES(?,?)')->execute([$row['name'], $row['code']]);
                    }
                    elseif ($preview['type'] === 'holidays') $pdo->prepare('INSERT INTO company_holidays(`date`,label,country_code,holiday_type) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE label=VALUES(label),holiday_type=VALUES(holiday_type)')->execute([$row['date'], $row['label'], $row['country_code'] ?: 'COMPANY', $row['country_code'] === 'COMPANY' ? 'company' : 'public']);
                    elseif ($preview['type'] === 'users') {
                        if (($user['role_slug'] ?? '') !== 'super-admin') throw new InvalidArgumentException('Only Super Admin can import user accounts and assign roles.');
                        $duplicatePerson = $pdo->prepare('SELECT id FROM users WHERE LOWER(TRIM(full_name))=LOWER(?) LIMIT 1');
                        $duplicatePerson->execute([$row['full_name']]);
                        if ($duplicatePerson->fetchColumn()) throw new InvalidArgumentException('This person already has an account. Update the existing account instead.');
                        $role = $pdo->prepare('SELECT id FROM roles WHERE slug=?');
                        $role->execute([$row['role']]);
                        $roleId = (int) $role->fetchColumn();
                        if (!$roleId) throw new RuntimeException('Unknown role: ' . $row['role']);
                        $dept = $pdo->prepare('SELECT id FROM departments WHERE code=?');
                        $dept->execute([$row['department']]);
                        $deptId = $dept->fetchColumn() ?: null;
                        $temporary = strtoupper(bin2hex(random_bytes(8)));
                        $pdo->prepare('INSERT INTO users(role_id,department_id,username,full_name,email,password_hash,must_change_password) VALUES(?,?,?,?,?,?,1)')->execute([$roleId, $deptId, $row['username'], $row['full_name'], $row['email'], password_hash($temporary, PASSWORD_DEFAULT)]);
                        $createdCredentials[] = ['username'=>$row['username'], 'password'=>$temporary];
                    }
                }
                $importedCount = count($preview['rows']);
                $importedType = $preview['type'];
                audit_admin_action((int) $user['id'], 'csv_imported', $importedType, null, ['rows' => $importedCount]);
                $pdo->commit();
                if ($createdCredentials) $_SESSION['csv_import_credentials'] = $createdCredentials;
                unset($_SESSION['csv_preview']);
                $preview = null;
                $_SESSION['csv_import_notice'] = 'Import complete: ' . $importedCount . ' ' . strtolower($types[$importedType]) . ' records processed successfully.';
                $returnQuery = http_build_query(array_intersect_key($_GET, array_flip(['from', 'to', 'department', 'employees'])));
                redirect('reports.php' . ($returnQuery !== '' ? '?' . $returnQuery : '') . '#csv-import');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'No records were imported. Check for duplicate usernames or department codes, and confirm referenced roles and departments exist. Correct the file and validate again.';
            }
        }
    } else {
        $error = 'Choose Validate CSV to review a file before importing.';
    }
}
$reportTimezone = new DateTimeZone(APP_DISPLAY_TIMEZONE);
$defaultReportFrom = (new DateTimeImmutable('first day of this month', $reportTimezone))->format('Y-m-d');
$defaultReportTo = (new DateTimeImmutable('today', $reportTimezone))->format('Y-m-d');
$from = ticket_valid_date(trim((string) ($_GET['from'] ?? ''))) ?: $defaultReportFrom;
$to = ticket_valid_date(trim((string) ($_GET['to'] ?? ''))) ?: $defaultReportTo;
$reportWindow = ticket_window_bounds($from, $to, 'all');
$from = $reportWindow['from'];
$to = $reportWindow['to'];
$reportDepartments = active_internal_departments();
$reportDepartmentIds = array_map('intval', array_column($reportDepartments, 'id'));
$selectedDepartment = trim((string) ($_GET['department'] ?? ''));
if (!ctype_digit($selectedDepartment) || !in_array((int) $selectedDepartment, $reportDepartmentIds, true)) $selectedDepartment = '';
$employeeParams = [];
$employeeVisibility = ticket_visibility_sql($employeeParams);
$employeeQuery = $pdo->prepare("SELECT DISTINCT employee_name FROM tickets t WHERE deleted_at IS NULL AND TRIM(employee_name) <> ''" . $employeeVisibility . ' ORDER BY employee_name');
$employeeQuery->execute($employeeParams);
$reportEmployeeOptions = array_map('strval', $employeeQuery->fetchAll(PDO::FETCH_COLUMN));
$reportExternalTickets = external_ticket_load_all($user);
foreach ($reportExternalTickets['tickets'] ?? [] as $externalTicket) {
    $employeeName = trim((string) ($externalTicket['employee_name'] ?? ''));
    if ($employeeName !== '' && $employeeName !== '—') $reportEmployeeOptions[] = $employeeName;
}
$reportEmployeeOptions = array_values(array_unique($reportEmployeeOptions));
natcasesort($reportEmployeeOptions);
$reportEmployeeOptions = array_values($reportEmployeeOptions);
$selectedEmployees = $_GET['employees'] ?? [];
if (!is_array($selectedEmployees)) $selectedEmployees = [$selectedEmployees];
$selectedEmployees = array_values(array_unique(array_filter(array_map(static fn($value): string => is_scalar($value) ? trim((string) $value) : '', $selectedEmployees), static fn(string $value): bool => $value !== '')));
$selectedEmployees = array_values(array_intersect($selectedEmployees, $reportEmployeeOptions));
$reportFilterParams = ['from' => $from, 'to' => $to, 'department' => $selectedDepartment, 'employees' => $selectedEmployees];
$reportQueryParams = array_filter($reportFilterParams, static fn($value): bool => $value !== '' && $value !== []);
$reportCsvQuery = http_build_query($reportQueryParams + ['format' => 'csv']);
$ticketExportQuery = http_build_query(['date_from' => $from, 'date_to' => $to, 'department' => $selectedDepartment, 'employees' => $selectedEmployees]);
$params = [];
$reportDateClauses = ticket_window_sql($reportWindow, $params, 't.created_at', 'report_date');
$scope = ticket_scope_sql($user, $params);
$base = 't.deleted_at IS NULL' . ($reportDateClauses ? ' AND ' . implode(' AND ', $reportDateClauses) : '') . $scope;
if ($selectedDepartment !== '') {
    $base .= ' AND (t.department_id = :report_department OR EXISTS (SELECT 1 FROM ticket_departments report_department_filter WHERE report_department_filter.ticket_id=t.id AND report_department_filter.department_id=:report_department_pivot))';
    $params['report_department'] = (int) $selectedDepartment;
    $params['report_department_pivot'] = (int) $selectedDepartment;
}
if ($selectedEmployees) {
    $employeePlaceholders = [];
    foreach ($selectedEmployees as $employeeIndex => $employeeName) {
        $placeholder = 'report_employee_' . $employeeIndex;
        $employeePlaceholders[] = ':' . $placeholder;
        $params[$placeholder] = $employeeName;
    }
    $base .= ' AND t.employee_name IN (' . implode(',', $employeePlaceholders) . ')';
}
[$departmentRows, $assigneeRows] = report_native_workload($pdo, $base, $params);
// Apply the same normalized external-ticket filters used by ticket downloads.
$externalFilters = array_fill_keys(['status', 'priority', 'subcategory', 'category', 'scope', 'search', 'dashboard_view'], '');
$externalFilters += ['date_from' => $from, 'date_to' => $to, 'department' => $selectedDepartment, 'department_name' => '', 'employees' => $selectedEmployees, 'dashboard_range' => 'all'];
foreach ($reportDepartments as $department) {
    if ((string) $department['id'] === $selectedDepartment) $externalFilters['department_name'] = (string) $department['name'];
}
$externalDepartmentRows = [];
$externalAssigneeRows = [];
foreach ($reportExternalTickets['tickets'] ?? [] as $ticket) {
    if (!external_ticket_matches_filters($ticket, $externalFilters, $user)) continue;
    $departmentName = trim((string) ($ticket['department'] ?? '')) ?: 'Unassigned';
    $externalDepartmentRows[] = ['name' => $departmentName, 'count' => 1, 'closed' => ($ticket['status'] ?? '') === 'closed' ? 1 : 0];
    foreach (external_assignee_names($ticket) as $assigneeName) $externalAssigneeRows[] = ['name' => $assigneeName, 'count' => 1];
}
$mergeWorkloadRows = static function (array $nativeRows, array $externalRows): array {
    $groups = [];
    foreach (array_merge($nativeRows, $externalRows) as $row) {
        $key = strtolower(trim((string) $row['name']));
        if (!isset($groups[$key])) $groups[$key] = ['name' => $row['name'], 'count' => 0, 'closed' => 0];
        $groups[$key]['count'] += (int) $row['count'];
        $groups[$key]['closed'] += (int) ($row['closed'] ?? 0);
    }
    $rows = array_values($groups);
    usort($rows, static fn(array $a, array $b): int => ($b['count'] <=> $a['count']) ?: strcasecmp($a['name'], $b['name']));
    return $rows;
};
$departmentRows = $mergeWorkloadRows($departmentRows, $externalDepartmentRows);
$assigneeRows = $mergeWorkloadRows($assigneeRows, $externalAssigneeRows);
$workloadSourcesIncomplete = !empty($reportExternalTickets['errors']);

$leaveParams = [];
$leaveDateClauses = ticket_window_sql($reportWindow, $leaveParams, 'submitted_at', 'report_leave_date');
if (app_schema_has('leave_requests', 'deleted_at', $pdo)) array_unshift($leaveDateClauses, 'deleted_at IS NULL');
$leaveSql = 'SELECT status,COUNT(*) count FROM leave_requests';
if ($leaveDateClauses) $leaveSql .= ' WHERE ' . implode(' AND ', $leaveDateClauses);
$leaveSql .= ' GROUP BY status ORDER BY status';
$leave = $pdo->prepare($leaveSql);
$leave->execute($leaveParams);
$leaveRows = $leave->fetchAll();

if (($_GET['format'] ?? '') === 'csv') {
    if (!$canExportReports) { http_response_code(403); exit('You do not have permission to export reports.'); }
    audit_admin_action((int) $user['id'], 'workload_report_downloaded', 'report', null, ['filters' => $reportFilterParams]);
    header('Content-Type:text/csv');
    header('Content-Disposition:attachment; filename="ticket-report.csv"');
    $out = fopen('php://output', 'w');
    if ($workloadSourcesIncomplete) fputcsv($out, ['Notice', 'Some connected sources are unavailable; this report is incomplete.']);
    fputcsv($out, ['Department', 'Tickets', 'Closed']);
    foreach ($departmentRows as $row) fputcsv($out, [$row['name'], $row['count'], $row['closed']]);
    fputcsv($out, []);
    fputcsv($out, ['Assignee', 'Tickets']);
    foreach ($assigneeRows as $row) fputcsv($out, [$row['name'], $row['count']]);
    exit;
}

page_start('Reports', $user);
?>
<div class="reports-screen"><div class="reports-left-column"><section class="report-export-card"><h2>Export tickets</h2><div class="reports-toolbar"><form method="get" class="report-filter-bar"><div class="report-date-range" data-date-range-picker><span class="report-date-caption">Select Date Range</span><input type="hidden" name="from" data-date-from value="<?= e($from) ?>"><input type="hidden" name="to" data-date-to value="<?= e($to) ?>"><button type="button" class="filter-picker-trigger date-range-trigger" data-date-range-trigger aria-label="Select Date Range" aria-expanded="false"><span class="filter-picker-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M8 3.5v3M16 3.5v3M3.5 9.5h17"/></svg></span><span class="filter-picker-copy"><strong data-date-range-label>Any date range</strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu date-range-menu" data-date-range-menu hidden></div></div><div class="report-filter-field"><label for="report-department">Department</label><select id="report-department" name="department"><option value="">All departments</option><?php foreach ($reportDepartments as $department): ?><option value="<?= (int) $department['id'] ?>"<?= $selectedDepartment === (string) $department['id'] ? ' selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></div><div class="report-filter-field report-employee-field"><label for="report-employees">Employees</label><select id="report-employees" data-native-select name="employees[]" multiple size="4" aria-label="Select one or more employees"><option value=""<?= $selectedEmployees ? '' : ' selected' ?>>All employees</option><?php foreach ($reportEmployeeOptions as $employeeName): ?><option value="<?= e($employeeName) ?>"<?= in_array($employeeName, $selectedEmployees, true) ? ' selected' : '' ?>><?= e($employeeName) ?></option><?php endforeach; ?></select><div data-report-employee-picker hidden></div></div><button class="button button-secondary report-filter-submit" type="submit">Apply filters</button></form><div class="page-head-actions"><?php if ($canExportReports): ?><a class="button report-action-button" href="export-tickets.php?format=xlsx&amp;<?= e($ticketExportQuery) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M5 19.5h14"/></svg>Export Tickets Excel</a><a class="button button-secondary report-action-button" href="export-tickets.php?format=csv&amp;<?= e($ticketExportQuery) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M5 19.5h14"/></svg>Export Tickets CSV</a><?php endif; ?></div></div>
</section><?php if ($canImportReports): ?>
<details id="csv-import" class="report-panel csv-import-panel report-secondary-section"<?= ($error || $importNotice || is_array($preview)) ? ' open' : '' ?>>
    <summary>Import data from CSV</summary><p class="report-import-intro">Add users, departments or holidays from a file. This is separate from downloading tickets.</p>
    <div class="csv-import-content">
        <?php if ($error): ?><p class="auth-error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <?php if ($importNotice): ?><p class="action-notice" role="status"><?= e($importNotice) ?></p><?php endif; ?>
        <?php if ($importCredentials && ($user['role_slug'] ?? '') === 'super-admin'): ?><div class="temporary-import-credentials"><h3>Temporary passwords</h3><p>Save these now. They are shown once; each person must change their password at first sign-in.</p><div class="table-wrap"><table class="ticket-table"><thead><tr><th>Username</th><th>Temporary password</th></tr></thead><tbody><?php foreach ($importCredentials as $credential): ?><tr><td><?= e($credential['username']) ?></td><td><code><?= e($credential['password']) ?></code></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
        <p class="muted">Use this only to add many users, departments or holidays from a spreadsheet instead of entering them individually. It does not import tickets and is not needed for ticket downloads.</p>
        <details class="csv-format-guide">
            <summary>1. Prepare your CSV — formats and templates</summary>
            <p>Download a blank template, keep its header row unchanged, and add up to 1,000 records. Save as CSV (UTF-8), maximum 5 MB.</p>
            <?php foreach ($types as $key => $label): ?>
                <div class="csv-format-row"><strong><?= e($label) ?></strong><a href="reports.php?csv_template=<?= e($key) ?>">Download <?= e(strtolower($label)) ?> template</a><code><?= e(implode(',', $headers[$key])) ?></code>
                <?php if ($key === 'users'): ?><small>Use an existing role slug and department code. After import, use Users to set up account access.</small>
                <?php elseif ($key === 'departments'): ?><small>Use a unique department name and code.<?php if (app_schema_has('departments', 'department_type')): ?> department_type must be internal or cng_staff.<?php endif; ?></small>
                <?php else: ?><small>Dates: YYYY-MM-DD. Use COMPANY for company holidays, or the country code for public holidays. Existing matching holidays are updated.</small><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </details>
    </div>
    <form method="post" action="#csv-import" enctype="multipart/form-data" class="compact-form csv-import-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="preview">
        <label>Import type<select name="type"><?php foreach ($types as $key => $label): ?><option value="<?= e($key) ?>"<?= $selectedImportType === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label>CSV file<input type="file" name="csv" accept=".csv,text/csv" required></label>
        <button class="button" type="submit">2. Validate CSV</button>
    </form>
    <?php if (is_array($preview)): ?>
        <div class="csv-preview">
            <h3>Review: <?= e($types[$preview['type']]) ?></h3>
            <p><?= e($preview['filename'] ?? '') ?> — <?= count($preview['rows']) ?> valid rows, <?= count($preview['errors']) ?> validation issues. Nothing has been imported yet.</p>
            <?php if ($preview['errors']): ?>
                <div role="alert"><p>Correct these issues, then upload and validate the corrected file. Import is unavailable while errors remain.</p><ul class="csv-error-list"><?php foreach ($preview['errors'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <?php if ($preview['rows']): ?>
                <p>Showing the first <?= min(10, count($preview['rows'])) ?> valid rows of <?= count($preview['rows']) ?>.</p>
                <div class="table-wrap csv-data-preview" tabindex="0" role="region" aria-label="CSV data preview"><table class="ticket-table"><thead><tr><?php foreach ($headers[$preview['type']] as $column): ?><th><?= e($column) ?></th><?php endforeach; ?></tr></thead><tbody>
                <?php foreach (array_slice($preview['rows'], 0, 10) as $row): ?><tr><?php foreach ($headers[$preview['type']] as $column): ?><td><?= e($row[$column]) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
            <div class="csv-preview-actions">
                <?php if ($preview['rows'] && !$preview['errors']): ?>
                <form method="post" action="#csv-import">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="preview_token" value="<?= e($preview['token']) ?>">
                    <input type="hidden" name="action" value="import">
                    <button class="button" type="submit">3. Import <?= count($preview['rows']) ?> rows</button>
                </form>
                <?php endif; ?>
                <form method="post" action="#csv-import">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="preview_token" value="<?= e($preview['token']) ?>">
                    <input type="hidden" name="action" value="cancel">
                    <button class="button button-secondary" type="submit">Discard preview</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</details>
<?php endif; ?>
</div>
<section class="report-workload-panel" aria-labelledby="workload-reports-title"><div class="report-workload-head"><div><h2 id="workload-reports-title">View workload reports</h2></div><?php if ($canExportReports): ?><a class="button button-secondary report-action-button" href="reports.php?<?= e($reportCsvQuery) ?>" aria-label="Export workload report as CSV"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M5 19.5h14"/></svg>Export</a><?php endif; ?></div><div class="report-workload-content">
<?php if ($workloadSourcesIncomplete): ?><p class="auth-error" role="status">Some connected ticket sources are unavailable. Workload figures are incomplete; try again later.</p><?php endif; ?><div class="report-grid"><section class="report-panel"><div class="report-panel-head"><h2>Workload by department</h2><span><?= count($departmentRows) ?> departments</span></div><div class="table-wrap report-table-wrap"><table class="ticket-table report-table"><thead><tr><th>Department</th><th>Tickets</th><th>Closed</th></tr></thead><tbody><?php foreach ($departmentRows as $row): ?><tr><td><?= e($row['name']) ?></td><td><?= $row['count'] ?></td><td><?= $row['closed'] ?></td></tr><?php endforeach; ?><?php if (!$departmentRows): ?><tr><td colspan="3" class="empty-table-cell">No tickets match these filters.</td></tr><?php endif; ?></tbody></table></div></section><section class="report-panel"><div class="report-panel-head"><h2>Workload by assignee</h2><span><?= count($assigneeRows) ?> assignees</span></div><div class="table-wrap report-table-wrap"><table class="ticket-table report-table"><thead><tr><th>Assignee</th><th>Tickets</th></tr></thead><tbody><?php foreach ($assigneeRows as $row): ?><tr><td><?= e($row['name']) ?></td><td><?= $row['count'] ?></td></tr><?php endforeach; ?><?php if (!$assigneeRows): ?><tr><td colspan="2" class="empty-table-cell">No tickets match these filters.</td></tr><?php endif; ?></tbody></table></div></section><section class="report-panel report-panel-wide"><div class="report-panel-head"><h2>Leave request activity</h2><span>Submission date only; no department/employee filtering or attendance logs</span></div><div class="table-wrap report-table-wrap"><table class="ticket-table report-table"><thead><tr><th>Status</th><th>Requests</th></tr></thead><tbody><?php foreach ($leaveRows as $row): ?><tr><td><?= e($row['status']) ?></td><td><?= $row['count'] ?></td></tr><?php endforeach; ?><?php if (!$leaveRows): ?><tr><td colspan="2" class="empty-table-cell">No leave requests in this date range.</td></tr><?php endif; ?></tbody></table></div></section></div>
</div></section>
</div>
<?php page_end(); ?>
