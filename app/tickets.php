<?php
declare(strict_types=1);

const TICKET_CATEGORIES = [
    'Performance' => ['Capacity', 'Technical'], 'Resignation' => [],
    'Personal Requests' => ['Leave Request','Work-from-Home (WFH) Request','Incentives Request','Salary Request','General Request'],
    'Attendance' => ['Tardiness','AWOL','Daily Attendance'], 'Behavioural Issues' => ['Workplace Etiquette','Dress Code'],
];
function activity(int $ticketId, ?int $actorId, string $action, array $details = []): void { db()->prepare('INSERT INTO ticket_activity(ticket_id,actor_id,action,details) VALUES(?,?,?,?)')->execute([$ticketId,$actorId,$action,json_encode($details)]); }
function active_users(): array { return db()->query('SELECT id,full_name FROM users WHERE is_active=1 ORDER BY full_name')->fetchAll(); }
