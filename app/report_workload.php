<?php
declare(strict_types=1);
require_once __DIR__ . '/schema.php';

/** Count each visible ticket once for every involved internal department and assignee. */
function report_native_workload(PDO $pdo, string $where, array $params): array
{
    $typedDepartments = app_schema_has('departments', 'department_type', $pdo);
    $departmentTypeSql = $typedDepartments ? 'department_type="internal"' : '1=1';
    $departmentJoin = $typedDepartments ? 'td.department_id' : 'COALESCE(td.department_id,t.department_id)';
    $departments = $pdo->prepare('SELECT COALESCE(d.name,"Unassigned") name,COUNT(DISTINCT t.id) count,COUNT(DISTINCT CASE WHEN t.status="closed" THEN t.id END) closed FROM tickets t LEFT JOIN ticket_departments td ON td.ticket_id=t.id AND td.department_id IN (SELECT id FROM departments WHERE ' . $departmentTypeSql . ') LEFT JOIN departments d ON d.id=' . $departmentJoin . ' WHERE ' . $where . ' GROUP BY d.id,d.name ORDER BY count DESC');
    $departments->execute($params);
    $assignees = $pdo->prepare('SELECT COALESCE(a.full_name,"Unassigned") name,COUNT(DISTINCT t.id) count FROM tickets t LEFT JOIN (SELECT ticket_id,user_id FROM ticket_assignees UNION SELECT id AS ticket_id,assignee_id AS user_id FROM tickets WHERE assignee_id IS NOT NULL) assignments ON assignments.ticket_id=t.id LEFT JOIN users a ON a.id=assignments.user_id WHERE ' . $where . ' GROUP BY COALESCE(a.id,0),a.full_name ORDER BY count DESC');
    $assignees->execute($params);
    return [$departments->fetchAll(), $assignees->fetchAll()];
}
