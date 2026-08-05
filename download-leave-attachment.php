<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$user = require_permission('access_leave_request_module');
$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT lra.*, lr.employee_user_id, lr.status FROM leave_request_attachments lra JOIN leave_requests lr ON lr.id = lra.leave_request_id WHERE lra.id = ?');
$stmt->execute([$id]);
$attachment = $stmt->fetch();
if (!$attachment) { http_response_code(404); exit('Attachment not found.'); }

$isOwner = (int) $attachment['employee_user_id'] === (int) $user['id'];
$isApprover = in_array($user['role_slug'] ?? '', ['team-leader', 'department-head'], true);
if (!$isOwner && !$isApprover) { http_response_code(403); exit('You do not have permission to access this attachment.'); }

$path = ROOT_PATH . '/' . ltrim((string) $attachment['file_path'], '/\\');
if (!is_file($path)) { http_response_code(404); exit('Attachment file not found.'); }

header('Content-Type: ' . ($attachment['mime_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $attachment['file_name']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
