<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('view_all_tickets');
require_non_team_member($user);
$pdo = db();
$error = '';
$preview = $_SESSION['csv_preview'] ?? null;
$types = ['users' => 'Users', 'departments' => 'Departments', 'holidays' => 'Holidays', 'tickets' => 'Tickets'];
$headers = [
    'users' => ['full_name', 'username', 'email', 'role', 'department'],
    'departments' => ['name', 'code'],
    'holidays' => ['date', 'label', 'country_code'],
    'tickets' => ['ticket_number', 'issue_escalator', 'subject', 'category', 'subcategory', 'department', 'employee_name', 'description', 'priority', 'status'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!user_can('manage_users')) { http_response_code(403); exit('You do not have permission to access this action.'); }
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $type = (string) ($_POST['type'] ?? '');
    if ($action === 'preview' && isset($types[$type]) && isset($_FILES['csv']) && ($_FILES['csv']['error'] ?? 1) === UPLOAD_ERR_OK && ($_FILES['csv']['size'] ?? 0) <= 5 * 1024 * 1024) {
        $handle = fopen($_FILES['csv']['tmp_name'], 'rb');
        $rows = [];
        $errors = [];
        $line = 0;
        $head = fgetcsv($handle);
        $expected = $headers[$type];
        if (!$head || array_map('trim', $head) !== $expected) $errors[] = 'Header must be: ' . implode(',', $expected);
        else while (($row = fgetcsv($handle)) !== false && count($rows) < 1000) {
            $line++;
            if (count($row) !== count($expected)) { $errors[] = 'Row ' . ($line + 1) . ': expected ' . count($expected) . ' columns.'; continue; }
            $data = array_combine($expected, $row);
            foreach ($expected as $key) $data[$key] = trim((string) $data[$key]);
            $holidayDate = DateTimeImmutable::createFromFormat('!Y-m-d', $data['date'] ?? '');
            $validHolidayDate = $holidayDate && $holidayDate->format('Y-m-d') === ($data['date'] ?? '');
            if ($type === 'users' && (!$data['full_name'] || !preg_match('/^[a-z0-9._-]{3,80}$/', $data['username']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) || !$data['role'])) $errors[] = 'Row ' . ($line + 1) . ': invalid user fields.';
            elseif ($type === 'departments' && (!$data['name'] || !$data['code'])) $errors[] = 'Row ' . ($line + 1) . ': name and code are required.';
            elseif ($type === 'holidays' && (!$validHolidayDate || !$data['label'])) $errors[] = 'Row ' . ($line + 1) . ': invalid holiday date or label.';
            elseif ($type === 'tickets' && (!$data['subject'] || !$data['category'] || !$data['department'] || !$data['employee_name'] || !$data['description'])) $errors[] = 'Row ' . ($line + 1) . ': required ticket fields missing.';
            else $rows[] = $data;
        }
        fclose($handle);
        $_SESSION['csv_preview'] = ['type' => $type, 'rows' => $rows, 'errors' => $errors];
        $preview = $_SESSION['csv_preview'];
    } elseif ($action === 'import' && is_array($preview) && !empty($preview['rows'])) {
        try {
            $pdo->beginTransaction();
            foreach ($preview['rows'] as $row) {
                if ($preview['type'] === 'departments') $pdo->prepare('INSERT INTO departments(name,code) VALUES(?,?)')->execute([$row['name'], $row['code']]);
                elseif ($preview['type'] === 'holidays') $pdo->prepare('INSERT INTO company_holidays(`date`,label,country_code,holiday_type) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE label=VALUES(label),holiday_type=VALUES(holiday_type)')->execute([$row['date'], $row['label'], $row['country_code'] ?: 'COMPANY', $row['country_code'] === 'COMPANY' ? 'company' : 'public']);
                elseif ($preview['type'] === 'users') {
                    $role = $pdo->prepare('SELECT id FROM roles WHERE slug=?');
                    $role->execute([$row['role']]);
                    $roleId = (int) $role->fetchColumn();
                    if (!$roleId) throw new RuntimeException('Unknown role: ' . $row['role']);
                    $dept = $pdo->prepare('SELECT id FROM departments WHERE code=?');
                    $dept->execute([$row['department']]);
                    $deptId = $dept->fetchColumn() ?: null;
                    $temporary = strtoupper(bin2hex(random_bytes(5)));
                    $pdo->prepare('INSERT INTO users(role_id,department_id,username,full_name,email,password_hash,must_change_password) VALUES(?,?,?,?,?,?,1)')->execute([$roleId, $deptId, $row['username'], $row['full_name'], $row['email'], password_hash($temporary, PASSWORD_DEFAULT)]);
                }
            }
            $pdo->commit();
            audit_admin_action((int) $user['id'], 'csv_imported', $preview['type'], null, ['rows' => count($preview['rows'])]);
            unset($_SESSION['csv_preview']);
            $preview = null;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Import failed. Check duplicates and referenced roles/departments.';
        }
    }
}

