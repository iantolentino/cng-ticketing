<?php
require __DIR__ . '/app/bootstrap.php'; require __DIR__ . '/app/tickets.php';
$user = require_permission('bulk_ticket_actions');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST required.'); }
verify_csrf(); $ids = posted_ids('ticket_ids'); $action = (string) ($_POST['bulk_action'] ?? '');
if (!$ids || !in_array($action, ['status','priority','delete'], true)) redirect('index.php');
$placeholders = implode(',', array_fill(0, count($ids), '?')); $visible = db()->prepare("SELECT id FROM tickets WHERE deleted_at IS NULL AND id IN ($placeholders)"); $visible->execute($ids); $ids = array_map('intval', $visible->fetchAll(PDO::FETCH_COLUMN));
if (!$ids) redirect('index.php?notice=bulk_empty');
$pdo = db(); $pdo->beginTransaction();
try {
    if ($action === 'status' && in_array($_POST['status'] ?? '', ['open','in_progress','pending','closed'], true)) { $status = (string) $_POST['status']; $q = $pdo->prepare("UPDATE tickets SET status=?, closed_at=CASE WHEN ?='closed' THEN NOW() ELSE NULL END WHERE id IN ($placeholders) AND deleted_at IS NULL"); $q->execute(array_merge([$status, $status], $ids)); }
    elseif ($action === 'priority' && isset(TICKET_PRIORITIES[$_POST['priority'] ?? ''])) { $q = $pdo->prepare("UPDATE tickets SET priority=? WHERE id IN ($placeholders) AND deleted_at IS NULL"); $q->execute(array_merge([(string) $_POST['priority']], $ids)); }
    elseif ($action === 'delete') { $q = $pdo->prepare("UPDATE tickets SET deleted_at=NOW(), deleted_by=? WHERE id IN ($placeholders) AND deleted_at IS NULL"); $q->execute(array_merge([(int) $user['id']], $ids)); }
    else { $pdo->rollBack(); redirect('index.php'); }
    foreach ($ids as $id) activity($id, (int) $user['id'], 'bulk_' . $action, ['ticket_count' => count($ids)]);
    $pdo->commit(); redirect('index.php?notice=bulk_updated');
} catch (Throwable) { if ($pdo->inTransaction()) $pdo->rollBack(); http_response_code(500); exit('Bulk action could not be completed.'); }
