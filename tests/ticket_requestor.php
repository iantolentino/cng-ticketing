<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/tickets.php';

$cases = [
    [['creator_name' => 'Jane Creator', 'employee_name' => 'John Employee'], 'Jane Creator'],
    [['creator_name' => 'Jane Creator', 'issue_escalator' => 'Other Name'], 'Jane Creator'],
    [['creator_name' => ' Jane Creator '], 'Jane Creator'],
    [['employee_name' => 'John Employee'], '—'],
    [['creator_name' => '', 'employee_name' => 'John Employee'], '—'],
    [['is_external' => true, 'requester' => 'External Requester', 'employee_name' => 'Employee'], 'External Requester'],
    [['is_external' => true, 'requester' => ''], '—'],
];
foreach ($cases as [$ticket, $expected]) {
    if (ticket_requestor_name($ticket) !== $expected) throw new RuntimeException('Incorrect requestor.');
}
echo count($cases) . " requestor checks passed.\n";