$from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
$to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');
$params = ['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59'];
$scope = ticket_scope_sql($user, $params);
$base = 't.deleted_at IS NULL AND t.created_at BETWEEN :from AND :to' . $scope;
$q = $pdo->prepare('SELECT COUNT(*) FROM tickets t WHERE ' . $base);
$q->execute($params);
$total = (int) $q->fetchColumn();
$closed = $pdo->prepare('SELECT COUNT(*) FROM tickets t WHERE ' . $base . ' AND t.status="closed"');
$closed->execute($params);
$closed = (int) $closed->fetchColumn();
$overdue = $pdo->prepare('SELECT COUNT(*) FROM tickets t WHERE ' . $base . ' AND t.status<>"closed" AND TIMESTAMPDIFF(DAY,t.created_at,NOW())>=COALESCE((SELECT open_days FROM sla_rules sr WHERE sr.priority=t.priority),7)');
$overdue->execute($params);
$overdue = (int) $overdue->fetchColumn();
$byDepartment = $pdo->prepare('SELECT d.name,COUNT(*) count,SUM(t.status="closed") closed FROM tickets t JOIN departments d ON d.id=t.department_id WHERE ' . $base . ' GROUP BY d.id,d.name ORDER BY count DESC');
$byDepartment->execute($params);
$departmentRows = $byDepartment->fetchAll();
$byAssignee = $pdo->prepare('SELECT COALESCE(a.full_name,"Unassigned") name,COUNT(DISTINCT t.id) count FROM tickets t LEFT JOIN users a ON a.id=t.assignee_id LEFT JOIN ticket_assignees ta ON ta.ticket_id=t.id WHERE ' . $base . ' GROUP BY COALESCE(a.id,0),a.full_name ORDER BY count DESC');
$byAssignee->execute($params);
$assigneeRows = $byAssignee->fetchAll();
$leave = $pdo->prepare('SELECT status,COUNT(*) count FROM leave_requests WHERE submitted_at BETWEEN ? AND ? GROUP BY status ORDER BY status');
$leave->execute([$params['from'], $params['to']]);
$leaveRows = $leave->fetchAll();

if (($_GET['format'] ?? '') === 'csv') {
    header('Content-Type:text/csv');
    header('Content-Disposition:attachment; filename="ticket-report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Metric', 'Value']);
    fputcsv($out, ['Tickets created', $total]);
    fputcsv($out, ['Closed', $closed]);
    fputcsv($out, ['Overdue', $overdue]);
    fputcsv($out, []);
    fputcsv($out, ['Department', 'Tickets', 'Closed']);
    foreach ($departmentRows as $row) fputcsv($out, [$row['name'], $row['count'], $row['closed']]);
    fputcsv($out, []);
    fputcsv($out, ['Assignee', 'Tickets']);
    foreach ($assigneeRows as $row) fputcsv($out, [$row['name'], $row['count']]);
    exit;
}

