<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('view_all_tickets');
if (($user['role_slug'] ?? '') === 'cng-admin') { http_response_code(403); exit('You do not have permission to access this action.'); }
$pdo = db();
$notice = '';
$error = '';
$month = trim((string) ($_GET['month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
$monthStart = $month . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$eventTypes = ['team_event' => 'Team event', 'coverage' => 'Coverage', 'reminder' => 'Reminder', 'other' => 'Other'];
$holidayCountries = ['COMPANY' => 'Company', 'PH' => 'Philippines', 'AU' => 'Australia', 'CA' => 'Canada'];
$attendanceStatuses = ['annual' => 'Annual', 'sick' => 'Sick', 'emergency' => 'Emergency', 'half_day' => 'Half-day', 'birthday' => 'Birthday', 'bereavement' => 'Bereavement', 'paternity' => 'Paternity', 'maternity' => 'Maternity', 'undertime' => 'Undertime', 'present' => 'Present', 'partial' => 'Partial coverage', 'absent' => 'Absent', 'training' => 'Training', 'work_from_home' => 'Work from home'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add_event') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $eventDate = trim((string) ($_POST['event_date'] ?? ''));
        $endDate = trim((string) ($_POST['end_date'] ?? ''));
        $eventType = trim((string) ($_POST['event_type'] ?? 'team_event'));
        if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate) || ($endDate !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) || $endDate < $eventDate)) || !isset($eventTypes[$eventType])) {
            $error = 'Enter a valid event title, type, and date range.';
        } else {
            $pdo->prepare('INSERT INTO calendar_events(title,event_date,end_date,event_type,created_by) VALUES(?,?,?,?,?)')->execute([$title, $eventDate, $endDate ?: null, $eventType, $user['id']]);
            audit_admin_action((int) $user['id'], 'calendar_event_created', 'calendar_event', (int) $pdo->lastInsertId(), ['title' => $title, 'event_date' => $eventDate]);
            redirect('team-calendar.php?month=' . substr($eventDate, 0, 7) . '&notice=event_saved');
        }
    } elseif ($action === 'add_holiday') {
        $date = trim((string) ($_POST['date'] ?? ''));
        $label = trim((string) ($_POST['label'] ?? ''));
        $country = trim((string) ($_POST['country_code'] ?? 'COMPANY'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $label === '' || !isset($holidayCountries[$country])) {
            $error = 'Enter a valid holiday date, label, and country.';
        } else {
            $type = $country === 'COMPANY' ? 'company' : 'public';
            $pdo->prepare('INSERT INTO company_holidays(`date`,label,country_code,holiday_type) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE holiday_type=VALUES(holiday_type)')->execute([$date, $label, $country, $type]);
            audit_admin_action((int) $user['id'], 'company_holiday_saved', 'company_holiday', null, ['date' => $date, 'label' => $label, 'country_code' => $country]);
            redirect('team-calendar.php?month=' . substr($date, 0, 7) . '&notice=holiday_saved');
        }
    }
}

$notices = ['holiday_saved' => 'Holiday saved.', 'event_saved' => 'Event saved.'];
$notice = $notices[$_GET['notice'] ?? ''] ?? '';
$itemsByDate = [];
$details = [];
$addItem = static function (array &$bucket, string $date, array $item): void {
    $bucket[$date] ??= [];
    $bucket[$date][] = $item;
};
$addDetail = static function (array &$details, array $detail): string {
    $id = 'cal-detail-' . (count($details) + 1);
    $details[$id] = $detail;
    return $id;
};

