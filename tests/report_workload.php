<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/external_tickets.php';
$source = file_get_contents(dirname(__DIR__) . '/reports.php');
$start = strpos($source, '// Apply the same normalized external-ticket filters');
$end = strpos($source, '$leave =', $start);
$code = substr($source, $start, $end - $start);
function workload_case(string $department, array $employees, bool $failed = false): array {
    global $code;
    $from = '2026-09-01'; $to = '2026-09-14';
    $selectedDepartment = $department; $selectedEmployees = $employees;
    $reportDepartments = [['id'=>1,'name'=>'Operations'],['id'=>2,'name'=>'Support']];
    $user = ['role_slug'=>'super-admin'];
    $departmentRows = [['name'=>'Operations','count'=>2,'closed'=>1]];
    $assigneeRows = [['name'=>'Alex','count'=>2]];
    $base = ['created_at'=>'2026-09-14 23:59:59','status'=>'closed','department'=>'Operations','assignees'=>'Alex','employee_name'=>'Alice'];
    $reportExternalTickets = ['tickets'=>[
        $base,
        array_replace($base,['department'=>'Support','assignees'=>'','status'=>'open','employee_name'=>'Ben']),
        array_replace($base,['created_at'=>'2026-09-15 00:00:00']),
        array_replace($base,['created_at'=>'2026-08-31 23:59:59']),
    ], 'errors'=>$failed?['Unavailable source']:[]];
    eval($code);
    return [$departmentRows, $assigneeRows, $workloadSourcesIncomplete];
}
function expect_workload(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
[$d,$a,$incomplete] = workload_case('',[]);
expect_workload($d[0]['name']==='Operations' && $d[0]['count']===3 && $d[0]['closed']===2, 'native and external workloads combine closed counts');
expect_workload(array_sum(array_column($d,'count'))===4, 'inclusive end date and out-of-range tickets handled');
expect_workload($a[0]['count']===3 && $a[1]['name']==='Unassigned', 'assignee merging and missing assignee');
[$d,$a] = workload_case('1',[]);
expect_workload(count($d)===1 && $d[0]['count']===3, 'department filter uses mapped external department');
[$d,$a] = workload_case('', ['alice']);
expect_workload(count($d)===1 && $d[0]['count']===3, 'employee matching follows case-insensitive external export filter');
[$d,$a,$incomplete] = workload_case('',[],true);
expect_workload($incomplete, 'unavailable sources marked incomplete');
expect_workload(!str_contains($source,'class="report-metrics"'), 'metric cards removed');
echo "7 workload checks passed\n";