<?php
declare(strict_types=1);
require_once __DIR__ . '/schema.php';

/**
 * Shared roster, shift and leave helpers for the Team Attendance and Team Calendar pages.
 *
 * Leave is stored once, in calendar_events, as a row whose staff_id points at the roster
 * (`staff_directory`). Both pages read and write that same row, which is what keeps the
 * attendance list and the calendar in sync with each other.
 */

/** Employment status vocabulary for a roster staff member. */
const ROSTER_STATUSES = [
    'active' => 'Active',
    'inactive' => 'Inactive',
    'for_replacement' => 'For replacement',
    'pulled_out' => 'Pulled out from account',
    'resigned' => 'Resigned',
];

/** Leave types that can be recorded against a roster staff member. */
const ROSTER_LEAVE_TYPES = [
    'annual' => 'Annual',
    'sick' => 'Sick',
    'emergency' => 'Emergency',
    'half_day' => 'Half-day',
    'birthday' => 'Birthday',
    'bereavement' => 'Bereavement',
    'paternity' => 'Paternity',
    'maternity' => 'Maternity',
    'undertime' => 'Undertime',
    'unpaid' => 'Unpaid',
];

/** Ordered weekday keys used by the 5-day shift editor. */
const ROSTER_WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

/** Default working week for a new shift: Monday to Friday. */
const ROSTER_DEFAULT_SHIFT_DAYS = 'Mon,Tue,Wed,Thu,Fri';

/** statuses that still count as part of the working roster. */
const ROSTER_WORKING_STATUSES = ['active', 'for_replacement'];

function roster_can_manage_attendance(array $user): bool
{
    $role = (string) ($user['role_slug'] ?? '');
    return in_array($role, ['super-admin', 'pod-leader', 'team-leader'], true) || user_can('manage_roles');
}

function roster_can_manage_calendar(array $user): bool
{
    return !in_array((string) ($user['role_slug'] ?? ''), ['team-member', 'cng-admin', 'management'], true);
}

