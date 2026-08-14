<?php
declare(strict_types=1);

const TICKET_CATEGORIES = [
    'Performance' => ['Capacity', 'Technical'], 'Resignation' => [],
    'Personal Requests' => ['Leave Request','Work-from-Home (WFH) Request','Incentives Request','Salary Request','General Request'],
    'Attendance' => ['Tardiness','AWOL','Daily Attendance'], 'Behavioural Issues' => ['Workplace Etiquette','Dress Code'],
];
const TICKET_PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
function activity(int $ticketId, ?int $actorId, string $action, array $details = []): void {
    $json = json_encode($details, JSON_THROW_ON_ERROR);
    db()->prepare('INSERT INTO ticket_activity(ticket_id,actor_id,action,details) VALUES(?,?,?,?)')->execute([$ticketId,$actorId,$action,$json]);
    db()->prepare('INSERT INTO ticket_activity_log(ticket_id,action,changed_fields,old_values,new_values,changed_by) VALUES(?,?,?,?,?,?)')->execute([$ticketId,$action,$json,null,$json,$actorId === null ? 'system' : (string) $actorId]);
}
function active_users(): array { return db()->query('SELECT id,full_name FROM users WHERE is_active=1 ORDER BY full_name')->fetchAll(); }
function active_departments(): array { return db()->query('SELECT id,name FROM departments ORDER BY name')->fetchAll(); }
function posted_text(string $key): string { return trim((string) ($_POST[$key] ?? '')); }
function posted_ids(string $key): array {
    $values = $_POST[$key] ?? $_POST[$key . '[]'] ?? [];
    if (!is_array($values)) $values = [$values];
    return array_values(array_unique(array_filter(array_map('intval', $values), static fn(int $id): bool => $id > 0)));
}
function valid_ids(array $ids, array $validIds): bool { return count(array_diff($ids, $validIds)) === 0; }
function selected_ids(string $table, string $column, int $ticketId): array {
    $q = db()->prepare("SELECT $column FROM $table WHERE ticket_id = ? ORDER BY $column");
    $q->execute([$ticketId]);
    return array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
}
function sync_ticket_assignees(int $ticketId, array $userIds): void {
    db()->prepare('DELETE FROM ticket_assignees WHERE ticket_id = ?')->execute([$ticketId]);
    $insert = db()->prepare('INSERT INTO ticket_assignees(ticket_id,user_id) VALUES(?,?)');
    foreach ($userIds as $userId) $insert->execute([$ticketId, $userId]);
}
function sync_ticket_departments(int $ticketId, array $departmentIds): void {
    db()->prepare('DELETE FROM ticket_departments WHERE ticket_id = ?')->execute([$ticketId]);
    $insert = db()->prepare('INSERT INTO ticket_departments(ticket_id,department_id) VALUES(?,?)');
    foreach ($departmentIds as $departmentId) $insert->execute([$ticketId, $departmentId]);
}
function ticket_scope_sql(array $user, array &$params): string {
    if (($user['role_slug'] ?? '') !== 'team-leader') return '';
    $params['scope_user_id'] = (int) $user['id'];
    $params['scope_user_id_pivot'] = (int) $user['id'];
    return ' AND (t.assignee_id = :scope_user_id OR EXISTS (SELECT 1 FROM ticket_assignees tas WHERE tas.ticket_id = t.id AND tas.user_id = :scope_user_id_pivot))';
}
function require_ticket_visible(array $user, int $ticketId): void {
    if (($user['role_slug'] ?? '') !== 'team-leader') return;
    $q = db()->prepare('SELECT 1 FROM tickets t WHERE t.id = ? AND (t.assignee_id = ? OR EXISTS (SELECT 1 FROM ticket_assignees tas WHERE tas.ticket_id = t.id AND tas.user_id = ?))');
    $q->execute([$ticketId, $user['id'], $user['id']]);
    if (!$q->fetchColumn()) { http_response_code(404); exit('Ticket not found.'); }
}
function ticket_age_days(array $ticket, string $field): int {
    $date = $ticket[$field] ?? null;
    if (!$date) return 0;
    return max(0, (int) floor((time() - strtotime((string) $date)) / 86400));
}
function ticket_sla_state(array $ticket): array {
    if (($ticket['status'] ?? '') === 'closed') return ['closed', 'Closed', 'Closed tickets are excluded from aging warnings.'];
    $ageDays = ticket_age_days($ticket, 'created_at');
    $idleDays = ticket_age_days($ticket, 'updated_at');
    $priority = $ticket['priority'] ?? 'normal';
    static $rules = null;
    if ($rules === null) {
        try { $rules = db()->query('SELECT priority,open_days,idle_days FROM sla_rules')->fetchAll(PDO::FETCH_UNIQUE); } catch (Throwable) { $rules = []; }
    }
    $rule = $rules[$priority] ?? ['open_days' => 7, 'idle_days' => 3];
    if ($ageDays >= (int) $rule['open_days']) return ['overdue', 'Overdue', $ageDays . ' days open'];
    if ($idleDays >= (int) $rule['idle_days']) return ['watch', 'Watch', $idleDays . ' days idle'];
    return ['ok', 'On track', $ageDays . ' days open'];
}
