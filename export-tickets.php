<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';
$internalDepartmentSql = ticket_internal_department_sql('dep');
require __DIR__ . '/app/external_tickets.php';

$user = require_permission('export_tickets');
$format = $_GET['format'] ?? 'csv';
if (!in_array($format, ['csv', 'xlsx'], true)) { http_response_code(400); exit('Unsupported export format.'); }
const MAX_EXPORT_ROWS = 10000;

$statuses = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'closed' => 'Closed'];
$departments = active_internal_departments();
$departmentFilterOptions = ticket_department_filter_options($departments);
$externalResult = external_ticket_load_all($user);
$allExternalTickets = $externalResult['tickets'];
$categoryOptions = ticket_category_options($allExternalTickets);
$filters = [];
foreach (['dashboard_range', 'dashboard_view', 'dashboard_from', 'dashboard_to', 'scope', 'status', 'priority', 'department', 'category', 'search', 'date_from', 'date_to'] as $key) {
    $filters[$key] = trim(request_string($_GET, $key));
}
$filters['employees'] = $_GET['employees'] ?? [];
if (!is_array($filters['employees'])) $filters['employees'] = [$filters['employees']];
$filters['employees'] = array_values(array_unique(array_filter(array_map(static fn($value): string => is_scalar($value) ? trim((string) $value) : '', $filters['employees']), static fn(string $value): bool => $value !== '')));
$filters['employees'] = array_slice($filters['employees'], 0, 100);
$dashboardRanges = [
    'today' => ['Today', 'Today'],
    'all' => ['All tickets', null],
    '7d' => ['Last 7 days', '-6 days'],
    '30d' => ['Last 30 days', '-29 days'],
    '3m' => ['Last 3 months', '-3 months'],
    '6m' => ['Last 6 months', '-6 months'],
    '1y' => ['Last 1 year', '-1 year'],
];
$dashboardViews = ['total', 'open_work', 'urgent', 'overdue', 'idle', 'unassigned'];
if (!isset($dashboardRanges[$filters['dashboard_range']])) $filters['dashboard_range'] = 'all';
if (!in_array($filters['dashboard_view'], $dashboardViews, true)) $filters['dashboard_view'] = '';
if (!in_array($filters['scope'], ['', 'mine'], true)) $filters['scope'] = '';
if (!isset($statuses[$filters['status']])) $filters['status'] = '';
if (!isset(TICKET_PRIORITIES[$filters['priority']])) $filters['priority'] = '';
$departmentId = null;
$filters['department_name'] = '';
if (array_key_exists($filters['department'], $departmentFilterOptions)) {
    $departmentId = $departmentFilterOptions[$filters['department']]['department_id'];
    $filters['department_name'] = $departmentFilterOptions[$filters['department']]['name'];
} elseif (ctype_digit($filters['department'])) {
    foreach ($departments as $department) {
        if ((int) $department['id'] === (int) $filters['department']) {
            $departmentId = (int) $department['id'];
            $filters['department_name'] = (string) $department['name'];
            break;
        }
    }
    if ($filters['department_name'] === '') $filters['department'] = '';
} else {
    $filters['department'] = '';
}
if (!in_array($filters['category'], $categoryOptions, true)) $filters['category'] = '';
foreach (['date_from', 'date_to', 'dashboard_from', 'dashboard_to'] as $dateKey) {
    if ($filters[$dateKey] === '') continue;
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$dateKey]);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $filters[$dateKey]) { http_response_code(400); exit($dateKey . ' must use YYYY-MM-DD.'); }
}
if ($filters['date_from'] !== '' && $filters['date_to'] !== '' && $filters['date_from'] > $filters['date_to']) { http_response_code(400); exit('date_from cannot be after date_to.'); }
$dashboardWindow = ticket_window_bounds($filters['dashboard_from'], $filters['dashboard_to'], $filters['dashboard_range']);
$filters['dashboard_from'] = $dashboardWindow['from'];
$filters['dashboard_to'] = $dashboardWindow['to'];
$filters['dashboard_range'] = $dashboardWindow['preset'];
$dateWindow = ticket_window_bounds($filters['date_from'], $filters['date_to'], 'all');

