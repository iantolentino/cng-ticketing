<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/leave_workflow.php';

// Connection-local temporary tables shadow application tables; no persistent rows change.
$pdo = db();
foreach (['staff_directory','calendar_events','leave_requests','users','departments','admin_audit_log','notifications'] as $table) {
    $definition = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
    $definition = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $definition);
    $definition = preg_replace('/^\s+CONSTRAINT .*\n/m', '', $definition);
    $definition = preg_replace('/,\n\)/', "\n)", $definition);
    $pdo->exec($definition);
}
$pdo->exec("INSERT INTO staff_directory(id,full_name,team_label) VALUES(1,'Workflow check','Check')");
$newRequest = static function () use ($pdo): int {
    $pdo->exec("INSERT INTO leave_requests(staff_id,leave_type,start_date,end_date,reason,status) VALUES(1,'annual','2026-11-01','2026-11-01','','pending')");
    return (int) $pdo->lastInsertId();
};
$check = static function (bool $valid, string $message): void {
    if (!$valid) throw new RuntimeException($message);
};
$actor = ['id' => 9999, 'role_slug' => 'team-leader'];
$id = $newRequest();
$check(leave_request_decide($id, 'approve', $actor)['ok'], 'TL approval failed.');
$check($pdo->query("SELECT status FROM leave_requests WHERE id=$id")->fetchColumn() === 'approved', 'Approval was not final.');
$eventId = (int) $pdo->query("SELECT id FROM calendar_events WHERE leave_request_id=$id")->fetchColumn();
$check($eventId > 0, 'Calendar/request link missing.');
$pdo->exec("INSERT INTO staff_directory(id,full_name,team_label) VALUES(2,'Other fixture','Check')");
$check(!roster_leave_save(['staff_id'=>2,'start_date'=>'2026-11-01','end_date'=>'2026-11-01','leave_type'=>'annual'], $actor, $eventId)['ok'], 'Approved request was reassigned to a different person.');
$check(!roster_leave_save(['staff_id'=>1,'start_date'=>'2026-11-04','end_date'=>'2026-11-04','leave_type'=>'annual','leave_request_id'=>$id], $actor)['ok'], 'Calendar bypassed request approval linkage.');
$check(!leave_request_decide($id, 'approve', $actor)['ok'], 'Repeated approval was accepted.');
$check((int) $pdo->query('SELECT COUNT(*) FROM calendar_events')->fetchColumn() === 1, 'Repeated approval duplicated leave.');
$collision = $newRequest();
$check(!leave_request_decide($collision, 'approve', $actor)['ok'], 'Conflicting calendar entry was accepted.');
$check($pdo->query("SELECT status FROM leave_requests WHERE id=$collision")->fetchColumn() === 'pending', 'Failed materialisation still approved the request.');
$saved = roster_leave_save(['staff_id' => 1, 'start_date' => '2026-11-02', 'end_date' => '2026-11-03', 'leave_type' => 'annual'], $actor, $eventId);
$check($saved['ok'], 'Calendar edit failed.');
$check($pdo->query("SELECT start_date FROM leave_requests WHERE id=$id")->fetchColumn() === '2026-11-02', 'Calendar edit did not update the request.');
$check(roster_leave_delete($eventId, $actor)['ok'], 'Team Leader calendar removal failed.');
$check($pdo->query("SELECT status FROM leave_requests WHERE id=$id")->fetchColumn() === 'approved', 'Team Leader removal changed the request status instead of retaining its history.');
$check((int) $pdo->query('SELECT COUNT(*) FROM calendar_events')->fetchColumn() === 1, 'Team Leader removal deleted the calendar row.');
$check($pdo->query("SELECT deleted_at IS NOT NULL FROM leave_requests WHERE id=$id")->fetchColumn() == 1, 'Team Leader removal did not mark the request.');
$check($pdo->query("SELECT deleted_at IS NOT NULL FROM calendar_events WHERE id=$eventId")->fetchColumn() == 1, 'Team Leader removal did not mark the calendar entry.');
$check(!leave_request_decide($collision, 'approve', ['id' => 9999, 'role_slug' => 'department-head'])['ok'], 'Department Head could make the final decision.');
$check(leave_request_decide($collision, 'reject', $actor)['ok'], 'Rejection failed.');
$admin = ['id' => 9999, 'role_slug' => 'super-admin'];
$pdo->exec("INSERT INTO leave_requests(staff_id,leave_type,start_date,end_date,reason,status) VALUES(2,'annual','2026-11-01','2026-11-01','','pending')");
$adminRequest = (int) $pdo->lastInsertId();
$check(leave_request_decide($adminRequest, 'approve', $admin)['ok'], 'Super Admin approval failed.');
$adminEventId = (int) $pdo->query("SELECT id FROM calendar_events WHERE leave_request_id=$adminRequest")->fetchColumn();
$edit = leave_request_admin_update($adminRequest, ['start_date'=>'2026-11-08','end_date'=>'2026-11-09','leave_type'=>'sick','reason'=>'Admin corrected dates'], $admin);
$check($edit['ok'], 'Super Admin edit failed: ' . $edit['error']);
$check($pdo->query("SELECT CONCAT(start_date,'|',end_date,'|',leave_type,'|',reason) FROM leave_requests WHERE id=$adminRequest")->fetchColumn() === '2026-11-08|2026-11-09|sick|Admin corrected dates', 'Super Admin request fields did not save.');
$check($pdo->query("SELECT CONCAT(event_date,'|',end_date,'|',leave_type) FROM calendar_events WHERE id=$adminEventId")->fetchColumn() === '2026-11-08|2026-11-09|sick', 'Super Admin edit did not synchronize the calendar entry.');
$check(!leave_request_admin_update($adminRequest, ['start_date'=>'2026-11-09','end_date'=>'2026-11-08','leave_type'=>'sick','reason'=>'invalid'], $actor)['ok'], 'Invalid date range was accepted.');
$check(!leave_request_admin_soft_delete($adminRequest, ['id'=>9998,'role_slug'=>'team-member'])['ok'], 'Team Member soft removal was allowed.');
$tlRemovalRequest = $newRequest();
$pdo->prepare("UPDATE leave_requests SET start_date='2026-11-07',end_date='2026-11-07' WHERE id=?")->execute([$tlRemovalRequest]);
$check(leave_request_admin_soft_delete($tlRemovalRequest, $actor)['ok'], 'Team Leader leave-request removal failed.');
$check((int)$pdo->query("SELECT COUNT(*) FROM leave_requests WHERE id=$tlRemovalRequest")->fetchColumn() === 1 && $pdo->query("SELECT deleted_at IS NOT NULL FROM leave_requests WHERE id=$tlRemovalRequest")->fetchColumn() == 1, 'Team Leader leave-request removal did not retain and mark the request.');
$check(roster_leave_delete($adminEventId, $admin)['ok'], 'Super Admin calendar removal soft delete failed.');
$check((int) $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE id=$adminRequest")->fetchColumn() === 1, 'Soft removal deleted the request row.');
$check((int) $pdo->query("SELECT COUNT(*) FROM calendar_events WHERE id=$adminEventId")->fetchColumn() === 1, 'Soft removal deleted the calendar row.');
$check($pdo->query("SELECT deleted_at IS NOT NULL FROM leave_requests WHERE id=$adminRequest")->fetchColumn() == 1, 'Soft removal did not mark the request.');
$check($pdo->query("SELECT deleted_at IS NOT NULL FROM calendar_events WHERE id=$adminEventId")->fetchColumn() == 1, 'Soft removal did not mark the calendar entry.');
$check(!leave_request_decide($adminRequest, 'reject', $admin)['ok'], 'A soft-removed request still accepted a decision.');
$check((int) $pdo->query('SELECT COUNT(*) FROM admin_audit_log')->fetchColumn() >= 5, 'Workflow history missing.');
echo "PASS: TL/Admin decisions, linked calendar sync, repeat protection, rollback, Team Leader/Super Admin soft removal, row retention, role boundary, and history.\n";
