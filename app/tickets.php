<?php
declare(strict_types=1);
require_once __DIR__ . '/../ticket_visibility.php';

const TICKET_CATEGORIES = [
    'Performance' => ['Capacity', 'Technical'], 'Resignation' => [],
    'Personal Requests' => ['Leave Request','Work-from-Home (WFH) Request','Incentives Request','Salary Request','General Request'],
    'Attendance' => ['Tardiness','AWOL','Daily Attendance'], 'Behavioural Issues' => ['Workplace Etiquette','Dress Code'],
];
const TICKET_PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
/** Legacy preset windows, kept so old links (and index.php drill-downs) still resolve. */
const TICKET_WINDOW_PRESETS = [
    'all' => ['label' => 'All tickets', 'modifier' => null],
    'today' => ['label' => 'Today', 'modifier' => 'today'],
    '7d' => ['label' => 'Last 7 days', 'modifier' => '-6 days'],
    '30d' => ['label' => 'Last 30 days', 'modifier' => '-29 days'],
    '3m' => ['label' => 'Last 3 months', 'modifier' => '-3 months'],
    '6m' => ['label' => 'Last 6 months', 'modifier' => '-6 months'],
    '1y' => ['label' => 'Last 1 year', 'modifier' => '-1 year'],
];
const TICKET_WINDOW_OPEN_START = '1970-01-01 00:00:00';
const TICKET_CATEGORY_DEPARTMENT_CODES = [
    'Performance' => ['rm', 'customer-care', 'admin', 'insurance', 'compliance'],
    'Resignation' => ['admin'],
    'Personal Requests' => ['admin'],
    'Attendance' => ['rm', 'admin', 'compliance', 'customer-care', 'insurance'],
    'Behavioural Issues' => ['admin', 'compliance'],
];
function ticket_requestor_name(array $ticket): string {
    if (!empty($ticket['is_external'])) return trim((string) ($ticket['requester'] ?? '')) ?: '—';
    return trim((string) ($ticket['creator_name'] ?? '')) ?: '—';
}

function activity(int $ticketId, ?int $actorId, string $action, array $details = []): void {
    $json = json_encode($details, JSON_THROW_ON_ERROR);
    db()->prepare('INSERT INTO ticket_activity(ticket_id,actor_id,action,details) VALUES(?,?,?,?)')->execute([$ticketId,$actorId,$action,$json]);
    db()->prepare('INSERT INTO ticket_activity_log(ticket_id,action,changed_fields,old_values,new_values,changed_by) VALUES(?,?,?,?,?,?)')->execute([$ticketId,$action,$json,null,$json,$actorId === null ? 'system' : (string) $actorId]);
}
function active_users(): array { return db()->query('SELECT u.id,u.full_name,u.department_id,r.slug AS role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 ORDER BY u.full_name')->fetchAll(); }
function active_departments(): array { return db()->query('SELECT id,name,code FROM departments ORDER BY name')->fetchAll(); }
/**
 * Distinct requestor names the current user is allowed to see: the ticket creator for native
 * tickets (what the list shows in the Requestor column) plus the requester of each federated
 * external ticket. Used both to render the filter and to reject unknown values.
 */
function ticket_requestor_options(array $user, array $externalTickets = []): array {
    $params = [];
    $scopeSql = ticket_scope_sql($user, $params);
    $query = db()->prepare('SELECT DISTINCT creator.full_name AS requestor FROM tickets t LEFT JOIN users creator ON creator.id = t.created_by WHERE t.deleted_at IS NULL' . $scopeSql);
    foreach ($params as $key => $value) $query->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $query->execute();
    $names = [];
    foreach ($query->fetchAll() as $row) {
        $name = trim((string) ($row['requestor'] ?? ''));
        if ($name !== '') $names[$name] = true;
    }
    foreach ($externalTickets as $ticket) {
        $name = trim((string) ($ticket['requester'] ?? ''));
        if ($name !== '') $names[$name] = true;
    }
    $options = array_keys($names);
    sort($options, SORT_NATURAL | SORT_FLAG_CASE);
    return $options;
}
function category_department_ids(string $category, array $departments): array {
    $codes = TICKET_CATEGORY_DEPARTMENT_CODES[$category] ?? array_column($departments, 'code');
    return array_values(array_map('intval', array_column(array_filter($departments, static fn(array $department): bool => in_array($department['code'], $codes, true)), 'id')));
}
function category_assignee_ids(string $category, array $departments, array $users): array {
    $departmentIds = category_department_ids($category, $departments);
    return array_values(array_map('intval', array_column(array_filter($users, static fn(array $member): bool => empty($member['department_id']) || ($member['role_slug'] ?? '') === 'team-leader' || in_array((int) $member['department_id'], $departmentIds, true)), 'id')));
}
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
function ticket_scope_sql(array $user, array &$params, string $scope = ''): string {
    $visibility = ticket_visibility_sql($params);
    $role = (string) ($user['role_slug'] ?? '');
    if ($role === 'team-member') {
        $params['scope_user_id'] = (int) $user['id'];
        return $visibility . ' AND t.created_by = :scope_user_id';
    }
    if ($scope !== 'mine') return $visibility;
    $params['scope_user_id'] = (int) $user['id'];
    $params['scope_user_id_pivot'] = (int) $user['id'];
    return $visibility . ' AND (t.assignee_id = :scope_user_id OR EXISTS (SELECT 1 FROM ticket_assignees tas WHERE tas.ticket_id = t.id AND tas.user_id = :scope_user_id_pivot))';
}
function require_ticket_visible(array $user, int $ticketId): void {
    guard_ticket_access($ticketId);
    $role = (string) ($user['role_slug'] ?? '');
    if ($role !== 'team-member') return;
    $q = db()->prepare('SELECT 1 FROM tickets t WHERE t.id = ? AND t.created_by = ?');
    $q->execute([$ticketId, $user['id']]);
    if (!$q->fetchColumn()) { http_response_code(404); exit('Ticket not found.'); }
}
/**
 * Normalise a YYYY-MM-DD string, returning '' when it is not a real calendar date.
 * Keeps every date that reaches SQL or a URL strictly validated.
 */