page_start('Reports', $user);
?>
<div class="reports-screen"><div class="reports-toolbar"><form method="get" class="report-filter-bar"><div class="report-date-range" data-date-range-picker><input type="hidden" name="from" data-date-from value="<?= e($from) ?>"><input type="hidden" name="to" data-date-to value="<?= e($to) ?>"><button type="button" class="filter-picker-trigger date-range-trigger" data-date-range-trigger aria-label="Report date range" aria-expanded="false"><span class="filter-picker-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M8 3.5v3M16 3.5v3M3.5 9.5h17"/></svg></span><span class="filter-picker-copy"><strong data-date-range-label>Any date range</strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu date-range-menu" data-date-range-menu hidden></div></div></form><div class="page-head-actions"><a class="button button-secondary report-action-button" href="reports.php?from=<?= e($from) ?>&amp;to=<?= e($to) ?>&amp;format=csv"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M5 19.5h14"/></svg>Export CSV</a><a class="button report-action-button" href="#csv-import"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5.5h14v13H5zM8 9h8M8 13h5"/></svg>CSV import</a></div></div>
<section class="report-metrics" aria-label="Report summary"><div class="report-metric-card"><span>Created</span><strong><?= $total ?></strong></div><div class="report-metric-card"><span>Closed</span><strong><?= $closed ?></strong></div><div class="report-metric-card is-alert"><span>Overdue</span><strong><?= $overdue ?></strong></div><div class="report-metric-card"><span>Closure rate</span><strong><?= $total ? round($closed / $total * 100) : 0 ?>%</strong></div></section>
<div class="report-grid"><section class="report-panel"><div class="report-panel-head"><h2>Workload by department</h2><span><?= count($departmentRows) ?> departments</span></div><div class="table-wrap report-table-wrap"><table class="ticket-table report-table"><thead><tr><th>Department</th><th>Tickets</th><th>Closed</th></tr></thead><tbody><?php foreach ($departmentRows as $row): ?><tr><td><?= e($row['name']) ?></td><td><?= $row['count'] ?></td><td><?= $row['closed'] ?></td></tr><?php endforeach; ?></tbody></table></div></section><section class="report-panel"><div class="report-panel-head"><h2>Workload by assignee</h2><span><?= count($assigneeRows) ?> assignees</span></div><div class="table-wrap report-table-wrap"><table class="ticket-table report-table"><thead><tr><th>Assignee</th><th>Tickets</th></tr></thead><tbody><?php foreach ($assigneeRows as $row): ?><tr><td><?= e($row['name']) ?></td><td><?= $row['count'] ?></td></tr><?php endforeach; ?></tbody></table></div></section><section class="report-panel report-panel-wide"><div class="report-panel-head"><h2>Leave activity</h2><span>Requests in range</span></div><div class="table-wrap report-table-wrap"><table class="ticket-table report-table"><thead><tr><th>Status</th><th>Requests</th></tr></thead><tbody><?php foreach ($leaveRows as $row): ?><tr><td><?= e($row['status']) ?></td><td><?= $row['count'] ?></td></tr><?php endforeach; ?></tbody></table></div></section></div>
<section id="csv-import" class="report-panel csv-import-panel"><div class="report-panel-head"><div><h2>CSV import</h2><p>Validate the file before importing any rows.</p></div><span>Upload · validate · import</span></div><?php if ($error): ?><p class="auth-error"><?= e($error) ?></p><?php endif; ?><form method="post" enctype="multipart/form-data" class="compact-form csv-import-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="preview"><label>Import type<select name="type"><?php foreach ($types as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label><label>CSV file<input type="file" name="csv" accept=".csv,text/csv" required></label><button class="button" type="submit">Validate CSV</button></form><p class="muted csv-import-note">Maximum 1,000 rows and 5 MB per upload. Required headers depend on the import type.</p><?php if (is_array($preview)): ?><div class="csv-preview"><h3>Preview: <?= e($types[$preview['type']] ?? 'CSV') ?></h3><p><?= count($preview['rows']) ?> valid rows, <?= count($preview['errors']) ?> errors.</p><?php if ($preview['errors']): ?><ul><?php foreach ($preview['errors'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul><?php endif; ?><?php if ($preview['rows']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="import"><button class="button" type="submit" data-confirm="Import these valid rows?">Import valid rows</button></form><?php endif; ?></div><?php endif; ?></section></div>
<?php page_end();
