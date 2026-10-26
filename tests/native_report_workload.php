<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/report_workload.php';
$pdo = db();
// Connection-local fixtures shadow the existing tables; real rows remain untouched.
foreach (['tickets','ticket_departments','ticket_assignees','departments','users'] as $table) {
    $sql = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
    $sql = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $sql);
    $sql = preg_replace('/^\s+CONSTRAINT .*\n/m', '', $sql);
    $pdo->exec(preg_replace('/,\n\)/', "\n)", $sql));
}
$pdo->exec("INSERT INTO departments(id,name,code,department_type) VALUES(1,'IT','it','internal'),(2,'HR','hr','internal'),(3,'CNG','cng','cng_staff')");
$pdo->exec("INSERT INTO users(id,role_id,username,full_name,password_hash) VALUES(1,1,'fixture-a','Reviewer A','unused'),(2,1,'fixture-b','Reviewer B','unused')");
$pdo->exec("INSERT INTO tickets(id,ticket_number,issue_escalator,subject,category,department_id,employee_name,description,status,created_by,assignee_id) VALUES(1,'TEST-1','Fixture','Fixture','Attendance',3,'Fixture','','closed',1,1),(2,'TEST-2','Fixture','Fixture','Attendance',3,'Fixture','','open',1,NULL)");
$pdo->exec('INSERT INTO ticket_departments(ticket_id,department_id) VALUES(1,1),(1,2),(1,3)');
$pdo->exec('INSERT INTO ticket_assignees(ticket_id,user_id) VALUES(1,1),(1,2)');
[$departments, $assignees] = report_native_workload($pdo, 't.deleted_at IS NULL', []);
$departmentMap = array_column($departments, null, 'name');
$assigneeMap = array_column($assignees, null, 'name');
if (count($departmentMap) !== 3 || isset($departmentMap['CNG']) || (int)$departmentMap['IT']['count'] !== 1 || (int)$departmentMap['HR']['count'] !== 1 || (int)$departmentMap['HR']['closed'] !== 1 || (int)$departmentMap['Unassigned']['count'] !== 1) throw new RuntimeException('Department counting failed.');
if (count($assigneeMap) !== 3 || (int)$assigneeMap['Reviewer A']['count'] !== 1 || (int)$assigneeMap['Reviewer B']['count'] !== 1 || (int)$assigneeMap['Unassigned']['count'] !== 1) throw new RuntimeException('Assignee counting or legacy deduplication failed.');
[$filteredDepartments, $filteredAssignees] = report_native_workload($pdo, 't.deleted_at IS NULL AND t.id=:id', ['id'=>1]);
if (count($filteredDepartments) !== 2 || count($filteredAssignees) !== 2) throw new RuntimeException('Filters were not applied consistently.');
echo "PASS: every internal department and assignee, closed counts, legacy deduplication, unassigned rows and shared filters.\n";
