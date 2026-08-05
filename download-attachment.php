<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';

$user = require_permission('view_attachments');
$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT ta.*, t.id AS ticket_id FROM ticket_attachments ta JOIN tickets t ON t.id = ta.ticket_id WHERE ta.id = ? AND t.deleted_at IS NULL');
$stmt->execute([$id]);
$attachment = $stmt->fetch();
if (!$attachment) { http_response_code(404); exit('Attachment not found.'); }
require_ticket_visible($user, (int) $attachment['ticket_id']);

$path = ROOT_PATH . '/' . ltrim((string) ($attachment['file_path'] ?: ''), '/\\');
if (!is_file($path)) { http_response_code(404); exit('Attachment file not found.'); }

$downloadName = $attachment['file_name'] ?: $attachment['original_name'];
header('Content-Type: ' . ($attachment['mime_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
