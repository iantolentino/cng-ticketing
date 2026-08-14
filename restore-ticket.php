<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';
$admin = require_login();
if (($admin['role_slug'] ?? '') !== 'super-admin') { http_response_code(403); exit('Super Admin access required.'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST required.'); }
verify_csrf();
$id = (int) ($_POST['id'] ?? 0);
$q = db()->prepare('SELECT id,ticket_number FROM tickets WHERE id=? AND deleted_at IS NOT NULL');
$q->execute([$id]);
$ticket = $q->fetch();
if (!$ticket) { http_response_code(404); exit('Deleted ticket not found.'); }
$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE tickets SET deleted_at=NULL, deleted_by=NULL WHERE id=? AND deleted_at IS NOT NULL')->execute([$id]);
    if ($pdo->rowCount() !== 1) throw new RuntimeException('Ticket was already restored.');
    activity($id, (int) $admin['id'], 'restored', ['ticket_number' => $ticket['ticket_number']]);
    audit_admin_action((int) $admin['id'], 'ticket_restored', 'ticket', $id, ['ticket_number' => $ticket['ticket_number']]);
    $pdo->commit();
    redirect('deleted-tickets.php?notice=ticket_restored');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    exit('Ticket could not be restored.');
}
