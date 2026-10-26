<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/roster.php';
require __DIR__ . '/../app/notifications.php';
$pdo = db();
foreach (['staff_directory','departments','users','leave_requests','leave_request_attachments','notifications','admin_audit_log'] as $table) {
    $sql = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
    $sql = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $sql);
    $sql = preg_replace('/^\s+CONSTRAINT .*\n/m', '', $sql);
    $pdo->exec(preg_replace('/,\n\)/', "\n)", $sql));
}
$pdo->exec("INSERT INTO staff_directory(id,full_name,team_label) VALUES(1,'Intake fixture','Fixture')");
$tlRole = (int)$pdo->query("SELECT id FROM roles WHERE slug='team-leader'")->fetchColumn();
$pdo->prepare('INSERT INTO users(id,role_id,username,full_name,password_hash) VALUES(?,?,?,?,?)')->execute([9999,$tlRole,'intake-fixture','Reviewer','unused']);
$_SERVER['REQUEST_METHOD'] = 'POST'; $_GET=[]; $_FILES=[];
unset($_SESSION['user_id']);
$source = file_get_contents(__DIR__.'/../public-leave-request.php');
$start = strpos($source, '$publicLeaveAvailable =');
$code = substr($source, $start, strpos($source, '?><!doctype html>') - $start);
// Intercept only the successful redirect; all validation, SQL, notification and history code runs.
$code = str_replace("redirect('leave-request.php?submitted=1');", "\$testRedirect = 'submitted';", $code);
$valid = ['csrf_token'=>csrf_token(),'staff_id'=>'1','requester_email'=>'fixture@example.com','start_date'=>'2026-11-01','end_date'=>'2026-11-02','leave_type'=>'annual'];
$_POST=$valid; $testRedirect=''; eval($code);
if ($testRedirect !== 'submitted' || $error !== '') throw new RuntimeException('Anonymous no-file intake failed.');
$request = $pdo->query('SELECT * FROM leave_requests')->fetch();
if (!$request || $request['status'] !== 'pending' || (int)$request['staff_id'] !== 1 || $request['employee_user_id'] !== null || $request['reason'] !== '') throw new RuntimeException('Anonymous request linkage failed.');
if ((int)$pdo->query('SELECT COUNT(*) FROM leave_request_attachments')->fetchColumn() !== 0 || (int)$pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn() !== 1 || (int)$pdo->query('SELECT COUNT(*) FROM admin_audit_log')->fetchColumn() !== 1) throw new RuntimeException('Attachment, queue notification or history failed.');
foreach ([['requester_email'=>''],['staff_id'=>'999'],['end_date'=>'2026-10-31']] as $invalid) {
    $_POST=array_replace($valid,$invalid); $testRedirect=''; eval($code);
    if ($error === '' || $testRedirect !== '' || (int)$pdo->query('SELECT COUNT(*) FROM leave_requests')->fetchColumn() !== 1) throw new RuntimeException('Invalid intake was accepted.');
}
$pdo->exec('DELETE FROM admin_audit_log'); // Only the connection-local fixture history.
$pdo->exec("ALTER TABLE admin_audit_log ADD CONSTRAINT fixture_reject_intake_audit CHECK(action <> 'public_leave_request_created')");
$_POST=$valid; $testRedirect=''; eval($code);
if ($error === '' || $testRedirect !== '' || (int)$pdo->query('SELECT COUNT(*) FROM leave_requests')->fetchColumn() !== 1 || (int)$pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn() !== 1) throw new RuntimeException('Failed history write did not roll back request and notification.');
echo "PASS: anonymous dropdown identity, required email/name/dates, optional proof/reason, one roster-linked pending request, TL notification and history.\n";
echo "PASS: failed audit insert rolls back intake and notifications.\n";