$where = ['t.deleted_at IS NULL'];
$params = [];
foreach (['status', 'priority'] as $key) if ($filters[$key] !== '') { $where[] = 't.' . $key . ' = :' . $key; $params[$key] = $filters[$key]; }
if ($filters['category'] !== '') { $where[] = 'LOWER(TRIM(t.category)) = LOWER(:category)'; $params['category'] = trim($filters['category']); }
if ($filters['department'] !== '') {
    if ($departmentId === null) {
        $where[] = '1 = 0';
    } else {
        $where[] = '(t.department_id = :department OR EXISTS (SELECT 1 FROM ticket_departments td_filter WHERE td_filter.ticket_id = t.id AND td_filter.department_id = :department_pivot))';
        $params['department'] = $departmentId;
        $params['department_pivot'] = $departmentId;
    }
}
if ($filters['search'] !== '') {
    $where[] = '(t.ticket_number LIKE :ticket_search OR t.subject LIKE :subject_search OR creator.full_name LIKE :requestor_search OR t.employee_name LIKE :employee_search)';
    $params['requestor_search'] = $params['ticket_search'] = $params['subject_search'] = $params['employee_search'] = '%' . $filters['search'] . '%';
}
if ($filters['employees']) {
    $employeePlaceholders = [];
    foreach ($filters['employees'] as $employeeIndex => $employeeName) {
        $placeholder = 'employee_filter_' . $employeeIndex;
        $employeePlaceholders[] = ':' . $placeholder;
        $params[$placeholder] = $employeeName;
    }
    $where[] = 't.employee_name IN (' . implode(',', $employeePlaceholders) . ')';
}
$where = array_merge($where, ticket_window_sql($dateWindow, $params, 't.created_at', 'date_window'));
$where = array_merge($where, ticket_window_sql($dashboardWindow, $params, 't.created_at', 'dashboard_window'));
if ($filters['dashboard_view'] !== '') {
    if ($filters['dashboard_view'] === 'open_work') $where[] = "t.status IN ('open','in_progress','pending')";
    if ($filters['dashboard_view'] === 'urgent') $where[] = 't.status <> "closed" AND t.priority = "urgent"';
    if ($filters['dashboard_view'] === 'overdue') $where[] = 't.status <> "closed" AND t.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)';
    if ($filters['dashboard_view'] === 'idle') $where[] = 't.status <> "closed" AND t.updated_at < DATE_SUB(NOW(), INTERVAL 3 DAY) AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
    if ($filters['dashboard_view'] === 'unassigned') $where[] = 't.assignee_id IS NULL AND NOT EXISTS (SELECT 1 FROM ticket_assignees ta_view WHERE ta_view.ticket_id = t.id)';
}
$whereSql = implode(' AND ', $where) . ticket_scope_sql($user, $params, $filters['scope']);

