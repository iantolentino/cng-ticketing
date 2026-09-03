<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';
require __DIR__ . '/app/external_tickets.php';

$user = require_permission('export_tickets');
$format = $_GET['format'] ?? 'csv';
if (!in_array($format, ['csv', 'xlsx'], true)) { http_response_code(400); exit('Unsupported export format.'); }
const MAX_EXPORT_ROWS = 10000;

$statuses = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'closed' => 'Closed'];
$departments = active_departments();
$departmentIds = array_map('intval', array_column($departments, 'id'));
$filters = [];
foreach (['dashboard_range', 'dashboard_view', 'status', 'priority', 'department', 'category', 'subcategory', 'search', 'date_from', 'date_to'] as $key) {
    $filters[$key] = trim(request_string($_GET, $key));
}
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
if (!isset($statuses[$filters['status']])) $filters['status'] = '';
if (!isset(TICKET_PRIORITIES[$filters['priority']])) $filters['priority'] = '';
if (!ctype_digit($filters['department']) || !in_array((int) $filters['department'], $departmentIds, true)) $filters['department'] = '';
if (!array_key_exists($filters['category'], TICKET_CATEGORIES)) $filters['category'] = '';
$subcategoryOptions = $filters['category'] ? TICKET_CATEGORIES[$filters['category']] : array_merge(...array_values(TICKET_CATEGORIES));
if (!in_array($filters['subcategory'], $subcategoryOptions, true)) $filters['subcategory'] = '';
foreach (['date_from', 'date_to'] as $dateKey) {
    if ($filters[$dateKey] === '') continue;
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$dateKey]);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $filters[$dateKey]) { http_response_code(400); exit($dateKey . ' must use YYYY-MM-DD.'); }
}
if ($filters['date_from'] !== '' && $filters['date_to'] !== '' && $filters['date_from'] > $filters['date_to']) { http_response_code(400); exit('date_from cannot be after date_to.'); }
$dashboardStart = $filters['dashboard_range'] === 'all'
    ? '1970-01-01 00:00:00'
    : ($filters['dashboard_range'] === 'today'
        ? date('Y-m-d 00:00:00')
        : date('Y-m-d 00:00:00', strtotime($dashboardRanges[$filters['dashboard_range']][1])));

$where = ['t.deleted_at IS NULL'];
$params = [];
foreach (['status', 'priority', 'category', 'subcategory'] as $key) if ($filters[$key] !== '') { $where[] = 't.' . $key . ' = :' . $key; $params[$key] = $filters[$key]; }
if ($filters['department'] !== '') {
    $where[] = '(t.department_id = :department OR EXISTS (SELECT 1 FROM ticket_departments td_filter WHERE td_filter.ticket_id = t.id AND td_filter.department_id = :department_pivot))';
    $params['department'] = (int) $filters['department'];
    $params['department_pivot'] = (int) $filters['department'];
}
if ($filters['search'] !== '') {
    $where[] = '(t.ticket_number LIKE :ticket_search OR t.subject LIKE :subject_search OR t.employee_name LIKE :employee_search)';
    $params['ticket_search'] = $params['subject_search'] = $params['employee_search'] = '%' . $filters['search'] . '%';
}
if ($filters['date_from'] !== '') { $where[] = 't.created_at >= :date_from'; $params['date_from'] = $filters['date_from'] . ' 00:00:00'; }
if ($filters['date_to'] !== '') { $where[] = 't.created_at < DATE_ADD(:date_to, INTERVAL 1 DAY)'; $params['date_to'] = $filters['date_to']; }
if ($filters['dashboard_range'] !== 'all') {
    $where[] = 't.created_at >= :dashboard_start';
    $params['dashboard_start'] = $dashboardStart;
}
if ($filters['dashboard_view'] !== '') {
    if ($filters['dashboard_view'] === 'open_work') $where[] = "t.status IN ('open','in_progress','pending')";
    if ($filters['dashboard_view'] === 'urgent') $where[] = 't.status <> "closed" AND t.priority = "urgent"';
    if ($filters['dashboard_view'] === 'overdue') $where[] = 't.status <> "closed" AND t.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)';
    if ($filters['dashboard_view'] === 'idle') $where[] = 't.status <> "closed" AND t.updated_at < DATE_SUB(NOW(), INTERVAL 3 DAY) AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
    if ($filters['dashboard_view'] === 'unassigned') $where[] = 't.assignee_id IS NULL AND NOT EXISTS (SELECT 1 FROM ticket_assignees ta_view WHERE ta_view.ticket_id = t.id)';
}
$whereSql = implode(' AND ', $where) . ticket_scope_sql($user, $params);

