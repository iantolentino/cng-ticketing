<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/tickets.php';
header('Content-Type: application/json; charset=utf-8');

function feed_error(int $status, string $message, ?int $tokenId = null): never {
    http_response_code($status);
    if (isset($GLOBALS['feedPdo'])) $GLOBALS['feedPdo']->prepare('INSERT INTO api_feed_access_log(token_id,ip_address,query_params,status_code) VALUES(?,?,?,?)')->execute([$tokenId, $_SERVER['REMOTE_ADDR'] ?? 'unknown', json_encode($_GET), $status]);
    echo json_encode(['error' => $message]); exit;
}
$pdo = $GLOBALS['feedPdo'] = db();
$token = request_string($_GET, 'token');
if (!preg_match('/^[a-f0-9]{64}$/', $token)) feed_error(401, 'Invalid or revoked token.');
$tokenLookup = $pdo->prepare('SELECT id FROM api_tokens WHERE token_hash = ? AND revoked_at IS NULL LIMIT 1');
$tokenLookup->execute([hash('sha256', $token)]);
$tokenId = (int) ($tokenLookup->fetchColumn() ?: 0);
if (!$tokenId) feed_error(401, 'Invalid or revoked token.');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown'; $recent = $pdo->prepare('SELECT COUNT(*) FROM api_feed_access_log WHERE ip_address=? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)'); $recent->execute([$ip]); if ((int)$recent->fetchColumn() >= 60) feed_error(429, 'Rate limit exceeded.', $tokenId);
$where = ['t.deleted_at IS NULL']; $params = [];
$allowedFilters = [
    'status' => ['open', 'in_progress', 'pending', 'closed'],
    'priority' => ['low', 'normal', 'high', 'urgent'],
    'category' => array_keys(TICKET_CATEGORIES),
];
foreach ($allowedFilters as $field => $allowedValues) {
    $value = request_string($_GET, $field);
    if ($value !== '') {
        if (!in_array($value, $allowedValues, true)) feed_error(400, $field . ' is invalid.');
        $where[] = "t.$field = :$field";
        $params[$field] = $value;
    }
}
$assignedTo = request_string($_GET, 'assigned_to');
if ($assignedTo !== '') {
    if (!ctype_digit($assignedTo) || (int) $assignedTo < 1) feed_error(400, 'assigned_to must be a positive integer.');
    $where[] = '(t.assignee_id = :assigned_to OR EXISTS (SELECT 1 FROM ticket_assignees ta WHERE ta.ticket_id=t.id AND ta.user_id=:assigned_to_pivot))';
    $params['assigned_to'] = (int) $assignedTo;
    $params['assigned_to_pivot'] = (int) $assignedTo;
}
$dateFromValue = request_string($_GET, 'date_from');
if ($dateFromValue !== '') {
    $dateFrom = DateTimeImmutable::createFromFormat('!Y-m-d', $dateFromValue);
    if (!$dateFrom || $dateFrom->format('Y-m-d') !== $dateFromValue) feed_error(400, 'date_from must use YYYY-MM-DD.');
    $where[]='t.created_at >= :date_from'; $params['date_from']=$dateFrom->format('Y-m-d 00:00:00');
}
$dateToValue = request_string($_GET, 'date_to');
if ($dateToValue !== '') {
    $dateTo = DateTimeImmutable::createFromFormat('!Y-m-d', $dateToValue);
    if (!$dateTo || $dateTo->format('Y-m-d') !== $dateToValue) feed_error(400, 'date_to must use YYYY-MM-DD.');
    $where[]='t.created_at < :date_to_exclusive'; $params['date_to_exclusive']=$dateTo->modify('+1 day')->format('Y-m-d 00:00:00');
}
if ($dateFromValue !== '' && $dateToValue !== '' && $dateFromValue > $dateToValue) feed_error(400, 'date_from cannot be after date_to.');
$search = request_string($_GET, 'search');
if ($search !== '') {
    $where[]='(t.ticket_number LIKE :ticket_search OR t.subject LIKE :subject_search OR t.issue LIKE :issue_search OR t.employee_name LIKE :employee_search OR t.description LIKE :description_search OR t.resolution LIKE :resolution_search)';
    $searchValue = '%' . $search . '%';
    $params['ticket_search'] = $params['subject_search'] = $params['issue_search'] = $params['employee_search'] = $params['description_search'] = $params['resolution_search'] = $searchValue;
}
$limitValue = request_string($_GET, 'limit');
$offsetValue = request_string($_GET, 'offset');
if ($limitValue !== '' && (!ctype_digit($limitValue) || (int) $limitValue < 1 || (int) $limitValue > 100)) feed_error(400, 'limit must be between 1 and 100.');
if ($offsetValue !== '' && (!ctype_digit($offsetValue) || (int) $offsetValue > 100000)) feed_error(400, 'offset must be between 0 and 100000.');
$limit = $limitValue === '' ? 50 : (int) $limitValue;
$offset = $offsetValue === '' ? 0 : (int) $offsetValue;
$sql='SELECT t.id,t.ticket_number,t.issue_escalator,t.subject,t.issue,t.priority,t.category,t.subcategory,t.department_id,t.employee_name,t.description,t.resolution,t.status,t.assignee_id,t.created_by,t.created_at,t.updated_at,t.closed_at,(SELECT COUNT(*) FROM ticket_comments c WHERE c.ticket_id=t.id AND c.visibility="shared") AS comment_count FROM tickets t WHERE '.implode(' AND ',$where).' ORDER BY t.created_at DESC LIMIT '.$limit.' OFFSET '.$offset;
$q=$pdo->prepare($sql); $q->execute($params); $data=$q->fetchAll();
$pdo->prepare('INSERT INTO api_feed_access_log(token_id,ip_address,query_params,status_code) VALUES(?,?,?,?)')->execute([$tokenId,$ip,json_encode($_GET),200]);
echo json_encode(['data'=>$data,'pagination'=>['limit'=>$limit,'offset'=>$offset,'count'=>count($data)]], JSON_UNESCAPED_SLASHES);