$holidays = $pdo->prepare('SELECT * FROM company_holidays WHERE `date` BETWEEN ? AND ? ORDER BY `date`, country_code, label');
$holidays->execute([$monthStart, $monthEnd]);
$holidayRows = $holidays->fetchAll();
foreach ($holidayRows as $holiday) {
    $detailId = $addDetail($details, [
        'title' => $holiday['label'],
        'type' => ($holidayCountries[$holiday['country_code']] ?? $holiday['country_code']) . ' holiday',
        'date' => $holiday['date'],
        'body' => 'Holiday type: ' . $holiday['holiday_type'],
        'attachments' => [],
    ]);
    $addItem($itemsByDate, $holiday['date'], ['kind' => 'holiday', 'label' => $holiday['country_code'] . ' - ' . $holiday['label'], 'detail_id' => $detailId]);
}

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
    $start = max(strtotime($request['start_date']), strtotime($monthStart));
    $end = min(strtotime($request['end_date']), strtotime($monthEnd));
    for ($day = $start; $day <= $end; $day = strtotime('+1 day', $day)) {
        $addItem($itemsByDate, date('Y-m-d', $day), ['kind' => 'leave', 'label' => $request['employee'] . ' leave', 'detail_id' => $detailId]);
    }
}

$events = $pdo->prepare('SELECT ce.*, u.full_name AS creator FROM calendar_events ce JOIN users u ON u.id = ce.created_by WHERE ce.event_date <= ? AND COALESCE(ce.end_date, ce.event_date) >= ? ORDER BY ce.event_date, ce.title');
$events->execute([$monthEnd, $monthStart]);
$eventRows = $events->fetchAll();
foreach ($eventRows as $event) {
    $detailId = $addDetail($details, [
        'title' => $event['title'],
        'type' => $eventTypes[$event['event_type']],
        'date' => $event['event_date'] . ($event['end_date'] ? ' to ' . $event['end_date'] : ''),
        'body' => 'Created by ' . $event['creator'],
        'attachments' => [],
    ]);
    $start = max(strtotime($event['event_date']), strtotime($monthStart));
    $end = min(strtotime($event['end_date'] ?: $event['event_date']), strtotime($monthEnd));
    for ($day = $start; $day <= $end; $day = strtotime('+1 day', $day)) {
        $addItem($itemsByDate, date('Y-m-d', $day), ['kind' => 'event', 'label' => $eventTypes[$event['event_type']] . ': ' . $event['title'], 'detail_id' => $detailId]);
    }
}

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
}

$calendarStart = strtotime($monthStart . ' -' . date('w', strtotime($monthStart)) . ' days');
$calendarEnd = strtotime($monthEnd . ' +' . (6 - (int) date('w', strtotime($monthEnd))) . ' days');
$prevMonth = date('Y-m', strtotime($monthStart . ' -1 month'));
$nextMonth = date('Y-m', strtotime($monthStart . ' +1 month'));

page_start('Team Calendar', $user);
?>
<?php if ($notice): ?><p class="action-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="auth-error"><?= e($error) ?></p><?php endif; ?>
<div class="page-head"><div><p class="eyebrow">Planning</p><h1>Team Calendar</h1><p class="page-subtitle">Click a day to add an event or holiday. Approved leave is added automatically after Team Leader and Department Head approval.</p></div><div class="page-head-actions"><a class="button button-secondary" href="team-calendar.php?month=<?= e($prevMonth) ?>">Previous</a><form method="get" class="month-form"><label>Month<input type="month" name="month" value="<?= e($month) ?>" onchange="this.form.submit()"></label></form><a class="button button-secondary" href="team-calendar.php?month=<?= e($nextMonth) ?>">Next</a></div></div>
<section class="calendar-board" aria-label="<?= e(date('F Y', strtotime($monthStart))) ?> calendar">
    <div class="calendar-weekdays"><?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $weekday): ?><span><?= e($weekday) ?></span><?php endforeach; ?></div>
    <div class="calendar-month-grid"><?php for ($day = $calendarStart; $day <= $calendarEnd; $day = strtotime('+1 day', $day)): $date = date('Y-m-d', $day); $outside = substr($date, 0, 7) !== $month; $items = $itemsByDate[$date] ?? []; ?><div class="calendar-day<?= $outside ? ' is-outside' : '' ?><?= $date === date('Y-m-d') ? ' is-today' : '' ?>" data-date="<?= e($date) ?>"><button type="button" class="calendar-date-button" data-date="<?= e($date) ?>"><span class="calendar-date"><?= e(date('j', $day)) ?></span></button><?php foreach (array_slice($items, 0, 4) as $item): ?><button type="button" class="calendar-chip calendar-chip-<?= e($item['kind']) ?>" data-detail-id="<?= e($item['detail_id']) ?>"><?= e($item['label']) ?></button><?php endforeach; ?><?php if (count($items) > 4): ?><span class="calendar-more">+<?= count($items) - 4 ?> more</span><?php endif; ?></div><?php endfor; ?></div>