$stmt = db()->prepare("SELECT t.*, d.name department,
    COALESCE((SELECT GROUP_CONCAT(DISTINCT dep.name ORDER BY dep.name SEPARATOR ', ') FROM ticket_departments td JOIN departments dep ON dep.id = td.department_id WHERE td.ticket_id = t.id), d.name) AS departments,
    COALESCE((SELECT GROUP_CONCAT(DISTINCT u.full_name ORDER BY u.full_name SEPARATOR ', ') FROM ticket_assignees ta JOIN users u ON u.id = ta.user_id WHERE ta.ticket_id = t.id), a.full_name) AS assignees
    FROM tickets t
    JOIN departments d ON d.id = t.department_id
    LEFT JOIN users a ON a.id = t.assignee_id
    WHERE $whereSql
    ORDER BY t.updated_at DESC");
$stmt->execute($params);
$nativeTickets = $stmt->fetchAll();
$externalResult = external_ticket_load_all($user);
$externalTickets = array_values(array_filter($externalResult['tickets'], static fn(array $ticket): bool => external_ticket_matches_filters($ticket, $filters, $dashboardStart)));
$tickets = array_merge($nativeTickets, $externalTickets);
usort($tickets, static function (array $left, array $right): int {
    $leftTime = strtotime((string) ($left['sort_at'] ?? $left['updated_at'] ?? $left['created_at'] ?? '')) ?: 0;
    $rightTime = strtotime((string) ($right['sort_at'] ?? $right['updated_at'] ?? $right['created_at'] ?? '')) ?: 0;
    return ($rightTime <=> $leftTime) ?: ((int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0));
});
$tickets = array_slice($tickets, 0, MAX_EXPORT_ROWS + 1);
if (count($tickets) > MAX_EXPORT_ROWS) { http_response_code(413); exit('Export exceeds the 10,000-row limit. Narrow the filters and try again.'); }

$headers = ['Status', 'Priority', 'Subject', 'Departments', 'Category', 'Subcategory', 'Date Created', 'Date Updated', 'Date Closed', 'Assignees', 'Employee'];
$rows = [];
foreach ($tickets as $ticket) $rows[] = [
    $ticket['status_label'] ?? ($statuses[$ticket['status']] ?? $ticket['status']), $ticket['priority_label'] ?? (TICKET_PRIORITIES[$ticket['priority'] ?? 'normal'] ?? 'Normal'), $ticket['subject'], $ticket['departments'], $ticket['category'], $ticket['subcategory'] ?? '',
    $ticket['created_at'], $ticket['updated_at'] ?? '', $ticket['closed_at'] ?? '', $ticket['assignees'] ?? 'Unassigned', $ticket['employee_name'],
];
function export_csv_value(string $value): string { return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value; }
function xlsx_xml(string $value): string { return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
function xlsx_col(int $column): string { $name = ''; while ($column > 0) { $column--; $name = chr(65 + $column % 26) . $name; $column = intdiv($column, 26); } return $name; }
function xlsx_sheet(array $headers, array $rows): string { $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'; foreach (array_merge([$headers], $rows) as $rowNumber => $row) { $xml .= '<row r="' . ($rowNumber + 1) . '">'; foreach ($row as $column => $value) $xml .= '<c r="' . xlsx_col($column + 1) . ($rowNumber + 1) . '" t="inlineStr"><is><t xml:space="preserve">' . xlsx_xml((string) $value) . '</t></is></c>'; $xml .= '</row>'; } return $xml . '</sheetData></worksheet>'; }
if ($format === 'csv') {
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
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="cng-jamesons-tickets.xlsx"'); header('Content-Length: ' . filesize($file)); readfile($file); unlink($file);
