<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';
require __DIR__ . '/app/roster.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('view_all_tickets');
$canManageCalendar = roster_can_manage_calendar($user);
$pdo = db();
$notice = '';
$error = '';
$month = trim((string) ($_GET['month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
$monthStart = $month . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$eventTypes = ['team_event' => 'Team event', 'coverage' => 'Coverage', 'reminder' => 'Reminder', 'other' => 'Other'];
$holidayCountries = ['COMPANY' => 'Company', 'PH' => 'Philippines', 'AU' => 'Australia', 'CA' => 'Canada'];
$attendanceStatuses = ROSTER_LEAVE_TYPES + ['present' => 'Present', 'partial' => 'Partial coverage', 'absent' => 'Absent', 'training' => 'Training', 'work_from_home' => 'Work from home'];
$staffOptions = roster_staff_rows();
$staffByTeam = [];
foreach ($staffOptions as $staffOption) {
    $staffByTeam[(string) $staffOption['team_label']][] = $staffOption;
}

/** Read a "YYYY-MM-DD" field, returning null when it is absent or not a real date. */
$postedDate = static function (array $source, string $key): ?string {
    $value = trim((string) ($source[$key] ?? ''));
    if ($value === '') return null;
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $parsed && $parsed->format('Y-m-d') === $value ? $value : null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManageCalendar) { http_response_code(403); exit('You do not have permission to update the team calendar.'); }
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $redirectMonth = substr((string) ($_POST['month'] ?? $month), 0, 7);

    if (in_array($action, ['add_event', 'save_event'], true)) {
        $eventId = (int) request_string($_POST, 'event_id');
        $title = trim((string) request_string($_POST, 'title'));
        $eventDate = $postedDate($_POST, 'event_date');
        $endDate = $postedDate($_POST, 'end_date');
        $eventType = trim((string) request_string($_POST, 'event_type')) ?: 'team_event';
        $staffId = (int) request_string($_POST, 'staff_id');
        $leaveType = trim((string) request_string($_POST, 'leave_type')) ?: 'annual';
        $notes = trim((string) request_string($_POST, 'notes'));
        $existingEvent = $eventId > 0 ? $pdo->prepare('SELECT * FROM calendar_events WHERE id = ?') : null;
        if ($existingEvent) { $existingEvent->execute([$eventId]); $existingEvent = $existingEvent->fetch() ?: null; }

        if ($eventId > 0 && !$existingEvent) {
            $error = 'That calendar entry no longer exists.';
        } elseif ($staffId > 0) {
            // A staff-linked entry is a leave record: one shared row for the calendar and attendance.
            $result = roster_leave_save([
                'staff_id' => $staffId, 'start_date' => (string) $eventDate, 'end_date' => (string) ($endDate ?? ''),
                'leave_type' => $leaveType, 'notes' => $notes,
            ], $user, $eventId);
            if ($result['ok']) {
                redirect('team-calendar.php?month=' . substr((string) $eventDate, 0, 7) . '&notice=' . ($eventId > 0 ? 'entry_updated' : 'entry_saved'));
            }
            $error = $result['error'];
        } elseif ($title === '' || $eventDate === null || ($endDate !== null && $endDate < $eventDate) || !isset($eventTypes[$eventType])) {
            $error = 'Enter a valid event title, type, and date range.';
        } else {
            if ($eventId > 0) {
                $pdo->prepare("UPDATE calendar_events SET title=?, event_date=?, end_date=?, event_type=?, staff_id=NULL, leave_type=NULL, notes=? WHERE id=? AND event_type <> 'leave'")
                    ->execute([$title, $eventDate, $endDate, $eventType, $notes !== '' ? $notes : null, $eventId]);
                audit_admin_action((int) $user['id'], 'calendar_event_updated', 'calendar_event', $eventId, ['title' => $title, 'event_date' => $eventDate]);
            } else {
                $pdo->prepare('INSERT INTO calendar_events(title,event_date,end_date,event_type,notes,created_by) VALUES(?,?,?,?,?,?)')
                    ->execute([$title, $eventDate, $endDate, $eventType, $notes !== '' ? $notes : null, $user['id']]);
                $eventId = (int) $pdo->lastInsertId();
                audit_admin_action((int) $user['id'], 'calendar_event_created', 'calendar_event', $eventId, ['title' => $title, 'event_date' => $eventDate]);
            }
            redirect('team-calendar.php?month=' . substr((string) $eventDate, 0, 7) . '&notice=' . ($action === 'save_event' && (int) request_string($_POST, 'event_id') > 0 ? 'entry_updated' : 'entry_saved'));
        }
    } elseif ($action === 'delete_event') {
        $eventId = (int) request_string($_POST, 'event_id');
        $check = $pdo->prepare('SELECT id FROM calendar_events WHERE id = ? AND event_type <> \'leave\'');
        $check->execute([$eventId]);
        if (!$check->fetchColumn()) {
            $error = 'That calendar entry no longer exists.';
        } else {
            $pdo->prepare("DELETE FROM calendar_events WHERE id = ? AND event_type <> 'leave'")->execute([$eventId]);
            audit_admin_action((int) $user['id'], 'calendar_event_deleted', 'calendar_event', $eventId);
            redirect('team-calendar.php?month=' . $redirectMonth . '&notice=entry_deleted');
        }
    } elseif (in_array($action, ['add_holiday', 'save_holiday'], true)) {
        $holidayId = (int) request_string($_POST, 'holiday_id');
        $date = $postedDate($_POST, 'date');
        $label = trim((string) request_string($_POST, 'label'));
        $country = trim((string) request_string($_POST, 'country_code')) ?: 'COMPANY';
        if ($date === null || $label === '' || !isset($holidayCountries[$country])) {
            $error = 'Enter a valid holiday date, label, and country.';
        } else {
            $type = $country === 'COMPANY' ? 'company' : 'public';
            try {
                if ($holidayId > 0) {
                    $pdo->prepare('UPDATE company_holidays SET `date`=?,label=?,country_code=?,holiday_type=? WHERE id=?')->execute([$date, $label, $country, $type, $holidayId]);
                    audit_admin_action((int) $user['id'], 'company_holiday_updated', 'company_holiday', $holidayId, ['date' => $date, 'label' => $label]);
                } else {
                    $pdo->prepare('INSERT INTO company_holidays(`date`,label,country_code,holiday_type) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE holiday_type=VALUES(holiday_type)')->execute([$date, $label, $country, $type]);
                    audit_admin_action((int) $user['id'], 'company_holiday_saved', 'company_holiday', null, ['date' => $date, 'label' => $label, 'country_code' => $country]);
                }
            } catch (PDOException $exception) {
                if ((string) $exception->getCode() === '23000') {
                    $error = 'A holiday with that date, country, and label already exists.';
                } else {
                    throw $exception;
                }
            }
            if ($error === '') {
                redirect('team-calendar.php?month=' . substr($date, 0, 7) . '&notice=' . ($holidayId > 0 ? 'entry_updated' : 'holiday_saved'));
            }
        }
    } elseif ($action === 'delete_holiday') {
        $holidayId = (int) request_string($_POST, 'holiday_id');
        $check = $pdo->prepare('SELECT id FROM company_holidays WHERE id = ?');
        $check->execute([$holidayId]);
        if (!$check->fetchColumn()) {
            $error = 'That holiday no longer exists.';
        } else {
            $pdo->prepare('DELETE FROM company_holidays WHERE id = ?')->execute([$holidayId]);
            audit_admin_action((int) $user['id'], 'company_holiday_deleted', 'company_holiday', $holidayId);
            redirect('team-calendar.php?month=' . $redirectMonth . '&notice=entry_deleted');
        }
    } elseif ($action === 'save_leave') {
        $leaveId = (int) request_string($_POST, 'leave_id');
        $result = roster_leave_save($_POST, $user, $leaveId);
        if ($result['ok']) {
            redirect('team-calendar.php?month=' . substr((string) request_string($_POST, 'start_date'), 0, 7) . '&notice=' . ($leaveId > 0 ? 'entry_updated' : 'entry_saved'));
        }
        $error = $result['error'];
    } elseif ($action === 'delete_leave') {
        $result = roster_leave_delete((int) request_string($_POST, 'leave_id'), $user);
        if ($result['ok']) {
            redirect('team-calendar.php?month=' . $redirectMonth . '&notice=entry_deleted');
        }
        $error = $result['error'];
    }
}

$notices = [
    'holiday_saved' => 'Holiday saved.',
    'event_saved' => 'Event saved.',
    'entry_saved' => 'Calendar entry saved.',
    'entry_updated' => 'Calendar entry updated.',
    'entry_deleted' => 'Calendar entry deleted.',
];
$notice = $notices[request_string($_GET, 'notice')] ?? '';
$highlightStaffId = (int) request_string($_GET, 'staff');

$itemsByDate = [];
$details = [];
$dayEntries = [];
$entryData = [];
$addItem = static function (array &$bucket, string $date, array $item): void {
    $bucket[$date] ??= [];
    $bucket[$date][] = $item;
};
$addDetail = static function (array &$details, array $detail): string {
    $id = 'cal-detail-' . (count($details) + 1);
    $details[$id] = $detail;
    return $id;
};
$addEntry = static function (array &$dayEntries, array &$entryData, string $date, array $entry): void {
    $dayEntries[$date] ??= [];
    $dayEntries[$date][] = $entry;
    if (!empty($entry['editable'])) $entryData[$entry['kind'] . ':' . $entry['id']] = $entry['fields'] ?? [];
};

/* Holidays ----------------------------------------------------------------- */
$holidays = $pdo->prepare('SELECT * FROM company_holidays WHERE `date` BETWEEN ? AND ? ORDER BY `date`, country_code, label');
$holidays->execute([$monthStart, $monthEnd]);
$holidayRows = $holidays->fetchAll();
foreach ($holidayRows as $holiday) {
    $countryLabel = $holidayCountries[$holiday['country_code']] ?? $holiday['country_code'];
    $detailId = $addDetail($details, [
        'title' => $holiday['label'],
        'type' => $countryLabel . ' holiday',
        'date' => $holiday['date'],
        'body' => 'Holiday type: ' . $holiday['holiday_type'],
        'attachments' => [],
    ]);
    $addItem($itemsByDate, $holiday['date'], ['kind' => 'holiday', 'label' => $holiday['country_code'] . ' - ' . $holiday['label'], 'detail_id' => $detailId]);
    $addEntry($dayEntries, $entryData, $holiday['date'], [
        'kind' => 'holiday',
        'id' => (int) $holiday['id'],
        'label' => $holiday['label'],
        'meta' => $countryLabel . ' holiday',
        'editable' => true,
        'fields' => ['holiday_id' => (int) $holiday['id'], 'label' => $holiday['label'], 'country_code' => $holiday['country_code'], 'date' => $holiday['date']],
    ]);
}

/* Staff leave (the same rows the Team Attendance page edits) ----------------- */
$rosterLeave = roster_leave_rows(['from' => $monthStart, 'to' => $monthEnd]);
foreach ($rosterLeave as $leaveRow) {
    $leaveTypeLabel = roster_leave_type_label((string) $leaveRow['leave_type']);
    $leaveSpan = $leaveRow['event_date'] . ((string) ($leaveRow['end_date'] ?? '') !== '' && $leaveRow['end_date'] !== $leaveRow['event_date'] ? ' to ' . $leaveRow['end_date'] : '');
    $detailId = $addDetail($details, [
        'title' => $leaveRow['full_name'] . ' - ' . $leaveTypeLabel,
        'type' => 'Staff leave',
        'date' => $leaveSpan,
        'body' => trim(($leaveRow['department'] ? $leaveRow['department'] . ' · ' : '') . $leaveRow['team_label'] . ($leaveRow['notes'] ? ' · ' . $leaveRow['notes'] : '')),
        'attachments' => [],
    ]);
    foreach (roster_leave_days($leaveRow, $monthStart, $monthEnd) as $date) {
        $addItem($itemsByDate, $date, ['kind' => 'leave', 'label' => $leaveRow['full_name'] . ' · ' . $leaveTypeLabel, 'detail_id' => $detailId]);
        $addEntry($dayEntries, $entryData, $date, [
            'kind' => 'leave',
            'id' => (int) $leaveRow['id'],
            'label' => $leaveRow['full_name'] . ' - ' . $leaveTypeLabel,
            'meta' => $leaveSpan . ($date !== $leaveRow['event_date'] ? ' (continues)' : ''),
            'editable' => true,
            'fields' => [
                'leave_id' => (int) $leaveRow['id'],
                'staff_id' => (int) $leaveRow['staff_id'],
                'leave_type' => (string) $leaveRow['leave_type'],
                'start_date' => (string) $leaveRow['event_date'],
                'end_date' => (string) ($leaveRow['end_date'] ?? ''),
                'notes' => (string) ($leaveRow['notes'] ?? ''),
            ],
        ]);
    }
}

/* Approved leave requests (workflow rows raised on the Leave Requests page) -- */
$leave = $pdo->prepare('SELECT lr.*, u.full_name AS employee FROM leave_requests lr JOIN users u ON u.id = lr.employee_user_id WHERE lr.status = "department_head_approved" AND lr.start_date <= ? AND lr.end_date >= ? ORDER BY lr.start_date, u.full_name');
$leave->execute([$monthEnd, $monthStart]);
$approvedLeave = $leave->fetchAll();
$leaveAttachments = [];
if ($approvedLeave) {
    $ids = array_map('intval', array_column($approvedLeave, 'id'));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $attachmentQuery = $pdo->prepare("SELECT * FROM leave_request_attachments WHERE leave_request_id IN ($placeholders) ORDER BY uploaded_at");
    $attachmentQuery->execute($ids);
    foreach ($attachmentQuery->fetchAll() as $attachment) {
        $leaveAttachments[(int) $attachment['leave_request_id']][] = $attachment;
    }
}
foreach ($approvedLeave as $request) {
    $attachments = [];
    foreach ($leaveAttachments[(int) $request['id']] ?? [] as $attachment) {
        $attachments[] = ['label' => $attachment['file_name'], 'href' => 'download-leave-attachment.php?id=' . (int) $attachment['id']];
    }
    $detailId = $addDetail($details, [
        'title' => $request['employee'] . ' leave',
        'type' => 'Approved leave request',
        'date' => $request['start_date'] . ' to ' . $request['end_date'],
        'body' => $request['reason'],
        'attachments' => $attachments,
    ]);
    $start = max(strtotime((string) $request['start_date']), strtotime($monthStart));
    $end = min(strtotime((string) $request['end_date']), strtotime($monthEnd));
    for ($day = $start; $day <= $end; $day = strtotime('+1 day', $day)) {
        $date = date('Y-m-d', $day);
        $addItem($itemsByDate, $date, ['kind' => 'leave', 'label' => $request['employee'] . ' leave', 'detail_id' => $detailId]);
        $addEntry($dayEntries, $entryData, $date, [
            'kind' => 'request',
            'id' => (int) $request['id'],
            'label' => $request['employee'] . ' leave request',
            'meta' => $request['start_date'] . ' to ' . $request['end_date'] . ' · manage on Leave Requests',
            'editable' => false,
        ]);
    }
}

/* Team events -------------------------------------------------------------- */
$events = $pdo->prepare("SELECT ce.*, u.full_name AS creator FROM calendar_events ce JOIN users u ON u.id = ce.created_by WHERE ce.event_type <> 'leave' AND ce.event_date <= ? AND COALESCE(ce.end_date, ce.event_date) >= ? ORDER BY ce.event_date, ce.title");
$events->execute([$monthEnd, $monthStart]);
$eventRows = $events->fetchAll();
foreach ($eventRows as $event) {
    $detailId = $addDetail($details, [
        'title' => $event['title'],
        'type' => $eventTypes[$event['event_type']] ?? $event['event_type'],
        'date' => $event['event_date'] . ($event['end_date'] ? ' to ' . $event['end_date'] : ''),
        'body' => 'Created by ' . $event['creator'] . ($event['notes'] ? ' · ' . $event['notes'] : ''),
        'attachments' => [],
    ]);
    $start = max(strtotime((string) $event['event_date']), strtotime($monthStart));
    $end = min(strtotime((string) ($event['end_date'] ?: $event['event_date'])), strtotime($monthEnd));
    for ($day = $start; $day <= $end; $day = strtotime('+1 day', $day)) {
        $date = date('Y-m-d', $day);
        $addItem($itemsByDate, $date, ['kind' => 'event', 'label' => ($eventTypes[$event['event_type']] ?? $event['event_type']) . ': ' . $event['title'], 'detail_id' => $detailId]);
        $addEntry($dayEntries, $entryData, $date, [
            'kind' => 'event',
            'id' => (int) $event['id'],
            'label' => $event['title'],
            'meta' => ($eventTypes[$event['event_type']] ?? $event['event_type']) . ' · ' . $event['event_date'] . ($event['end_date'] ? ' to ' . $event['end_date'] : ''),
            'editable' => true,
            'fields' => [
                'event_id' => (int) $event['id'],
                'title' => (string) $event['title'],
                'event_type' => (string) $event['event_type'],
                'event_date' => (string) $event['event_date'],
                'end_date' => (string) ($event['end_date'] ?? ''),
                'notes' => (string) ($event['notes'] ?? ''),
                'staff_id' => 0,
            ],
        ]);
    }
}

/* Department attendance ---------------------------------------------------- */
$attendance = $pdo->prepare('SELECT ta.*, d.name AS department, u.full_name AS logger, (SELECT GROUP_CONCAT(sd.full_name ORDER BY sd.full_name SEPARATOR ", ") FROM team_attendance_leave tal JOIN staff_directory sd ON sd.id = tal.staff_id WHERE tal.attendance_id = ta.id) AS leave_staff FROM team_attendance ta JOIN departments d ON d.id = ta.department_id JOIN users u ON u.id = ta.logged_by WHERE ta.attendance_date BETWEEN ? AND ? ORDER BY ta.attendance_date, d.name');
$attendance->execute([$monthStart, $monthEnd]);
$attendanceRows = $attendance->fetchAll();
foreach ($attendanceRows as $row) {
    $detailId = $addDetail($details, [
        'title' => $row['department'] . ' attendance',
        'type' => 'Team Attendance',
        'date' => $row['attendance_date'],
        'body' => ($attendanceStatuses[$row['status']] ?? $row['status']) . ' - Headcount: ' . (int) $row['headcount'] . ($row['leave_staff'] ? ' - Staff on leave: ' . $row['leave_staff'] : '') . ($row['notes'] ? ' - ' . $row['notes'] : '') . ' - Logged by ' . $row['logger'],
        'attachments' => [],
    ]);
    $addItem($itemsByDate, $row['attendance_date'], ['kind' => 'attendance', 'label' => 'Attendance: ' . $row['department'], 'detail_id' => $detailId]);
    $addEntry($dayEntries, $entryData, $row['attendance_date'], [
        'kind' => 'attendance',
        'id' => (int) $row['id'],
        'label' => 'Attendance: ' . $row['department'],
        'meta' => 'Headcount ' . (int) $row['headcount'] . ' · edit on Team Attendance',
        'editable' => false,
    ]);
}

foreach ($dayEntries as $date => $entries) {
    usort($entries, static fn(array $left, array $right): int => strcmp((string) $left['label'], (string) $right['label']));
    $dayEntries[$date] = $entries;
}

$calendarStart = strtotime($monthStart . ' -' . date('w', strtotime($monthStart)) . ' days');
$calendarEnd = strtotime($monthEnd . ' +' . (6 - (int) date('w', strtotime($monthEnd))) . ' days');
$prevMonth = date('Y-m', strtotime($monthStart . ' -1 month'));
$nextMonth = date('Y-m', strtotime($monthStart . ' +1 month'));

page_start('Team Calendar', $user);
?>
<div class="team-calendar-screen">
    <?php if ($notice): ?><p class="action-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="auth-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <section class="team-calendar-panel" aria-label="<?= e(date('F Y', strtotime($monthStart))) ?> calendar">
        <div class="team-calendar-toolbar">
            <nav class="calendar-month-nav" aria-label="Calendar month navigation">
                <a class="calendar-nav-button" href="team-calendar.php?month=<?= e($prevMonth) ?>" aria-label="Previous month"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></a>
                <div><h2><?= e(date('F Y', strtotime($monthStart))) ?></h2><p><?= $canManageCalendar ? 'Click any date to add or edit a holiday, event, or leave' : 'Team schedule and availability' ?></p></div>
                <a class="calendar-nav-button" href="team-calendar.php?month=<?= e($nextMonth) ?>" aria-label="Next month"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></a>
                <a class="calendar-today-link" href="team-calendar.php?month=<?= e(date('Y-m')) ?>">Today</a>
            </nav>
            <div class="calendar-summary-inline" aria-label="Month summary"><span><i class="calendar-legend-dot is-holiday"></i>Holidays <b><?= count($holidayRows) ?></b></span><span><i class="calendar-legend-dot is-leave"></i>Leave <b><?= count($rosterLeave) + count($approvedLeave) ?></b></span><span><i class="calendar-legend-dot is-event"></i>Events <b><?= count($eventRows) ?></b></span><span><i class="calendar-legend-dot is-attendance"></i>Attendance <b><?= count($attendanceRows) ?></b></span></div>
        </div>
        <div class="calendar-weekdays"><?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $weekday): ?><span><?= e($weekday) ?></span><?php endforeach; ?></div>
        <div class="calendar-month-grid"><?php for ($day = $calendarStart; $day <= $calendarEnd; $day = strtotime('+1 day', $day)): $date = date('Y-m-d', $day); $outside = substr($date, 0, 7) !== $month; $items = $itemsByDate[$date] ?? []; ?><div class="calendar-day<?= $outside ? ' is-outside' : '' ?><?= $date === date('Y-m-d') ? ' is-today' : '' ?>" data-date="<?= e($date) ?>"><?php if ($canManageCalendar): ?><button type="button" class="calendar-date-button" data-date="<?= e($date) ?>" aria-label="Add or edit items on <?= e(date('F j, Y', $day)) ?>"><span class="calendar-date"><?= e(date('j', $day)) ?></span></button><?php else: ?><a class="calendar-date-button" href="leave-requests.php?start_date=<?= e($date) ?>&amp;end_date=<?= e($date) ?>" aria-label="Request leave on <?= e(date('F j, Y', $day)) ?>"><span class="calendar-date"><?= e(date('j', $day)) ?></span></a><?php endif; ?><?php foreach (array_slice($items, 0, 3) as $item): ?><button type="button" class="calendar-chip calendar-chip-<?= e($item['kind']) ?>" data-detail-id="<?= e($item['detail_id']) ?>"><?= e($item['label']) ?></button><?php endforeach; ?><?php if (count($items) > 3): ?><span class="calendar-more">+<?= count($items) - 3 ?> more</span><?php endif; ?></div><?php endfor; ?></div>
    </section>
    <div class="calendar-popover" id="calendar-popover" role="dialog" aria-labelledby="calendar-detail-title" aria-live="polite" hidden><div class="calendar-popover-head"><div><p class="eyebrow" id="calendar-detail-type">Details</p><h2 id="calendar-detail-title">Calendar item</h2><p class="muted" id="calendar-detail-date"></p></div><button type="button" class="calendar-popover-close" id="calendar-detail-close" aria-label="Close details"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div><p id="calendar-detail-body"></p><div id="calendar-detail-files" class="attachment-list"></div></div>

    <?php if ($canManageCalendar): ?>
    <div class="calendar-day-card" id="calendar-day-card" role="dialog" aria-labelledby="calendar-day-card-title" hidden>
        <div class="calendar-popover-head"><div><p class="eyebrow">Calendar date</p><h2 id="calendar-day-card-title">Date</h2><p class="muted" id="calendar-day-card-sub"></p></div><button type="button" class="calendar-popover-close" id="calendar-day-card-close" aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>
        <div class="calendar-day-card-items" id="calendar-day-card-items"></div>
        <div class="calendar-day-card-add">
            <label class="calendar-day-card-field">What do you want to add?
                <select id="calendar-day-card-kind">
                    <option value="event">Company event</option>
                    <option value="leave">Leave</option>
                    <option value="holiday">Holiday</option>
                </select>
            </label>
            <button type="button" class="button" id="calendar-day-card-add-button">Add</button>
        </div>
    </div>

    <div class="calendar-modal" id="calendar-modal-event" role="dialog" aria-modal="true" aria-labelledby="calendar-modal-event-title" hidden>
        <div class="calendar-modal-panel">
            <div class="calendar-popover-head"><div><p class="eyebrow" id="calendar-modal-event-mode">New entry</p><h2 id="calendar-modal-event-title">Company event</h2></div><button type="button" class="calendar-popover-close" data-calendar-modal-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>
            <form method="post" class="calendar-modal-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_event">
                <input type="hidden" name="month" value="<?= e($month) ?>">
                <input type="hidden" name="event_id" value="0" data-field="event_id">
                <label class="calendar-modal-field is-wide">Title<input name="title" required maxlength="180" data-field="title" placeholder="Event title"></label>
                <label class="calendar-modal-field">Type<select name="event_type" data-field="event_type"><?php foreach ($eventTypes as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
                <label class="calendar-modal-field">Start date<input type="date" name="event_date" required data-field="event_date"></label>
                <label class="calendar-modal-field">End date<input type="date" name="end_date" data-field="end_date"></label>
                <label class="calendar-modal-field is-wide">Note<input name="notes" maxlength="255" data-field="notes" placeholder="Optional note"></label>
                <details class="calendar-modal-details">
                    <summary>This entry is a staff leave</summary>
                    <div class="calendar-modal-grid">
                        <label class="calendar-modal-field">Staff member<select name="staff_id" data-field="staff_id"><option value="0">Not a staff leave</option><?php foreach ($staffByTeam as $teamLabel => $teamStaff): ?><optgroup label="<?= e((string) $teamLabel) ?>"><?php foreach ($teamStaff as $teamMember): ?><option value="<?= (int) $teamMember['id'] ?>"><?= e($teamMember['full_name']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label>
                        <label class="calendar-modal-field">Leave type<select name="leave_type" data-field="leave_type"><?php foreach (ROSTER_LEAVE_TYPES as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
                    </div>
                    <p class="muted calendar-modal-hint">Choosing a staff member turns this entry into a leave record shared with Team Attendance.</p>
                </details>
                <div class="calendar-modal-actions"><button class="button" type="submit">Save entry</button><button type="button" class="button button-secondary" data-calendar-modal-close>Cancel</button></div>
            </form>
            <form method="post" class="calendar-modal-delete" data-calendar-delete hidden>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_event">
                <input type="hidden" name="month" value="<?= e($month) ?>">
                <input type="hidden" name="event_id" value="0" data-delete-field="event_id">
                <button class="button button-danger" type="submit">Delete this event</button>
            </form>
        </div>
    </div>

    <div class="calendar-modal" id="calendar-modal-holiday" role="dialog" aria-modal="true" aria-labelledby="calendar-modal-holiday-title" hidden>
        <div class="calendar-modal-panel">
            <div class="calendar-popover-head"><div><p class="eyebrow" id="calendar-modal-holiday-mode">New entry</p><h2 id="calendar-modal-holiday-title">Holiday</h2></div><button type="button" class="calendar-popover-close" data-calendar-modal-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>
            <form method="post" class="calendar-modal-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_holiday">
                <input type="hidden" name="month" value="<?= e($month) ?>">
                <input type="hidden" name="holiday_id" value="0" data-field="holiday_id">
                <label class="calendar-modal-field is-wide">Holiday label<input name="label" required maxlength="150" data-field="label" placeholder="Holiday name"></label>
                <label class="calendar-modal-field">Country<select name="country_code" data-field="country_code"><?php foreach ($holidayCountries as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
                <label class="calendar-modal-field">Date<input type="date" name="date" required data-field="date"></label>
                <div class="calendar-modal-actions"><button class="button" type="submit">Save holiday</button><button type="button" class="button button-secondary" data-calendar-modal-close>Cancel</button></div>
            </form>
            <form method="post" class="calendar-modal-delete" data-calendar-delete hidden>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_holiday">
                <input type="hidden" name="month" value="<?= e($month) ?>">
                <input type="hidden" name="holiday_id" value="0" data-delete-field="holiday_id">
                <button class="button button-danger" type="submit">Delete this holiday</button>
            </form>
        </div>
    </div>

    <div class="calendar-modal" id="calendar-modal-leave" role="dialog" aria-modal="true" aria-labelledby="calendar-modal-leave-title" hidden>
        <div class="calendar-modal-panel">
            <div class="calendar-popover-head"><div><p class="eyebrow" id="calendar-modal-leave-mode">New entry</p><h2 id="calendar-modal-leave-title">Staff leave</h2></div><button type="button" class="calendar-popover-close" data-calendar-modal-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>
            <form method="post" class="calendar-modal-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_leave">
                <input type="hidden" name="month" value="<?= e($month) ?>">
                <input type="hidden" name="leave_id" value="0" data-field="leave_id">
                <label class="calendar-modal-field is-wide">Staff member<select name="staff_id" required data-field="staff_id"><option value="">Choose a staff member&hellip;</option><?php foreach ($staffByTeam as $teamLabel => $teamStaff): ?><optgroup label="<?= e((string) $teamLabel) ?>"><?php foreach ($teamStaff as $teamMember): ?><option value="<?= (int) $teamMember['id'] ?>"><?= e($teamMember['full_name']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label>
                <label class="calendar-modal-field">Leave type<select name="leave_type" data-field="leave_type"><?php foreach (ROSTER_LEAVE_TYPES as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
                <label class="calendar-modal-field">Start date<input type="date" name="start_date" required data-field="start_date"></label>
                <label class="calendar-modal-field">End date<input type="date" name="end_date" data-field="end_date"></label>
                <label class="calendar-modal-field is-wide">Note<input name="notes" maxlength="255" data-field="notes" placeholder="Optional note"></label>
                <p class="muted calendar-modal-hint">Saved once for both the Team Calendar and Team Attendance.</p>
                <div class="calendar-modal-actions"><button class="button" type="submit">Save leave</button><button type="button" class="button button-secondary" data-calendar-modal-close>Cancel</button></div>
            </form>
            <form method="post" class="calendar-modal-delete" data-calendar-delete hidden>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_leave">
                <input type="hidden" name="month" value="<?= e($month) ?>">
                <input type="hidden" name="leave_id" value="0" data-delete-field="leave_id">
                <button class="button button-danger" type="submit">Delete this leave</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($canManageCalendar): ?><aside class="calendar-month-summary" aria-labelledby="calendar-summary-title"><div class="calendar-form-head"><div><h2 id="calendar-summary-title">Month summary</h2><p>Includes staff leave, approved leave requests, and attendance.</p></div></div><div class="calendar-summary-list"><div><span><i class="calendar-legend-dot is-holiday"></i>Holidays</span><strong><?= count($holidayRows) ?></strong></div><div><span><i class="calendar-legend-dot is-leave"></i>Staff leave</span><strong><?= count($rosterLeave) ?></strong></div><div><span><i class="calendar-legend-dot is-leave"></i>Approved requests</span><strong><?= count($approvedLeave) ?></strong></div><div><span><i class="calendar-legend-dot is-event"></i>Team events</span><strong><?= count($eventRows) ?></strong></div><div><span><i class="calendar-legend-dot is-attendance"></i>Attendance</span><strong><?= count($attendanceRows) ?></strong></div></div><p class="muted calendar-modal-hint"><a href="team-attendance.php<?= $highlightStaffId > 0 ? '?staff=' . $highlightStaffId : '' ?>">Edit shifts and leave on Team Attendance</a></p></aside><?php endif; ?>
</div>
<script>
var calendarDetails = <?= json_encode($details, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
var calendarDayEntries = <?= json_encode($dayEntries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
var calendarEntryData = <?= json_encode($entryData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
var calendarCanManage = <?= $canManageCalendar ? 'true' : 'false' ?>;
var calendarMonth = <?= json_encode($month) ?>;
var calendarKinds = { event: 'Company event', holiday: 'Holiday', leave: 'Staff leave', request: 'Leave request', attendance: 'Attendance' };

var detailPanel = document.querySelector('#calendar-popover');
var lastCalendarTrigger = null;

document.querySelectorAll('.calendar-chip').forEach(function(chip){
  chip.addEventListener('click', function(event){
    event.stopPropagation();
    var detail = calendarDetails[chip.dataset.detailId];
    if (!detail) return;
    document.querySelector('#calendar-detail-type').textContent = detail.type;
    document.querySelector('#calendar-detail-title').textContent = detail.title;
    document.querySelector('#calendar-detail-date').textContent = detail.date;
    document.querySelector('#calendar-detail-body').textContent = detail.body || '';
    var files = document.querySelector('#calendar-detail-files');
    files.innerHTML = '';
    (detail.attachments || []).forEach(function(file){
      var item = document.createElement('div');
      item.className = 'attachment-item';
      var label = document.createElement('strong');
      label.textContent = file.label;
      var link = document.createElement('a');
      link.className = 'button button-secondary';
      link.href = file.href;
      link.textContent = 'Download';
      item.append(label, link);
      files.appendChild(item);
    });
    if (!(detail.attachments || []).length) {
      var empty = document.createElement('p');
      empty.className = 'muted';
      empty.textContent = 'No attachment for this item.';
      files.appendChild(empty);
    }
    lastCalendarTrigger = chip;
    detailPanel.hidden = false;
    var rect = chip.getBoundingClientRect();
    var top = Math.min(rect.bottom + 8, window.innerHeight - detailPanel.offsetHeight - 12);
    var left = Math.min(rect.left, window.innerWidth - detailPanel.offsetWidth - 12);
    detailPanel.style.top = Math.max(12, top) + 'px';
    detailPanel.style.left = Math.max(12, left) + 'px';
    document.querySelector('#calendar-detail-close').focus();
  });
});
function closeCalendarDetail(returnFocus) {
  detailPanel.hidden = true;
  if (returnFocus && lastCalendarTrigger) lastCalendarTrigger.focus();
}
document.querySelector('#calendar-detail-close').addEventListener('click', function(){ closeCalendarDetail(true); });
document.addEventListener('click', function(event){
  if (detailPanel.hidden) return;
  if (event.target.closest('#calendar-popover') || event.target.closest('.calendar-chip')) return;
  closeCalendarDetail(false);
});
document.addEventListener('keydown', function(event){
  if (event.key === 'Escape' && !detailPanel.hidden) closeCalendarDetail(true);
});

/* Date click card: pick a holiday, a company event, or a staff leave -------- */
(function () {
  var dayCard = document.querySelector('#calendar-day-card');
  if (!dayCard || !calendarCanManage) return;
  var dayCardTitle = document.querySelector('#calendar-day-card-title');
  var dayCardSub = document.querySelector('#calendar-day-card-sub');
  var dayCardItems = document.querySelector('#calendar-day-card-items');
  var kindSelect = document.querySelector('#calendar-day-card-kind');
  var modals = Array.prototype.slice.call(document.querySelectorAll('.calendar-modal'));
  var idFields = { event: 'event_id', holiday: 'holiday_id', leave: 'leave_id' };
  var dateFields = { event: 'event_date', holiday: 'date', leave: 'start_date' };
  var activeDate = calendarMonth + '-01';
  var activeTrigger = null;

  var prettyDate = function (value) {
    var parts = String(value).split('-');
    if (parts.length !== 3) return value;
    return new Intl.DateTimeFormat('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
      .format(new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2])));
  };

  var positionCard = function (anchor) {
    var rect = anchor.getBoundingClientRect();
    var top = rect.bottom + 8;
    if (top + dayCard.offsetHeight > window.innerHeight - 12) top = Math.max(12, rect.top - dayCard.offsetHeight - 8);
    var left = Math.min(Math.max(12, rect.left), Math.max(12, window.innerWidth - dayCard.offsetWidth - 12));
    dayCard.style.top = top + 'px';
    dayCard.style.left = left + 'px';
  };

  var renderDayCard = function (date) {
    activeDate = date;
    dayCardTitle.textContent = prettyDate(date);
    var entries = calendarDayEntries[date] || [];
    dayCardSub.textContent = entries.length
      ? entries.length + (entries.length === 1 ? ' entry on this date' : ' entries on this date')
      : 'Nothing scheduled yet';
    dayCardItems.innerHTML = '';
    entries.forEach(function (entry) {
      var row = document.createElement('div');
      row.className = 'calendar-day-card-item';
      var copy = document.createElement('div');
      copy.className = 'calendar-day-card-copy';
      var strong = document.createElement('strong');
      strong.textContent = entry.label;
      var meta = document.createElement('small');
      meta.textContent = (calendarKinds[entry.kind] ? calendarKinds[entry.kind] + ' \u00b7 ' : '') + entry.meta;
      copy.append(strong, meta);
      row.appendChild(copy);
      if (entry.editable) {
        var edit = document.createElement('button');
        edit.type = 'button';
        edit.className = 'table-link';
        edit.textContent = 'Edit';
        edit.setAttribute('data-calendar-edit', entry.kind + ':' + entry.id);
        row.appendChild(edit);
      }
      dayCardItems.appendChild(row);
    });
    if (!entries.length) {
      var emptyNote = document.createElement('p');
      emptyNote.className = 'muted calendar-modal-hint';
      emptyNote.textContent = 'Choose what to add below.';
      dayCardItems.appendChild(emptyNote);
    }
  };

  var closeDayCard = function (returnFocus) {
    if (dayCard.hidden) return;
    dayCard.hidden = true;
    if (returnFocus && activeTrigger) activeTrigger.focus();
  };

  var openDayCard = function (date, anchor) {
    activeTrigger = anchor;
    dayCard.hidden = false;
    renderDayCard(date);
    positionCard(anchor);
    document.querySelector('#calendar-day-card-close').focus();
  };

  var openModal = function (kind, fields) {
    var modal = document.querySelector('#calendar-modal-' + kind);
    if (!modal) return;
    var form = modal.querySelector('form.calendar-modal-form');
    if (!form) return;
    var idField = idFields[kind];
    var editId = fields && fields[idField] ? String(fields[idField]) : '0';
    form.reset();
    form.querySelectorAll('[data-field]').forEach(function (field) {
      var key = field.dataset.field;
      var value = '';
      if (key === idField) value = editId;
      else if (fields && Object.prototype.hasOwnProperty.call(fields, key) && fields[key] !== null && fields[key] !== undefined) value = String(fields[key]);
      field.value = value;
    });
    if (editId === '0') {
      var dateField = form.querySelector('[data-field="' + dateFields[kind] + '"]');
      if (dateField) dateField.value = activeDate;
      var leaveTypeField = form.querySelector('[data-field="leave_type"]');
      if (leaveTypeField && !leaveTypeField.value) leaveTypeField.value = 'annual';
    }
    var deleteForm = modal.querySelector('[data-calendar-delete]');
    if (deleteForm) {
      deleteForm.hidden = editId === '0';
      deleteForm.querySelectorAll('[data-delete-field]').forEach(function (field) {
        if (field.dataset.deleteField === idField) field.value = editId;
      });
    }
    var mode = modal.querySelector('.eyebrow');
    if (mode) mode.textContent = editId === '0' ? 'New entry' : 'Editing';
    modals.forEach(function (other) { other.hidden = other !== modal; });
    modal.hidden = false;
    var firstField = form.querySelector('input:not([type="hidden"]), select');
    if (firstField) firstField.focus();
  };

  document.querySelectorAll('.calendar-day').forEach(function (day) {
    day.addEventListener('click', function (event) {
      if (event.target.closest('.calendar-chip')) return;
      event.preventDefault();
      openDayCard(day.dataset.date, day);
    });
  });

  document.querySelector('#calendar-day-card-close').addEventListener('click', function () { closeDayCard(true); });

  document.querySelector('#calendar-day-card-add-button').addEventListener('click', function () {
    var kind = kindSelect.value;
    closeDayCard(false);
    openModal(kind, { event_date: activeDate, date: activeDate, start_date: activeDate });
  });

  dayCardItems.addEventListener('click', function (event) {
    var button = event.target.closest('[data-calendar-edit]');
    if (!button) return;
    var parts = String(button.dataset.calendarEdit).split(':');
    var fields = calendarEntryData[parts[0] + ':' + parts[1]];
    if (!fields) return;
    closeDayCard(false);
    openModal(parts[0], fields);
  });

  document.querySelectorAll('.calendar-chip').forEach(function (chip) {
    chip.addEventListener('click', function () { closeDayCard(false); });
  });

  modals.forEach(function (modal) {
    modal.querySelectorAll('[data-calendar-modal-close]').forEach(function (button) {
      button.addEventListener('click', function () { modal.hidden = true; });
    });
    modal.addEventListener('click', function (event) {
      if (event.target === modal) modal.hidden = true;
    });
  });

  document.addEventListener('click', function (event) {
    if (dayCard.hidden) return;
    if (event.target.closest('#calendar-day-card') || event.target.closest('.calendar-day')) return;
    closeDayCard(false);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    if (!dayCard.hidden) { closeDayCard(true); return; }
    modals.forEach(function (modal) { modal.hidden = true; });
  });
}());
</script>
<?php page_end();
