<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

function feed_error(int $status, string $message, ?int $tokenId = null): never {
    http_response_code($status);
    if (isset($GLOBALS['feedPdo'])) $GLOBALS['feedPdo']->prepare('INSERT INTO api_feed_access_log(token_id,ip_address,query_params,status_code) VALUES(?,?,?,?)')->execute([$tokenId, $_SERVER['REMOTE_ADDR'] ?? 'unknown', json_encode($_GET), $status]);
    echo json_encode(['error' => $message]); exit;
}
$pdo = $GLOBALS['feedPdo'] = db();
$token = (string) ($_GET['token'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/', $token)) feed_error(401, 'Invalid or revoked token.');
$rows = $pdo->query('SELECT id,token FROM api_tokens WHERE revoked_at IS NULL')->fetchAll(); $tokenId = null;
foreach ($rows as $row) if (hash_equals($row['token'], $token)) { $tokenId = (int) $row['id']; break; }
if (!$tokenId) feed_error(401, 'Invalid or revoked token.');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown'; $recent = $pdo->prepare('SELECT COUNT(*) FROM api_feed_access_log WHERE ip_address=? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)'); $recent->execute([$ip]); if ((int)$recent->fetchColumn() >= 60) feed_error(429, 'Rate limit exceeded.', $tokenId);
$where = ['t.deleted_at IS NULL']; $params = [];
foreach (['status','priority','category'] as $field) if (isset($_GET[$field]) && $_GET[$field] !== '') { $where[] = "t.$field = :$field"; $params[$field] = $_GET[$field]; }
if (!empty($_GET['assigned_to'])) { $where[] = '(t.assignee_id = :assigned_to OR EXISTS (SELECT 1 FROM ticket_assignees ta WHERE ta.ticket_id=t.id AND ta.user_id=:assigned_to_pivot))'; $params['assigned_to']=(int)$_GET['assigned_to']; $params['assigned_to_pivot']=(int)$_GET['assigned_to']; }
if (!empty($_GET['date_from'])) { $where[]='DATE(t.created_at) >= :date_from'; $params['date_from']=$_GET['date_from']; }
if (!empty($_GET['date_to'])) { $where[]='DATE(t.created_at) <= :date_to'; $params['date_to']=$_GET['date_to']; }
if (!empty($_GET['search'])) { $where[]='(t.ticket_number LIKE :search OR t.subject LIKE :search OR t.issue LIKE :search OR t.employee_name LIKE :search OR t.description LIKE :search OR t.resolution LIKE :search)'; $params['search']='%'.$_GET['search'].'%'; }
$limit = min(100, max(1, (int)($_GET['limit'] ?? 50))); $offset = max(0, (int)($_GET['offset'] ?? 0));
$sql='SELECT t.id,t.ticket_number,t.issue_escalator,t.subject,t.issue,t.priority,t.category,t.subcategory,t.department_id,t.employee_name,t.description,t.resolution,t.status,t.assignee_id,t.created_by,t.created_at,t.updated_at,t.closed_at,(SELECT COUNT(*) FROM ticket_comments c WHERE c.ticket_id=t.id AND c.visibility="shared") AS comment_count FROM tickets t WHERE '.implode(' AND ',$where).' ORDER BY t.created_at DESC LIMIT '.$limit.' OFFSET '.$offset;
$q=$pdo->prepare($sql); $q->execute($params); $data=$q->fetchAll();
$pdo->prepare('INSERT INTO api_feed_access_log(token_id,ip_address,query_params,status_code) VALUES(?,?,?,?)')->execute([$tokenId,$ip,json_encode($_GET),200]);
echo json_encode(['data'=>$data,'pagination'=>['limit'=>$limit,'offset'=>$offset,'count'=>count($data)]], JSON_UNESCAPED_SLASHES);