function ticket_valid_date(string $value): string {
    $value = trim($value);
    if (preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $matches) !== 1) return '';
    return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]) ? $value : '';
}
function ticket_window_label(string $from, string $to): string {
    if ($from !== '' && $to !== '') return date('M j, Y', strtotime($from)) . ' - ' . date('M j, Y', strtotime($to));
    if ($from !== '') return 'From ' . date('M j, Y', strtotime($from));
    if ($to !== '') return 'Until ' . date('M j, Y', strtotime($to));
    return 'All tickets';
}
/**
 * Resolve the created-date window shared by the dashboard KPIs and the ticket list, from either an
 * explicit calendar selection (start/end, inclusive) or one of the legacy presets. When both a
 * calendar selection and a preset exist the calendar wins, so the two pages can never disagree.
 *
 * @return array{start:string,end:?string,from:string,to:string,preset:string,label:string}
 */
function ticket_window_bounds(string $from, string $to, string $preset = 'all'): array {
    $from = ticket_valid_date($from);
    $to = ticket_valid_date($to);
    if ($from !== '' && $to !== '' && $from > $to) { $swap = $from; $from = $to; $to = $swap; }
    if ($from === '' && $to === '') {
        $preset = isset(TICKET_WINDOW_PRESETS[$preset]) ? $preset : 'all';
        $modifier = TICKET_WINDOW_PRESETS[$preset]['modifier'];
        if ($modifier !== null) $from = date('Y-m-d', strtotime($modifier));
        return [
            'start' => $from !== '' ? $from . ' 00:00:00' : TICKET_WINDOW_OPEN_START,
            'end' => null,
            'from' => $from,
            'to' => '',
            'preset' => $preset,
            'label' => TICKET_WINDOW_PRESETS[$preset]['label'],
        ];
    }
    return [
        'start' => $from !== '' ? $from . ' 00:00:00' : TICKET_WINDOW_OPEN_START,
        'end' => $to !== '' ? $to : null,
        'from' => $from,
        'to' => $to,
        'preset' => '',
        'label' => ticket_window_label($from, $to),
    ];
}
/** SQL fragments for a resolved window. `$params` is filled in place with bound values. */
function ticket_window_sql(array $window, array &$params, string $column = 't.created_at'): array {
    $clauses = [];
    if ($window['start'] !== TICKET_WINDOW_OPEN_START) { $clauses[] = $column . ' >= :window_start'; $params['window_start'] = $window['start']; }
    if ($window['end'] !== null) { $clauses[] = $column . ' < DATE_ADD(:window_end, INTERVAL 1 DAY)'; $params['window_end'] = $window['end']; }
    return $clauses;
}
/** Same window applied in PHP, for federated external tickets that never reach SQL. */
function ticket_window_matches(?string $createdAt, array $window): bool {
    if ($createdAt === null || $createdAt === '') return $window['start'] === TICKET_WINDOW_OPEN_START && $window['end'] === null;
    if ($window['start'] !== TICKET_WINDOW_OPEN_START && $createdAt < $window['start']) return false;
    if ($window['end'] !== null && $createdAt >= date('Y-m-d 00:00:00', strtotime($window['end'] . ' +1 day'))) return false;
    return true;
}
function ticket_age_days(array $ticket, string $field): int {
    $date = $ticket[$field] ?? null;
    if (!$date) return 0;
    $timestamp = strtotime((string) $date);
    if ($timestamp === false) return 0;
    return max(0, (int) floor((time() - $timestamp) / 86400));
}
function ticket_sla_state(array $ticket): array {
    if (!empty($ticket['is_external'])) return ['external', 'N/A', 'SLA is not available from the external source.'];
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
function ticket_display_id(array $ticket): string {
    $externalId = (int) ($ticket['external_id'] ?? 0);
    if (!empty($ticket['is_external']) && $externalId > 0) return '#' . $externalId;
    $nativeId = (int) ($ticket['id'] ?? 0);
    if ($nativeId > 0) return '#' . $nativeId;
    $ticketNumber = (string) ($ticket['ticket_number'] ?? '');
    return preg_match('/(\d+)\z/', $ticketNumber, $matches) ? '#' . $matches[1] : $ticketNumber;
}