function roster_status_label(string $status): string
{
    return ROSTER_STATUSES[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function roster_leave_type_label(?string $type): string
{
    $type = (string) $type;
    return ROSTER_LEAVE_TYPES[$type] ?? ($type !== '' ? ucfirst(str_replace('_', ' ', $type)) : 'Leave');
}

/** Parse a stored "Mon,Tue,…" shift string into a de-duplicated, week-ordered list. */
function roster_shift_days(?string $raw): array
{
    $wanted = [];
    foreach (explode(',', (string) $raw) as $token) {
        $token = trim($token);
        foreach (ROSTER_WEEKDAYS as $weekday) {
            if (strcasecmp($token, $weekday) === 0) { $wanted[$weekday] = true; break; }
        }
    }
    return array_values(array_filter(ROSTER_WEEKDAYS, static fn(string $weekday): bool => isset($wanted[$weekday])));
}

/** Days not scheduled as work days; an empty schedule is shown as all days off. */
function roster_day_off_days(?string $shiftDays): array
{
    return array_values(array_diff(ROSTER_WEEKDAYS, roster_shift_days($shiftDays)));
}

/** De-duplicate posted day-off checkboxes in week order. */
function roster_day_off_days_from_post(array $source): array
{
    $posted = $source['days_off'] ?? [];
    if (!is_array($posted)) return [];
    $wanted = [];
    foreach ($posted as $value) {
        if (!is_scalar($value)) continue;
        $token = trim((string) $value);
        foreach (ROSTER_WEEKDAYS as $weekday) {
            if (strcasecmp($token, $weekday) === 0) { $wanted[$weekday] = true; break; }
        }
    }
    return array_values(array_filter(ROSTER_WEEKDAYS, static fn(string $weekday): bool => isset($wanted[$weekday])));
}

/** Convert posted day-off checkboxes into the stored working-day list. */
function roster_shift_days_from_day_off_post(array $source): string
{
    $daysOff = roster_day_off_days_from_post($source);
    return implode(',', array_values(array_filter(ROSTER_WEEKDAYS, static fn(string $weekday): bool => !in_array($weekday, $daysOff, true))));
}

/** Merge posted weekday checkboxes into the stored form, keeping week order. */
function roster_shift_days_from_post(array $source): string
{
    $posted = $source['shift_days'] ?? [];
    if (!is_array($posted)) return '';
    $wanted = [];
    foreach ($posted as $value) {
        if (!is_scalar($value)) continue;
        $token = trim((string) $value);
        foreach (ROSTER_WEEKDAYS as $weekday) {
            if (strcasecmp($token, $weekday) === 0) { $wanted[$weekday] = true; break; }
        }
    }
    return implode(',', array_values(array_filter(ROSTER_WEEKDAYS, static fn(string $weekday): bool => isset($wanted[$weekday]))));
}

/** Render ["Mon","Tue","Wed","Thu","Fri"] as "Mon-Fri" for compact display. */
function roster_shift_day_range(array $days): string
{
    $positions = [];
    foreach (ROSTER_WEEKDAYS as $index => $weekday) {
        if (in_array($weekday, $days, true)) $positions[] = $index;
    }
    if (!$positions) return '';
    $groups = [];
    $start = $positions[0];
    $previous = $positions[0];
    foreach (array_slice($positions, 1) as $position) {
        if ($position === $previous + 1) { $previous = $position; continue; }
        $groups[] = [$start, $previous];
        $start = $previous = $position;
    }
    $groups[] = [$start, $previous];
    $labels = [];
    foreach ($groups as [$first, $last]) {
        $labels[] = $first === $last ? ROSTER_WEEKDAYS[$first] : ROSTER_WEEKDAYS[$first] . '-' . ROSTER_WEEKDAYS[$last];
    }
    return implode(', ', $labels);
}

/** "08:00:00" -> "8:00 AM"; blank/invalid -> "". */
function roster_time_label(?string $time): string
{
    $time = trim((string) $time);
    if ($time === '') return '';
    $parsed = DateTimeImmutable::createFromFormat('!H:i:s', strlen($time) === 5 ? $time . ':00' : $time);
    if (!$parsed) $parsed = DateTimeImmutable::createFromFormat('!H:i', substr($time, 0, 5));
    return $parsed ? $parsed->format('g:i A') : '';
}

/** "08:00:00" -> "08:00" for an <input type="time">. */
function roster_time_input(?string $time): string
{
    $time = trim((string) $time);
    return preg_match('/^\d{2}:\d{2}/', $time) === 1 ? substr($time, 0, 5) : '';
}

/** Normalise a posted time to "HH:MM:00", or null when blank. */
function roster_time_value(array $source, string $key): ?string
{
    $value = trim((string) ($source[$key] ?? ''));
    if ($value === '') return null;
    $parsed = DateTimeImmutable::createFromFormat('!H:i', $value);
    return $parsed && $parsed->format('H:i') === $value ? $value . ':00' : null;
}

/** Human summary of a staff member's shift, falling back to the legacy free-text field. */
function roster_shift_summary(?array $staff): string
{
    if (!$staff) return '';
    $parts = [];
    $shiftLabel = trim((string) ($staff['shift_label'] ?? ''));
    if ($shiftLabel !== '') $parts[] = $shiftLabel;
    $days = roster_shift_days((string) ($staff['shift_days'] ?? ''));
    if ($days) $parts[] = roster_shift_day_range($days);
    $start = roster_time_label($staff['shift_start'] ?? null);
    $end = roster_time_label($staff['shift_end'] ?? null);
    if ($start !== '' && $end !== '') $parts[] = $start . ' - ' . $end;
    elseif ($start !== '') $parts[] = 'from ' . $start;
    elseif ($end !== '') $parts[] = 'until ' . $end;
    $dstStart = roster_time_label($staff['shift_dst_start'] ?? null);
    $dstEnd = roster_time_label($staff['shift_dst_end'] ?? null);
    if ($dstStart !== '' && $dstEnd !== '') $parts[] = 'DST ' . $dstStart . ' - ' . $dstEnd;
    if ($days) {
        $daysOff = roster_day_off_days((string) ($staff['shift_days'] ?? ''));
        if ($daysOff) $parts[] = 'Off ' . roster_shift_day_range($daysOff);
    }
    $summary = implode(' · ', $parts);
    if ($summary === '') $summary = trim((string) ($staff['shift_schedule'] ?? ''));
    if ($summary !== '' && (int) ($staff['break_minutes'] ?? 0) > 0) $summary .= ' · ' . (int) $staff['break_minutes'] . 'm break';
    return $summary;
}

/** Keep the legacy free-text shift_schedule column readable for non-attendance screens. */
function roster_shift_schedule_string(string $days, ?string $start, ?string $end, string $shiftLabel = ''): string
{
    $parts = [];
    if (trim($shiftLabel) !== '') $parts[] = trim($shiftLabel);
    $range = roster_shift_day_range(roster_shift_days($days));
    if ($range !== '') $parts[] = $range;
    $startLabel = roster_time_label($start);
    $endLabel = roster_time_label($end);
    if ($startLabel !== '' && $endLabel !== '') $parts[] = $startLabel . '-' . $endLabel;
    elseif ($startLabel !== '') $parts[] = 'from ' . $startLabel;
    return implode(' ', $parts);
}

/**
 * Roster rows with department, team leader and leave counters.
 *
 * @param array{statuses?:list<string>,department_id?:int,department_unassigned?:bool,team_label?:string,search?:string,staff_id?:int} $options
 */
function roster_staff_rows(array $options = []): array
{
    $where = [];
    $params = [];
    if (!empty($options['staff_id'])) {
        $where[] = 'sd.id = :staff_id';
        $params['staff_id'] = (int) $options['staff_id'];
    }
    $statuses = $options['statuses'] ?? null;
    if (is_array($statuses) && $statuses) {
        $placeholders = [];
        foreach (array_values($statuses) as $index => $status) {
            $placeholders[] = ':status_' . $index;
            $params['status_' . $index] = (string) $status;
        }
        $where[] = 'sd.employment_status IN (' . implode(',', $placeholders) . ')';
    }
    if (!empty($options['department_id'])) {
        $where[] = 'sd.department_id = :department_id';
        $params['department_id'] = (int) $options['department_id'];
    }
    if (isset($options['department_unassigned']) && $options['department_unassigned']) {
        $where[] = 'sd.department_id IS NULL';
    }
    if (isset($options['team_label']) && $options['team_label'] !== '') {
        $where[] = 'sd.team_label = :team_label';
        $params['team_label'] = (string) $options['team_label'];
    }
    if (isset($options['search']) && $options['search'] !== '') {
        // One distinct placeholder per LIKE: PDO forbids reusing a named parameter.
        $needle = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $options['search']) . '%';
        $where[] = '(sd.full_name LIKE :search_name OR sd.team_label LIKE :search_team OR d.name LIKE :search_department)';
        $params['search_name'] = $needle;
        $params['search_team'] = $needle;
        $params['search_department'] = $needle;
    }
    $activeCalendarLeave = app_schema_has('calendar_events', 'deleted_at') ? ' AND ce.deleted_at IS NULL' : '';
    $leaderSql = app_schema_has('staff_directory', 'team_leader_name') ? "COALESCE(NULLIF(sd.team_leader_name, ''), leader.full_name)" : 'leader.full_name';
    $sql = 'SELECT sd.*, d.name AS department, d.code AS department_code,
        account_status.account_count AS linked_account_count, account_status.account_active AS linked_account_active,
        ' . $leaderSql . ' AS team_leader_display,
        repl.full_name AS replacement_for_name,
        (SELECT COUNT(*) FROM calendar_events ce WHERE ce.staff_id = sd.id AND ce.event_type = \'leave\' AND COALESCE(ce.end_date, ce.event_date) >= CURDATE()' . $activeCalendarLeave . ') AS upcoming_leave,
        (SELECT MIN(ce.event_date) FROM calendar_events ce WHERE ce.staff_id = sd.id AND ce.event_type = \'leave\' AND COALESCE(ce.end_date, ce.event_date) >= CURDATE()' . $activeCalendarLeave . ') AS next_leave_date
        FROM staff_directory sd
        LEFT JOIN departments d ON d.id = sd.department_id
        LEFT JOIN (SELECT LOWER(TRIM(full_name)) AS name_key, COUNT(*) AS account_count, MAX(is_active) AS account_active FROM users GROUP BY LOWER(TRIM(full_name))) account_status ON account_status.name_key = LOWER(TRIM(sd.full_name))
        LEFT JOIN staff_directory leader ON leader.id = sd.team_leader_id
        LEFT JOIN staff_directory repl ON repl.id = sd.replacement_for_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . " ORDER BY FIELD(sd.employment_status,'active','for_replacement','inactive','pulled_out','resigned'), sd.team_label, sd.full_name";
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function roster_staff_load(int $staffId): ?array
{
    if ($staffId < 1) return null;
    $rows = roster_staff_rows(['staff_id' => $staffId]);
    return $rows[0] ?? null;
}

/** Distinct team labels present in the roster, for the team filter. */
function roster_team_labels(): array
{
    return db()->query('SELECT DISTINCT team_label FROM staff_directory ORDER BY team_label')->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Leave rows (calendar_events with a staff link) with the owning staff member attached.
 *
 * @param array{staff_id?:int,from?:string,to?:string,department_id?:int,team_label?:string,search?:string,event_id?:int,statuses?:list<string>} $filters
 */
function roster_leave_rows(array $filters = []): array
{
    $where = ["ce.event_type = 'leave'", 'ce.staff_id IS NOT NULL'];
    if (app_schema_has('calendar_events', 'deleted_at')) $where[] = 'ce.deleted_at IS NULL';
    $params = [];
    if (!empty($filters['event_id'])) {
        $where[] = 'ce.id = :event_id';
        $params['event_id'] = (int) $filters['event_id'];
    }
    if (!empty($filters['staff_id'])) {
        $where[] = 'ce.staff_id = :staff_id';
        $params['staff_id'] = (int) $filters['staff_id'];
    }
    if (!empty($filters['from'])) {
        $where[] = 'COALESCE(ce.end_date, ce.event_date) >= :from_date';
        $params['from_date'] = (string) $filters['from'];
    }
    if (!empty($filters['to'])) {
        $where[] = 'ce.event_date <= :to_date';
        $params['to_date'] = (string) $filters['to'];
    }
    if (!empty($filters['department_id'])) {
        $where[] = 'sd.department_id = :department_id';
        $params['department_id'] = (int) $filters['department_id'];
    }
    if (isset($filters['department_unassigned']) && $filters['department_unassigned']) {
        $where[] = 'sd.department_id IS NULL';
    }
    if (isset($filters['team_label']) && $filters['team_label'] !== '') {
        $where[] = 'sd.team_label = :team_label';
        $params['team_label'] = (string) $filters['team_label'];
    }
    if (isset($filters['search']) && $filters['search'] !== '') {
        $needle = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']) . '%';
        $where[] = '(sd.full_name LIKE :search_name OR ce.title LIKE :search_title)';
        $params['search_name'] = $needle;
        $params['search_title'] = $needle;
    }
    if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
        $placeholders = [];
        foreach (array_values($filters['statuses']) as $index => $status) {
            $placeholders[] = ':leave_status_' . $index;
            $params['leave_status_' . $index] = (string) $status;
        }
        $where[] = 'sd.employment_status IN (' . implode(',', $placeholders) . ')';
    }
    $hasLink = app_schema_has('calendar_events', 'leave_request_id');
    $linkSql = $hasLink ? 'ce.leave_request_id, lr.reason AS request_reason' : 'NULL AS leave_request_id, NULL AS request_reason';
    $joinSql = $hasLink ? 'LEFT JOIN leave_requests lr ON lr.id = ce.leave_request_id' : '';
    $statement = db()->prepare('SELECT ce.id, ce.title, ce.event_date, ce.end_date, ce.leave_type, ce.notes, ce.staff_id, ' . $linkSql . ',
        sd.full_name, sd.team_label, sd.employment_status, sd.department_id, d.name AS department,
        u.full_name AS recorded_by
        FROM calendar_events ce
        JOIN staff_directory sd ON sd.id = ce.staff_id
        ' . $joinSql . '
        LEFT JOIN departments d ON d.id = sd.department_id
        LEFT JOIN users u ON u.id = ce.created_by
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY ce.event_date DESC, sd.full_name');
    $statement->execute($params);
    return $statement->fetchAll();
}

function roster_leave_load(int $eventId): ?array
{
    if ($eventId < 1) return null;
    $rows = roster_leave_rows(['event_id' => $eventId]);
    return $rows[0] ?? null;
}

/** Load any calendar row, so an existing plain event can be converted into a leave. */
function roster_event_load(int $eventId): ?array
{
    if ($eventId < 1) return null;
    $where = app_schema_has('calendar_events', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
    $statement = db()->prepare('SELECT * FROM calendar_events WHERE id = ?' . $where);
    $statement->execute([$eventId]);
    return $statement->fetch() ?: null;
}

/** Inclusive list of dates a leave row covers, clipped to a window. */
function roster_leave_days(array $leave, string $windowStart, string $windowEnd): array
{
    $start = max((string) $leave['event_date'], $windowStart);
    $end = min((string) ($leave['end_date'] ?: $leave['event_date']), $windowEnd);
    if ($start > $end) return [];
    $days = [];
    $cursor = strtotime($start);
    $limit = strtotime($end);
    // A malformed range can never walk away: the window caps the loop at ~2 years of days.
    for ($guard = 0; $cursor !== false && $cursor <= $limit && $guard < 800; $guard++, $cursor = strtotime('+1 day', $cursor)) {
        $days[] = date('Y-m-d', $cursor);
    }
    return $days;
}

/**
 * Create or update a leave row for a roster staff member.
 *
 * @return array{ok:bool,error:string,id:int}
 */
function roster_leave_save(array $input, array $user, int $eventId = 0): array
{
    $staffId = (int) ($input['staff_id'] ?? 0);
    $start = trim((string) ($input['start_date'] ?? ''));
    $end = trim((string) ($input['end_date'] ?? ''));
    $type = trim((string) ($input['leave_type'] ?? 'annual'));
    $notes = trim((string) ($input['notes'] ?? ''));

    if ($end === '') $end = $start;
    $startValue = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
    $endValue = DateTimeImmutable::createFromFormat('!Y-m-d', $end);
    if (!$startValue || $startValue->format('Y-m-d') !== $start || !$endValue || $endValue->format('Y-m-d') !== $end) {
        return ['ok' => false, 'error' => 'Choose a valid start and end date.', 'id' => 0];
    }
    if ($end < $start) return ['ok' => false, 'error' => 'The end date cannot be before the start date.', 'id' => 0];
    if (!isset(ROSTER_LEAVE_TYPES[$type])) return ['ok' => false, 'error' => 'Choose a valid leave type.', 'id' => 0];
    if (mb_strlen($notes) > 255) return ['ok' => false, 'error' => 'Keep the note under 255 characters.', 'id' => 0];

    $staff = roster_staff_load($staffId);
    if (!$staff) return ['ok' => false, 'error' => 'Choose a staff member from the roster.', 'id' => 0];
    if (($staff['employment_status'] ?? 'active') === 'resigned' && $eventId === 0) {
        return ['ok' => false, 'error' => 'Resigned staff cannot be given new leave. Set their status back to Active first.', 'id' => 0];
    }

    $title = 'Leave: ' . $staff['full_name'] . ' - ' . roster_leave_type_label($type);
    $pdo = db();
    $ownsTransaction = !$pdo->inTransaction();
    if (!empty($input['leave_request_id']) && ($ownsTransaction || !in_array(($user['role_slug'] ?? ''), ['team-leader', 'super-admin'], true))) {
        return ['ok'=>false, 'error'=>'A request can be linked only through its Team Leader approval.', 'id'=>0];
    }
    if (!empty($input['leave_request_id']) && !app_linked_leave_available($pdo)) return ['ok'=>false, 'error'=>'This request uses the existing two-stage leave approval.', 'id'=>0];
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        if ($eventId > 0) {
            $existing = roster_event_load($eventId);
            if (!$existing) throw new InvalidArgumentException('That calendar entry no longer exists.');
            if (!app_schema_has('calendar_events', 'leave_request_id', $pdo) && preg_match('/\AApproved leave request #[0-9]+\z/', (string) ($existing['notes'] ?? '')) && !(($user['role_slug'] ?? '') === 'super-admin' && !empty($input['admin_request_edit']))) throw new InvalidArgumentException('Approved request dates can only be changed from the leave request record.');
            if (!empty($existing['leave_request_id']) && (int)$existing['staff_id'] !== $staffId) throw new InvalidArgumentException('This approved request belongs to its original staff member. Cancel it and create new leave for another person.');
            $pdo->prepare('UPDATE calendar_events SET title=?, staff_id=?, event_date=?, end_date=?, leave_type=?, notes=?, event_type=\'leave\' WHERE id=?')
                ->execute([$title, $staffId, $start, $end === $start ? null : $end, $type, $notes !== '' ? $notes : null, $eventId]);
            if (!empty($existing['leave_request_id'])) {
                $pdo->prepare('UPDATE leave_requests SET staff_id=?, start_date=?, end_date=?, leave_type=? WHERE id=?')
                    ->execute([$staffId, $start, $end, $type, (int) $existing['leave_request_id']]);
                audit_admin_action((int) $user['id'], 'approved_leave_updated', 'leave_request', (int) $existing['leave_request_id'], ['calendar_event_id' => $eventId, 'before' => ['staff_id' => $existing['staff_id'], 'start_date' => $existing['event_date'], 'end_date' => $existing['end_date'] ?: $existing['event_date'], 'leave_type' => $existing['leave_type']], 'after' => ['staff_id' => $staffId, 'start_date' => $start, 'end_date' => $end, 'leave_type' => $type]]);
            }
            audit_admin_action((int) $user['id'], 'staff_leave_updated', 'calendar_event', $eventId, ['staff_id' => $staffId, 'start_date' => $start, 'end_date' => $end, 'leave_type' => $type]);
        } else {
            $columns = 'title, staff_id, event_date, end_date, leave_type, notes, event_type, created_by';
            $values = [$title, $staffId, $start, $end === $start ? null : $end, $type, $notes !== '' ? $notes : null, 'leave', (int) $user['id']];
            if (app_schema_has('calendar_events', 'leave_request_id', $pdo)) {
                $columns .= ', leave_request_id';
                $values[] = ((int) ($input['leave_request_id'] ?? 0)) ?: null;
            }
            $pdo->prepare('INSERT INTO calendar_events(' . $columns . ') VALUES(' . implode(',', array_fill(0, count($values), '?')) . ')')->execute($values);
            $eventId = (int) $pdo->lastInsertId();
            audit_admin_action((int) $user['id'], 'staff_leave_created', 'calendar_event', $eventId, ['staff_id' => $staffId, 'start_date' => $start, 'end_date' => $end, 'leave_type' => $type]);
        }
        if ($ownsTransaction) $pdo->commit();
    } catch (PDOException $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        // uq_calendar_events_staff_leave_start: the same staff member cannot start the same
        // leave type twice on one day. Catching it here turns a double submit into a notice
        // instead of a fatal error.
        if ((string) $exception->getCode() === '23000') {
            return ['ok' => false, 'error' => 'That staff member already has this leave type starting on ' . $start . '.', 'id' => 0];
        }
        throw $exception;
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => $exception->getMessage(), 'id' => 0];
    }
    return ['ok' => true, 'error' => '', 'id' => $eventId];
}

/** @return array{ok:bool,error:string} */
function roster_leave_request_soft_delete(int $requestId, array $actor): array
{
    if (!in_array(($actor['role_slug'] ?? ''), ['super-admin', 'team-leader'], true)) return ['ok'=>false, 'error'=>'Super Admin or Team Leader access required.'];
    if (!app_schema_has('leave_requests', 'deleted_at') || !app_schema_has('calendar_events', 'deleted_at')) return ['ok'=>false, 'error'=>'Apply the additive leave removal migration before removing requests.'];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT * FROM leave_requests WHERE id=? AND deleted_at IS NULL FOR UPDATE');
        $query->execute([$requestId]);
        $request = $query->fetch();
        if (!$request) throw new InvalidArgumentException('Leave request not found.');
        if (app_schema_has('calendar_events', 'leave_request_id', $pdo)) {
            $event = $pdo->prepare("SELECT id FROM calendar_events WHERE leave_request_id=? AND event_type='leave' AND deleted_at IS NULL FOR UPDATE");
            $event->execute([$requestId]);
        } else {
            $event = $pdo->prepare("SELECT id FROM calendar_events WHERE event_type='leave' AND notes=? AND staff_id <=> ? AND deleted_at IS NULL FOR UPDATE");
            $event->execute(['Approved leave request #' . $requestId, $request['staff_id']]);
        }
        $eventIds = array_map('intval', $event->fetchAll(PDO::FETCH_COLUMN));
        $pdo->prepare('UPDATE leave_requests SET deleted_at=NOW() WHERE id=?')->execute([$requestId]);
        if ($eventIds) {
            $pdo->prepare('UPDATE calendar_events SET deleted_at=NOW() WHERE id IN (' . implode(',', array_fill(0, count($eventIds), '?')) . ')')->execute($eventIds);
        }
        audit_admin_action((int)$actor['id'], 'leave_request_soft_deleted', 'leave_request', $requestId, ['status'=>$request['status'], 'calendar_event_ids'=>$eventIds, 'rows_retained'=>true]);
        $pdo->commit();
        return ['ok'=>true, 'error'=>''];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false, 'error'=>$exception instanceof InvalidArgumentException ? $exception->getMessage() : 'The leave request could not be removed.'];
    }
}

/** @return array{ok:bool,error:string} */
function roster_leave_delete(int $eventId, array $user): array
{
    $leave = roster_leave_load($eventId);
    if (!$leave) return ['ok' => false, 'error' => 'Leave record not found.'];
    $requestId = (int) ($leave['leave_request_id'] ?? 0);
    $legacyRequest = preg_match('/\AApproved leave request #([0-9]+)\z/', (string) ($leave['notes'] ?? ''), $matches) === 1;
    if ($requestId === 0 && $legacyRequest) $requestId = (int) $matches[1];
    if ($requestId > 0) {
        if (in_array(($user['role_slug'] ?? ''), ['super-admin', 'team-leader'], true)) {
            $result = roster_leave_request_soft_delete($requestId, $user);
            if ($result['ok']) $result['message'] = 'Leave request hidden. Its request and calendar records were retained.';
            return $result;
        }
        return ['ok'=>false, 'error'=>'Only Super Admins and Team Leaders can remove an approved leave request.'];
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if (!empty($leave['leave_request_id'])) {
            $pdo->prepare("UPDATE leave_requests SET status='cancelled' WHERE id=?")->execute([(int) $leave['leave_request_id']]);
            audit_admin_action((int) $user['id'], 'approved_leave_cancelled', 'leave_request', (int) $leave['leave_request_id'], ['calendar_event_id' => $eventId]);
        }
        $pdo->prepare("DELETE FROM calendar_events WHERE id = ? AND event_type = 'leave'")->execute([$eventId]);
        audit_admin_action((int) $user['id'], 'staff_leave_deleted', 'calendar_event', $eventId, ['staff_id' => (int) $leave['staff_id'], 'start_date' => $leave['event_date']]);
        $pdo->commit();
        return ['ok' => true, 'error' => ''];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => 'The leave could not be removed.'];
    }
}

/** Staff eligible to be picked as a replacement for the given staff member. */
function roster_replacement_candidates(int $staffId): array
{
    $statement = db()->prepare("SELECT id, full_name, team_label FROM staff_directory
        WHERE id <> ? AND employment_status IN ('active','for_replacement') ORDER BY team_label, full_name");
    $statement->execute([$staffId]);
    return $statement->fetchAll();
}