$stmt = db()->prepare("SELECT t.*, creator.full_name AS creator_name, d.name department,
    COALESCE((SELECT GROUP_CONCAT(DISTINCT dep.name ORDER BY dep.name SEPARATOR ', ') FROM ticket_departments td JOIN departments dep ON dep.id = td.department_id AND {$internalDepartmentSql} WHERE td.ticket_id = t.id), d.name) AS departments,
    COALESCE((SELECT GROUP_CONCAT(DISTINCT u.full_name ORDER BY u.full_name SEPARATOR ', ') FROM ticket_assignees ta JOIN users u ON u.id = ta.user_id WHERE ta.ticket_id = t.id), a.full_name) AS assignees
    FROM tickets t
    JOIN departments d ON d.id = t.department_id
    LEFT JOIN users a ON a.id = t.assignee_id LEFT JOIN users creator ON creator.id = t.created_by
    WHERE $whereSql
    ORDER BY t.updated_at DESC");
$stmt->execute($params);
$nativeTickets = $stmt->fetchAll();
$externalTickets = array_values(array_filter($allExternalTickets, static fn(array $ticket): bool => external_ticket_matches_filters($ticket, $filters, $user)));
$tickets = array_merge($nativeTickets, $externalTickets);
usort($tickets, static function (array $left, array $right): int {
    $leftTime = strtotime((string) ($left['sort_at'] ?? $left['updated_at'] ?? $left['created_at'] ?? '')) ?: 0;
    $rightTime = strtotime((string) ($right['sort_at'] ?? $right['updated_at'] ?? $right['created_at'] ?? '')) ?: 0;
    return ($rightTime <=> $leftTime) ?: ((int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0));
});
$tickets = array_slice($tickets, 0, MAX_EXPORT_ROWS + 1);
if (count($tickets) > MAX_EXPORT_ROWS) { http_response_code(413); exit('Export exceeds the 10,000-row limit. Narrow the filters and try again.'); }

$headers = ['ID', 'Title', 'Requestor', 'Assigned Department', 'SLA', 'Status', 'Priority', 'Category', 'Subcategory', 'Date Created', 'Date Updated', 'Date Closed', 'Assignees'];
$rows = [];
foreach ($tickets as $ticket) {
    $sla = ticket_sla_state($ticket);
    $rows[] = [
        ticket_display_id($ticket), $ticket['subject'], ticket_requestor_name($ticket), $ticket['departments'] ?? ($ticket['department'] ?? '—'), $sla[1],
        $ticket['status_label'] ?? ($statuses[$ticket['status']] ?? $ticket['status']), $ticket['priority_label'] ?? (TICKET_PRIORITIES[$ticket['priority'] ?? 'normal'] ?? 'Normal'), $ticket['category'], $ticket['subcategory'] ?? '',
        $ticket['created_at'], $ticket['updated_at'] ?? '', $ticket['closed_at'] ?? '', $ticket['assignees'] ?? ($ticket['agent'] ?? 'Unassigned'),
    ];
}
function export_csv_value(string $value): string { return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value; }
function xlsx_xml(string $value): string { return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
function xlsx_col(int $column): string { $name = ''; while ($column > 0) { $column--; $name = chr(65 + $column % 26) . $name; $column = intdiv($column, 26); } return $name; }
function xlsx_sheet(array $headers, array $rows): string { $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'; foreach (array_merge([$headers], $rows) as $rowNumber => $row) { $xml .= '<row r="' . ($rowNumber + 1) . '">'; foreach ($row as $column => $value) $xml .= '<c r="' . xlsx_col($column + 1) . ($rowNumber + 1) . '" t="inlineStr"><is><t xml:space="preserve">' . xlsx_xml((string) $value) . '</t></is></c>'; $xml .= '</row>'; } return $xml . '</sheetData></worksheet>'; }
if ($format === 'csv') {
    audit_admin_action((int) $user['id'], 'tickets_downloaded', 'ticket_export', null, ['format' => 'csv', 'count' => count($tickets), 'filters' => $filters]);
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="cng-jamesons-tickets.csv"');
    $output = fopen('php://output', 'w'); fputcsv($output, $headers); foreach ($rows as $row) fputcsv($output, array_map(static fn($value) => export_csv_value((string) $value), $row)); fclose($output); exit;
}
if (!class_exists('ZipArchive')) { http_response_code(500); exit('Excel export is not available on this server.'); }
$file = tempnam(sys_get_temp_dir(), 'cng-export-'); $zip = new ZipArchive();
if ($file === false || $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { http_response_code(500); exit('Unable to prepare the Excel export.'); }
$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
$zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Tickets" sheetId="1" r:id="rId1"/></sheets></workbook>');
$zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
$zip->addFromString('xl/worksheets/sheet1.xml', xlsx_sheet($headers, $rows)); $zip->close();
audit_admin_action((int) $user['id'], 'tickets_downloaded', 'ticket_export', null, ['format' => 'xlsx', 'count' => count($tickets), 'filters' => $filters]);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="cng-jamesons-tickets.xlsx"'); header('Content-Length: ' . filesize($file)); readfile($file); unlink($file);
