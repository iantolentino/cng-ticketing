<?php
require __DIR__.'/app/bootstrap.php'; require __DIR__.'/app/tickets.php';
$user=require_permission('delete_tickets'); $id=(int)($_POST['id']??0); verify_csrf();
$q=db()->prepare('SELECT id,ticket_number FROM tickets WHERE id=? AND deleted_at IS NULL');$q->execute([$id]);$ticket=$q->fetch();if(!$ticket){http_response_code(404);exit('Ticket not found.');}
$pdo=db();$pdo->beginTransaction();try{activity($id,$user['id'],'soft_deleted',['ticket_number'=>$ticket['ticket_number']]);$pdo->prepare('UPDATE tickets SET deleted_at=NOW(),deleted_by=? WHERE id=?')->execute([$user['id'],$id]);$pdo->commit();redirect('index.php?notice=ticket_deleted');}catch(Throwable $e){$pdo->rollBack();http_response_code(500);exit('Ticket could not be deleted.');}