</section>
<div class="calendar-popover" id="calendar-popover" role="dialog" aria-live="polite" hidden><div class="calendar-popover-head"><div><p class="eyebrow" id="calendar-detail-type">Details</p><h2 id="calendar-detail-title">Calendar item</h2><p class="muted" id="calendar-detail-date"></p></div><button type="button" class="calendar-popover-close" id="calendar-detail-close" aria-label="Close details">&times;</button></div><p id="calendar-detail-body"></p><div id="calendar-detail-files" class="attachment-list"></div></div>
<div class="calendar-grid">
    <section><h2>Add calendar event</h2><form method="post" class="ticket-form compact-form" id="calendar-event-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add_event"><label>Title<input name="title" required></label><label>Type<select name="event_type"><?php foreach ($eventTypes as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label><label>Start date<input type="date" name="event_date" required value="<?= e($monthStart) ?>"></label><label>End date<input type="date" name="end_date"></label><button class="button">Save event</button></form></section>
    <section><h2>Add holiday</h2><form method="post" class="ticket-form compact-form" id="calendar-holiday-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add_holiday"><label>Holiday label<input name="label" required></label><label>Country<select name="country_code"><?php foreach ($holidayCountries as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label><label>Date<input type="date" name="date" required value="<?= e($monthStart) ?>"></label><button class="button">Save holiday</button></form></section>
</div>
<section><div class="section-head"><div><h2>Month summary</h2><p class="muted">PH, AU, and CA public holidays are seeded for 2026. Approved leave and Team Attendance records are pulled into this calendar automatically.</p></div></div><div class="status-strip"><div><span>Holidays</span><strong><?= count($holidayRows) ?></strong></div><div><span>Approved leave</span><strong><?= count($approvedLeave) ?></strong></div><div><span>Team events</span><strong><?= count($eventRows) ?></strong></div><div><span>Attendance</span><strong><?= count($attendanceRows) ?></strong></div></div></section>
<script>
document.querySelectorAll('.calendar-day').forEach(function(day){
  day.addEventListener('click', function(event){
    if (event.target.closest('.calendar-chip')) return;
    setCalendarDate(day.dataset.date);
  });
});
function setCalendarDate(date) {
  document.querySelector('#calendar-event-form input[name="event_date"]').value = date;
  document.querySelector('#calendar-holiday-form input[name="date"]').value = date;
  document.querySelector('#calendar-event-form input[name="title"]').focus();
}
var calendarDetails = <?= json_encode($details, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
var detailPanel = document.querySelector('#calendar-popover');
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
    detailPanel.hidden = false;
    var rect = chip.getBoundingClientRect();
    var top = Math.min(rect.bottom + 8, window.innerHeight - detailPanel.offsetHeight - 12);
    var left = Math.min(rect.left, window.innerWidth - detailPanel.offsetWidth - 12);
    detailPanel.style.top = Math.max(12, top) + 'px';
    detailPanel.style.left = Math.max(12, left) + 'px';
  });
});
document.querySelector('#calendar-detail-close').addEventListener('click', function(){ detailPanel.hidden = true; });
document.addEventListener('click', function(event){
  if (detailPanel.hidden) return;
  if (event.target.closest('#calendar-popover') || event.target.closest('.calendar-chip')) return;
  detailPanel.hidden = true;
});
document.addEventListener('keydown', function(event){
  if (event.key === 'Escape') detailPanel.hidden = true;
});
</script>
<?php page_end();
