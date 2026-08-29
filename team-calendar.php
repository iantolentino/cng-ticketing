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
<div class="team-calendar-screen">
    <?php if ($notice): ?><p class="action-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="auth-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <section class="team-calendar-panel" aria-label="<?= e(date('F Y', strtotime($monthStart))) ?> calendar">
        <div class="team-calendar-toolbar">
            <nav class="calendar-month-nav" aria-label="Calendar month navigation">
                <a class="calendar-nav-button" href="team-calendar.php?month=<?= e($prevMonth) ?>" aria-label="Previous month"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></a>
                <div><h2><?= e(date('F Y', strtotime($monthStart))) ?></h2><p>Team schedule and availability</p></div>
                <a class="calendar-nav-button" href="team-calendar.php?month=<?= e($nextMonth) ?>" aria-label="Next month"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></a>
                <a class="calendar-today-link" href="team-calendar.php?month=<?= e(date('Y-m')) ?>">Today</a>
            </nav>
            <div class="calendar-summary-inline" aria-label="Month summary"><span><i class="calendar-legend-dot is-holiday"></i>Holidays <b><?= count($holidayRows) ?></b></span><span><i class="calendar-legend-dot is-leave"></i>Leave <b><?= count($approvedLeave) ?></b></span><span><i class="calendar-legend-dot is-event"></i>Events <b><?= count($eventRows) ?></b></span><span><i class="calendar-legend-dot is-attendance"></i>Attendance <b><?= count($attendanceRows) ?></b></span></div>
        </div>
        <div class="calendar-weekdays"><?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $weekday): ?><span><?= e($weekday) ?></span><?php endforeach; ?></div>
        <div class="calendar-month-grid"><?php for ($day = $calendarStart; $day <= $calendarEnd; $day = strtotime('+1 day', $day)): $date = date('Y-m-d', $day); $outside = substr($date, 0, 7) !== $month; $items = $itemsByDate[$date] ?? []; ?><div class="calendar-day<?= $outside ? ' is-outside' : '' ?><?= $date === date('Y-m-d') ? ' is-today' : '' ?>" data-date="<?= e($date) ?>"><button type="button" class="calendar-date-button" data-date="<?= e($date) ?>" aria-label="Add item on <?= e(date('F j, Y', $day)) ?>"><span class="calendar-date"><?= e(date('j', $day)) ?></span></button><?php foreach (array_slice($items, 0, 3) as $item): ?><button type="button" class="calendar-chip calendar-chip-<?= e($item['kind']) ?>" data-detail-id="<?= e($item['detail_id']) ?>"><?= e($item['label']) ?></button><?php endforeach; ?><?php if (count($items) > 3): ?><span class="calendar-more">+<?= count($items) - 3 ?> more</span><?php endif; ?></div><?php endfor; ?></div>
    </section>
    <div class="calendar-popover" id="calendar-popover" role="dialog" aria-labelledby="calendar-detail-title" aria-live="polite" hidden><div class="calendar-popover-head"><div><p class="eyebrow" id="calendar-detail-type">Details</p><h2 id="calendar-detail-title">Calendar item</h2><p class="muted" id="calendar-detail-date"></p></div><button type="button" class="calendar-popover-close" id="calendar-detail-close" aria-label="Close details"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div><p id="calendar-detail-body"></p><div id="calendar-detail-files" class="attachment-list"></div></div>
    <div class="team-calendar-lower-grid">
        <section class="calendar-form-panel" aria-labelledby="calendar-event-title">
            <div class="calendar-form-head"><div><h2 id="calendar-event-title">Add calendar event</h2><p>Create a team event, coverage entry, or reminder.</p></div><span class="calendar-form-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span></div>
            <form method="post" class="calendar-compact-form" id="calendar-event-form" data-submit-on-change="false"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add_event">
                <label class="calendar-form-field is-wide">Title<input name="title" required placeholder="Event title"></label>
                <div class="calendar-form-field"><span class="calendar-field-label">Type</span><div class="filter-picker calendar-select-picker" data-filter-picker><input type="hidden" name="event_type" value="team_event"><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-copy"><strong data-filter-label>Team event</strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Event type</p><?php foreach ($eventTypes as $key => $label): ?><button type="button" class="filter-option<?= $key === 'team_event' ? ' is-selected' : '' ?>" data-filter-target="event_type" data-filter-value="<?= e($key) ?>" data-filter-label="<?= e($label) ?>"><span><?= e($label) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?></div></div></div>
                <div class="calendar-form-field calendar-date-picker" data-date-picker><span class="calendar-field-label">Start date</span><input type="hidden" name="event_date" value="<?= e($monthStart) ?>" data-date-input><button type="button" class="filter-picker-trigger" data-date-trigger aria-expanded="false"><span class="filter-picker-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M8 3.5v3M16 3.5v3M3.5 9.5h17"/></svg></span><span class="filter-picker-copy"><strong data-date-label><?= e(date('M j, Y', strtotime($monthStart))) ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu date-picker-menu" data-date-menu hidden></div></div>
                <div class="calendar-form-field calendar-date-picker" data-date-picker data-empty-date-label="Optional"><span class="calendar-field-label">End date</span><input type="hidden" name="end_date" value="" data-date-input><button type="button" class="filter-picker-trigger" data-date-trigger aria-expanded="false"><span class="filter-picker-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M8 3.5v3M16 3.5v3M3.5 9.5h17"/></svg></span><span class="filter-picker-copy"><strong data-date-label>Optional</strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu date-picker-menu" data-date-menu hidden></div></div>
                <div class="calendar-form-submit"><button class="button">Save event</button></div>
            </form>
        </section>
        <section class="calendar-form-panel" aria-labelledby="calendar-holiday-title">
            <div class="calendar-form-head"><div><h2 id="calendar-holiday-title">Add holiday</h2><p>Add a company or regional public holiday.</p></div><span class="calendar-form-icon is-holiday" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3.5v2M12 18.5v2M3.5 12h2M18.5 12h2M6 6l1.5 1.5M16.5 16.5 18 18M18 6l-1.5 1.5M7.5 16.5 6 18"/><circle cx="12" cy="12" r="4"/></svg></span></div>
            <form method="post" class="calendar-compact-form" id="calendar-holiday-form" data-submit-on-change="false"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add_holiday">
                <label class="calendar-form-field is-wide">Holiday label<input name="label" required placeholder="Holiday name"></label>
                <div class="calendar-form-field"><span class="calendar-field-label">Country</span><div class="filter-picker calendar-select-picker" data-filter-picker><input type="hidden" name="country_code" value="COMPANY"><button type="button" class="filter-picker-trigger" data-filter-trigger aria-expanded="false"><span class="filter-picker-copy"><strong data-filter-label>Company</strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu" data-filter-menu hidden><p class="filter-menu-label">Country</p><?php foreach ($holidayCountries as $key => $label): ?><button type="button" class="filter-option<?= $key === 'COMPANY' ? ' is-selected' : '' ?>" data-filter-target="country_code" data-filter-value="<?= e($key) ?>" data-filter-label="<?= e($label) ?>"><span><?= e($label) ?></span><span class="filter-option-check" aria-hidden="true">✓</span></button><?php endforeach; ?></div></div></div>
                <div class="calendar-form-field calendar-date-picker" data-date-picker><span class="calendar-field-label">Date</span><input type="hidden" name="date" value="<?= e($monthStart) ?>" data-date-input><button type="button" class="filter-picker-trigger" data-date-trigger aria-expanded="false"><span class="filter-picker-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M8 3.5v3M16 3.5v3M3.5 9.5h17"/></svg></span><span class="filter-picker-copy"><strong data-date-label><?= e(date('M j, Y', strtotime($monthStart))) ?></strong></span><svg class="filter-picker-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 9 5 5 5-5"/></svg></button><div class="filter-picker-menu date-picker-menu" data-date-menu hidden></div></div>
                <div class="calendar-form-submit"><button class="button">Save holiday</button></div>
            </form>
        </section>
        <aside class="calendar-month-summary" aria-labelledby="calendar-summary-title"><div class="calendar-form-head"><div><h2 id="calendar-summary-title">Month summary</h2><p>Automatically includes approved leave and attendance.</p></div></div><div class="calendar-summary-list"><div><span><i class="calendar-legend-dot is-holiday"></i>Holidays</span><strong><?= count($holidayRows) ?></strong></div><div><span><i class="calendar-legend-dot is-leave"></i>Approved leave</span><strong><?= count($approvedLeave) ?></strong></div><div><span><i class="calendar-legend-dot is-event"></i>Team events</span><strong><?= count($eventRows) ?></strong></div><div><span><i class="calendar-legend-dot is-attendance"></i>Attendance</span><strong><?= count($attendanceRows) ?></strong></div></div></aside>
    </div>
</div>
<script>
document.querySelectorAll('.calendar-day').forEach(function(day){
  day.addEventListener('click', function(event){
    if (event.target.closest('.calendar-chip')) return;
    setCalendarDate(day.dataset.date);
  });
});
function setCalendarDate(date) {
  ['#calendar-event-form input[name="event_date"]', '#calendar-holiday-form input[name="date"]'].forEach(function(selector){
    var input = document.querySelector(selector);
    input.value = date;
    input.dispatchEvent(new Event('change', { bubbles: true }));
  });
  document.querySelector('#calendar-event-form input[name="title"]').focus();
}
var calendarDetails = <?= json_encode($details, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
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
</script>
<?php page_end();
