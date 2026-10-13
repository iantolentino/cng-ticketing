<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/tickets.php';
require __DIR__ . '/app/roster.php';
require __DIR__ . '/app/layout.php';

$user = require_permission('view_all_tickets');
require_non_team_member($user);
// CNG Admin reads this screen but does not edit it: roster_can_manage_attendance()
// excludes that role, so every write path below is already gated by $canManage.
// Previously this page hardcoded a 403 for cng-admin, which contradicted the
// sidebar link and hid the roster from them entirely.

$pdo = db();
$canManage = roster_can_manage_attendance($user);
$notice = '';
$error = '';

/* -------------------------------------------------------------------------
 * Staff shift / employment status
 * ---------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_staff') {
    verify_csrf();
    if (!$canManage) { http_response_code(403); exit('You do not have permission to manage attendance.'); }
    $staffId = (int) request_string($_POST, 'staff_id');
    $staff = roster_staff_load($staffId);
    if (!$staff) {
        $error = 'Choose a staff member from the roster.';
    } else {
        $shiftDays = roster_shift_days_from_post($_POST);
        $shiftStart = roster_time_value($_POST, 'shift_start');
        $shiftEnd = roster_time_value($_POST, 'shift_end');
        $breakRaw = trim((string) request_string($_POST, 'break_minutes'));
        $status = trim((string) request_string($_POST, 'employment_status'));
        $effective = trim((string) request_string($_POST, 'status_effective_date'));
        $statusNote = trim((string) request_string($_POST, 'status_note'));
        $departmentId = (int) request_string($_POST, 'department_id');
        $teamLeaderId = (int) request_string($_POST, 'team_leader_id');
        $replacementForId = (int) request_string($_POST, 'replacement_for_id');
        $departmentIds = array_map('intval', array_column(active_departments(), 'id'));
        $staffIds = array_map('intval', array_column(roster_staff_rows(), 'id'));
        $effectiveValue = DateTimeImmutable::createFromFormat('!Y-m-d', $effective);

        if ($shiftDays === '') {
            $error = 'Select at least one working day for the shift.';
        } elseif (($shiftStart === null) !== ($shiftEnd === null)) {
            $error = 'Enter both a shift start and a shift end time, or leave both empty.';
        } elseif ($shiftStart !== null && $shiftEnd !== null && $shiftEnd <= $shiftStart) {
            $error = 'The shift end time must be after the shift start time.';
        } elseif (!isset(ROSTER_STATUSES[$status])) {
            $error = 'Choose a valid employment status.';
        } elseif ($effective !== '' && (!$effectiveValue || $effectiveValue->format('Y-m-d') !== $effective)) {
            $error = 'Enter a valid effective date.';
        } elseif ($breakRaw !== '' && !ctype_digit($breakRaw)) {
            $error = 'Break minutes must be a whole number.';
        } elseif ((int) ($breakRaw === '' ? 60 : $breakRaw) > 480) {
            $error = 'Break minutes must be 480 or fewer.';
        } elseif ($departmentId > 0 && !in_array($departmentId, $departmentIds, true)) {
            $error = 'Choose a valid department.';
        } elseif ($teamLeaderId > 0 && !in_array($teamLeaderId, $staffIds, true)) {
            $error = 'Choose a valid team leader.';
        } elseif ($replacementForId === $staffId && $staffId > 0) {
            $error = 'A staff member cannot be their own replacement.';
        } elseif ($replacementForId > 0 && !in_array($replacementForId, $staffIds, true)) {
            $error = 'Choose a valid staff member to replace.';
        } elseif (mb_strlen($statusNote) > 190) {
            $error = 'Keep the status note under 190 characters.';
        } else {
            $breakMinutes = (int) ($breakRaw === '' ? 60 : $breakRaw);
            $pdo->prepare('UPDATE staff_directory SET shift_days=?, shift_start=?, shift_end=?, break_minutes=?, shift_schedule=?,
                department_id=?, team_leader_id=?, employment_status=?, status_effective_date=?, status_note=?, replacement_for_id=? WHERE id=?')
                ->execute([
                    $shiftDays,
                    $shiftStart,
                    $shiftEnd,
                    $breakMinutes,
                    roster_shift_schedule_string($shiftDays, $shiftStart, $shiftEnd),
                    $departmentId > 0 ? $departmentId : null,
                    $teamLeaderId > 0 ? $teamLeaderId : null,
                    $status,
                    $effective !== '' ? $effective : null,
                    $statusNote !== '' ? $statusNote : null,
                    $replacementForId > 0 ? $replacementForId : null,
                    $staffId,
                ]);
            audit_admin_action((int) $user['id'], 'staff_roster_updated', 'staff_directory', $staffId, [
                'shift_days' => $shiftDays, 'shift_start' => $shiftStart, 'shift_end' => $shiftEnd,
                'employment_status' => $status, 'department_id' => $departmentId, 'team_leader_id' => $teamLeaderId,
            ]);
            redirect('team-attendance.php?staff=' . $staffId . '&notice=staff_saved');
        }
    }
}

/* -------------------------------------------------------------------------
 * Staff leave is no longer filed from this screen.
 *
 * Leave lives in one place - the Team Calendar - and this screen only reads it,
 * so there is no second form that could disagree with the calendar. Both used
 * to call roster_leave_save()/roster_leave_delete(); those handlers now exist
 * only in team-calendar.php.
 * ---------------------------------------------------------------------- */

/* -------------------------------------------------------------------------
 * Viewing date. Historical department attendance rows still exist and the
 * Team Calendar reads them, but this screen no longer logs or lists them.
 * ---------------------------------------------------------------------- */
$selectedDate = request_string($_GET, 'date') ?: date('Y-m-d');
$selectedDateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $selectedDate);
if (!$selectedDateValue || $selectedDateValue->format('Y-m-d') !== $selectedDate) $selectedDate = date('Y-m-d');

// Only notices this screen can still produce: 'saved'/'updated' belonged to the
// retired department log and the leave_* notices to the leave form that moved to
// the Team Calendar.
$notices = [
    'staff_saved' => 'Shift and roster details saved.',
];
$notice = $notices[request_string($_GET, 'notice')] ?? '';

/* -------------------------------------------------------------------------
 * Filters + selected staff
 * ---------------------------------------------------------------------- */
$departments = active_departments();
$departmentIds = array_map('intval', array_column($departments, 'id'));
$filterDepartment = (int) request_string($_GET, 'department');
if ($filterDepartment > 0 && !in_array($filterDepartment, $departmentIds, true)) $filterDepartment = 0;
$filterUnassigned = request_string($_GET, 'department') === 'none';
$filterTeam = trim((string) request_string($_GET, 'team'));
if ($filterTeam !== '' && !in_array($filterTeam, roster_team_labels(), true)) $filterTeam = '';
$filterStatus = trim((string) request_string($_GET, 'status'));
if ($filterStatus !== '' && !isset(ROSTER_STATUSES[$filterStatus])) $filterStatus = '';
$filterSearch = trim((string) request_string($_GET, 'search'));

$rosterOptions = [
    'department_id' => $filterDepartment,
    'department_unassigned' => $filterUnassigned,
    'team_label' => $filterTeam,
    'search' => $filterSearch,
];
if ($filterStatus !== '') $rosterOptions['statuses'] = [$filterStatus];
$staffRows = roster_staff_rows($rosterOptions);

$selectedStaffId = (int) request_string($_GET, 'staff');
$selectedStaff = roster_staff_load($selectedStaffId);
if ($selectedStaffId > 0 && !$selectedStaff) $selectedStaffId = 0;

// Read-only leave context for the staff card. Filing and editing happens on the
// Team Calendar, which owns these records, so nothing here is editable.
$staffLeave = $selectedStaffId > 0 ? roster_leave_rows(['staff_id' => $selectedStaffId]) : [];
$upcomingLeave = array_values(array_filter($staffLeave, static fn(array $row): bool => ((string) ($row['end_date'] ?: $row['event_date'])) >= date('Y-m-d')));
$leaveToday = roster_leave_rows([
    'from' => date('Y-m-d'),
    'to' => date('Y-m-d'),
    'statuses' => ROSTER_WORKING_STATUSES,
]);
$leaveTodayByStaff = [];
foreach ($leaveToday as $row) $leaveTodayByStaff[(int) $row['staff_id']] = roster_leave_type_label($row['leave_type']);
$rosterCounts = ['total' => count($staffRows)];
foreach (ROSTER_STATUSES as $statusKey => $statusLabel) {
    $rosterCounts[$statusKey] = count(array_filter($staffRows, static fn(array $row): bool => (string) $row['employment_status'] === $statusKey));
}
$leaderOptions = roster_staff_rows();
usort($leaderOptions, static fn(array $left, array $right): int => strcasecmp((string) $left['full_name'], (string) $right['full_name']));

/* -------------------------------------------------------------------------
 * Attendance summary for the viewing date
 *
 * "Total headcount" is every staff member on the working roster (active and
 * for-replacement) who has already been hired by the viewing date. It is
 * deliberately not filtered by shift pattern, so it reads as the company's
 * headcount rather than the number of people rostered that particular day.
 * "Staff on leave" is read from the Team Calendar leave entries, so this panel
 * and the calendar can never disagree. The retired department attendance log no
 * longer feeds these numbers; it is kept below as a read-only history.
 * ---------------------------------------------------------------------- */
$headcountStaff = array_values(array_filter(
    roster_staff_rows(['statuses' => ROSTER_WORKING_STATUSES]),
    static function (array $row) use ($selectedDate): bool {
        $hired = (string) ($row['hired_date'] ?? '');
        return $hired === '' || $hired <= $selectedDate;
    }
));
$leaveOnDateIds = array_map('intval', array_column(roster_leave_rows([
    'from' => $selectedDate,
    'to' => $selectedDate,
    'statuses' => ROSTER_WORKING_STATUSES,
]), 'staff_id'));
$headcountCount = count($headcountStaff);
$onLeaveCount = count(array_filter(
    $headcountStaff,
    static fn(array $row): bool => in_array((int) $row['id'], $leaveOnDateIds, true)
));

/* Read-only history of the retired department attendance log. This screen no
 * longer creates these records, but the existing ones stay browsable here and
 * still render on the Team Calendar. logged_by is LEFT JOINed so a record
 * survives the deletion of the user who filed it. */
$departmentAttendanceStatuses = ROSTER_LEAVE_TYPES + [
    'present' => 'Present',
    'partial' => 'Partial coverage',
    'absent' => 'Absent',
    'training' => 'Training',
    'work_from_home' => 'Work from home',
];
$recentDepartmentLogs = $pdo->query('SELECT ta.*, d.name AS department, COALESCE(u.full_name, \'Removed user\') AS logger, (SELECT COUNT(*) FROM team_attendance_leave tal WHERE tal.attendance_id = ta.id) AS leave_count FROM team_attendance ta JOIN departments d ON d.id = ta.department_id LEFT JOIN users u ON u.id = ta.logged_by ORDER BY ta.attendance_date DESC, d.name LIMIT 20')->fetchAll();

$rosterListQuery = static function (array $overrides = []) use ($filterDepartment, $filterUnassigned, $filterTeam, $filterStatus, $filterSearch): string {
    $params = array_filter([
        'department' => $filterDepartment > 0 ? (string) $filterDepartment : ($filterUnassigned ? 'none' : ''),
        'team' => $filterTeam,
        'status' => $filterStatus,
        'search' => $filterSearch,
    ], static fn(string $value): bool => $value !== '');
    return 'team-attendance.php' . ($params ? '?' . http_build_query($params + $overrides) : ($overrides ? '?' . http_build_query($overrides) : ''));
};

page_start('Team Attendance & Recruitment', $user);
?>
<div class="attendance-screen">
<?php /* While the modal is open it covers the page, so its own save notice is shown
         inside the modal instead - otherwise the confirmation would be hidden behind
         the overlay. */ ?>
<?php if (!$selectedStaff): ?>
<?php if ($notice): ?><p class="action-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="auth-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php endif; ?>

<section class="attendance-roster-panel" aria-labelledby="attendance-roster-title">
    <div class="section-head"><div><h2 id="attendance-roster-title">Staff attendance</h2><p class="muted">Pick a staff member to set their weekly shift, their department, and their employment status. Leave is managed on the Team Calendar.</p></div><span class="section-count"><?= (int) $rosterCounts['total'] ?> shown &middot; <?= (int) $rosterCounts['active'] ?> active</span></div>
    <form method="get" class="attendance-filter-bar" aria-label="Filter the staff list">
        <label class="attendance-filter-field"><span>Search</span><input type="search" name="search" value="<?= e($filterSearch) ?>" placeholder="Name, team, or department" autocomplete="off"></label>
        <label class="attendance-filter-field"><span>Department</span><select name="department"><option value="">All departments</option><option value="none"<?= $filterUnassigned ? ' selected' : '' ?>>Not assigned yet</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"<?= $filterDepartment === (int) $department['id'] ? ' selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></label>
        <label class="attendance-filter-field"><span>Team</span><select name="team"><option value="">All teams</option><?php foreach (roster_team_labels() as $teamLabel): ?><option value="<?= e((string) $teamLabel) ?>"<?= $filterTeam === (string) $teamLabel ? ' selected' : '' ?>><?= e((string) $teamLabel) ?></option><?php endforeach; ?></select></label>
        <label class="attendance-filter-field"><span>Status</span><select name="status"><option value="">All statuses</option><?php foreach (ROSTER_STATUSES as $statusKey => $statusLabel): ?><option value="<?= e($statusKey) ?>"<?= $filterStatus === $statusKey ? ' selected' : '' ?>><?= e($statusLabel) ?></option><?php endforeach; ?></select></label>
        <button class="button" type="submit">Apply</button>
        <a class="button button-secondary" href="team-attendance.php">Reset</a>
    </form>
    <div class="attendance-roster-grid" role="list">
        <?php foreach ($staffRows as $row): $rowId = (int) $row['id']; $isSelected = $rowId === $selectedStaffId; ?>
            <a class="attendance-roster-card<?= $isSelected ? ' is-selected' : '' ?><?= in_array((string) $row['employment_status'], ROSTER_WORKING_STATUSES, true) ? '' : ' is-inactive' ?>" role="listitem" href="<?= e($rosterListQuery(['staff' => $rowId])) ?>">
                <span class="attendance-roster-name"><?= e($row['full_name']) ?></span>
                <span class="attendance-roster-meta"><?= e($row['team_label']) ?><?= $row['department'] ? ' &middot; ' . e((string) $row['department']) : '' ?></span>
                <span class="attendance-roster-shift<?= roster_shift_summary($row) === '' ? ' is-missing' : '' ?>"><?= e(roster_shift_summary($row) ?: 'No shift set') ?></span>
                <span class="attendance-roster-tags">
                    <span class="pill pill-<?= (string) $row['employment_status'] === 'active' ? 'closed' : 'pending' ?>"><?= e(roster_status_label((string) $row['employment_status'])) ?></span>
                    <?php if (isset($leaveTodayByStaff[$rowId])): ?><span class="pill pill-leave">On leave today &middot; <?= e($leaveTodayByStaff[$rowId]) ?></span><?php elseif ((int) ($row['upcoming_leave'] ?? 0) > 0): ?><span class="pill pill-pending"><?= (int) $row['upcoming_leave'] ?> upcoming</span><?php endif; ?>
                    <?php if ($row['team_leader_name']): ?><span class="attendance-roster-leader">TL: <?= e((string) $row['team_leader_name']) ?></span><?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
        <?php if (!$staffRows): ?><p class="muted attendance-empty-note">No staff match these filters.</p><?php endif; ?>
    </div>
</section>

<?php if ($selectedStaff): ?>
<?php /* The staff card is a modal so choosing someone never requires scrolling to
         the bottom of the roster to reach the form. It is rendered open by default
         and closes back to the filtered list via the X, ESC, or a backdrop click. */ ?>
<div class="calendar-modal attendance-modal" id="attendance-staff-modal" role="dialog" aria-modal="true" aria-labelledby="attendance-staff-title">
<div class="calendar-modal-panel attendance-modal-panel">
    <div class="section-head">
        <div><h2 id="attendance-staff-title"><?= e($selectedStaff['full_name']) ?></h2><p class="muted"><?= e($selectedStaff['team_label']) ?><?= $selectedStaff['department'] ? ' &middot; ' . e((string) $selectedStaff['department']) : '' ?><?= $selectedStaff['position_title'] ? ' &middot; ' . e((string) $selectedStaff['position_title']) : '' ?></p></div>
        <div class="attendance-modal-head-actions">
            <span class="section-count"><?= e(roster_status_label((string) $selectedStaff['employment_status'])) ?><?= $selectedStaff['hired_date'] ? ' &middot; since ' . e(date('M j, Y', strtotime((string) $selectedStaff['hired_date']))) : '' ?></span>
            <a class="attendance-modal-close" href="<?= e($rosterListQuery()) ?>" aria-label="Close staff details" data-attendance-modal-close>&times;</a>
        </div>
    </div>
    <?php if ($notice): ?><p class="action-notice attendance-modal-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="auth-error attendance-modal-notice" role="alert"><?= e($error) ?></p><?php endif; ?>
    <div class="attendance-staff-columns">
        <div class="attendance-staff-column">
            <h3 class="attendance-subhead">Weekly shift</h3>
            <p class="muted attendance-hint">Current: <?= e(roster_shift_summary($selectedStaff) ?: 'no shift recorded yet') ?></p>
            <form method="post" class="attendance-shift-form" data-shift-form>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_staff">
                <input type="hidden" name="staff_id" value="<?= (int) $selectedStaff['id'] ?>">
                <fieldset class="attendance-weekdays"<?= $canManage ? '' : ' disabled' ?>>
                    <legend>Working days</legend>
                    <div class="attendance-weekday-row">
                        <?php $selectedDays = roster_shift_days((string) ($selectedStaff['shift_days'] ?? '')); ?>
                        <?php foreach (ROSTER_WEEKDAYS as $weekday): ?>
                            <label class="attendance-weekday"><input type="checkbox" name="shift_days[]" value="<?= e($weekday) ?>"<?= in_array($weekday, $selectedDays, true) ? ' checked' : '' ?>><span><?= e($weekday) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($canManage): ?><div class="attendance-weekday-presets"><button type="button" class="table-link" data-shift-preset="Mon,Tue,Wed,Thu,Fri">Mon-Fri</button><button type="button" class="table-link" data-shift-preset="Mon,Tue,Wed,Thu,Fri,Sat">Mon-Sat</button><button type="button" class="table-link" data-shift-preset="">Clear</button></div><?php endif; ?>
                </fieldset>
                <div class="attendance-form-row">
                    <label>Shift start<input type="time" name="shift_start" value="<?= e(roster_time_input($selectedStaff['shift_start'] ?? null)) ?>"<?= $canManage ? '' : ' disabled' ?>></label>
                    <label>Shift end<input type="time" name="shift_end" value="<?= e(roster_time_input($selectedStaff['shift_end'] ?? null)) ?>"<?= $canManage ? '' : ' disabled' ?>></label>
                    <label>Break (minutes)<input type="number" name="break_minutes" min="0" max="480" step="5" value="<?= (int) ($selectedStaff['break_minutes'] ?? 60) ?>"<?= $canManage ? '' : ' disabled' ?>></label>
                </div>
                <div class="attendance-form-row">
                    <label>Department<select name="department_id"<?= $canManage ? '' : ' disabled' ?>><option value="0">Not assigned</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"<?= (int) ($selectedStaff['department_id'] ?? 0) === (int) $department['id'] ? ' selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></label>
                    <label>Team leader<select name="team_leader_id"<?= $canManage ? '' : ' disabled' ?>><option value="0">Not assigned</option><?php foreach ($leaderOptions as $leader): ?><option value="<?= (int) $leader['id'] ?>"<?= (int) ($selectedStaff['team_leader_id'] ?? 0) === (int) $leader['id'] ? ' selected' : '' ?>><?= e($leader['full_name']) ?><?= (string) $leader['team_label'] === 'TL' ? ' (TL)' : '' ?></option><?php endforeach; ?></select></label>
                    <label>Employment status<select name="employment_status" data-employment-status<?= $canManage ? '' : ' disabled' ?>><?php foreach (ROSTER_STATUSES as $statusKey => $statusLabel): ?><option value="<?= e($statusKey) ?>"<?= (string) $selectedStaff['employment_status'] === $statusKey ? ' selected' : '' ?>><?= e($statusLabel) ?></option><?php endforeach; ?></select></label>
                </div>
                <div class="attendance-form-row" data-status-extra<?= (string) $selectedStaff['employment_status'] === 'active' ? ' hidden' : '' ?>>
                    <label>Effective date<input type="date" name="status_effective_date" value="<?= e((string) ($selectedStaff['status_effective_date'] ?? '')) ?>"<?= $canManage ? '' : ' disabled' ?>></label>
                    <label class="is-wide">Status note<input name="status_note" maxlength="190" value="<?= e((string) ($selectedStaff['status_note'] ?? '')) ?>" placeholder="Reason or replacement note"<?= $canManage ? '' : ' disabled' ?>></label>
                    <label>Replacement for<select name="replacement_for_id"<?= $canManage ? '' : ' disabled' ?>><option value="0">Not a replacement</option><?php foreach (roster_replacement_candidates((int) $selectedStaff['id']) as $candidate): ?><option value="<?= (int) $candidate['id'] ?>"<?= (int) ($selectedStaff['replacement_for_id'] ?? 0) === (int) $candidate['id'] ? ' selected' : '' ?>><?= e($candidate['full_name']) ?> &middot; <?= e((string) $candidate['team_label']) ?></option><?php endforeach; ?></select></label>
                </div>
                <?php if ((int) ($selectedStaff['replacement_for_id'] ?? 0) > 0 && $selectedStaff['replacement_for_name'] !== null): ?>
                    <p class="attendance-replacement-note">Replacing <strong><?= e((string) $selectedStaff['replacement_for_name']) ?></strong>.</p>
                <?php endif; ?>
                <?php if ($canManage): ?><div class="attendance-form-actions"><button class="button" type="submit">Save shift and status</button></div><?php endif; ?>
            </form>
        </div>
        <div class="attendance-staff-column">
            <h3 class="attendance-subhead">Leave</h3>
            <?php /* Leave is filed and edited on the Team Calendar, which owns the record.
                     This column only reports what is already there, so the two screens can
                     never hold competing copies of the same leave. */ ?>
            <p class="muted attendance-hint"><?= $upcomingLeave ? count($upcomingLeave) . ' upcoming leave ' . (count($upcomingLeave) === 1 ? 'entry' : 'entries') : 'No upcoming leave recorded.' ?></p>
            <?php if ($upcomingLeave): ?>
                <ul class="attendance-leave-summary">
                    <?php foreach (array_slice($upcomingLeave, 0, 4) as $leaveRow):
                        $leaveStart = (string) $leaveRow['event_date'];
                        $leaveEnd = (string) ($leaveRow['end_date'] ?: $leaveRow['event_date']); ?>
                        <li>
                            <span class="pill pill-leave"><?= e(roster_leave_type_label((string) $leaveRow['leave_type'])) ?></span>
                            <span><?= e(date('M j, Y', strtotime($leaveStart))) ?><?= $leaveEnd !== $leaveStart ? ' &ndash; ' . e(date('M j, Y', strtotime($leaveEnd))) : '' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (count($upcomingLeave) > 4): ?><p class="muted attendance-hint">and <?= count($upcomingLeave) - 4 ?> more on the calendar.</p><?php endif; ?>
            <?php endif; ?>
            <p class="muted attendance-hint">Leave is added, changed, and removed on the Team Calendar.</p>
            <div class="attendance-form-actions"><a class="button button-secondary" href="team-calendar.php?month=<?= e(date('Y-m', strtotime($selectedDate))) ?>&amp;staff=<?= (int) $selectedStaff['id'] ?>">Manage leave on the Team Calendar</a></div>
        </div>
    </div>
</div>
</div>
<script>
(function () {
    var modal = document.getElementById('attendance-staff-modal');
    if (!modal) return;
    // Close back to the filtered roster. The X is a plain link so the modal works
    // without JS; ESC and a backdrop click are progressive enhancements.
    var closeHref = modal.querySelector('[data-attendance-modal-close]').getAttribute('href');
    function closeModal() { window.location.href = closeHref; }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeModal();
    });
    modal.addEventListener('mousedown', function (event) {
        if (event.target === modal) closeModal();
    });
    // Focus the first control so keyboard users land inside the dialog.
    var firstField = modal.querySelector('input:not([type=hidden]), select, button');
    if (firstField) firstField.focus({ preventScroll: true });
})();
</script>
<?php else: ?>
<p class="muted attendance-empty-note">Select a staff member above to edit their shift and status.</p>
<?php endif; ?>

<section class="attendance-summary-panel">
    <div class="attendance-metrics">
        <div><span>Total headcount</span><strong><?= (int) $headcountCount ?></strong></div>
        <div><span>Staff on leave</span><strong><?= (int) $onLeaveCount ?></strong></div>
    </div>
    <form method="get" class="attendance-date-filter"><label for="attendance-view-date">Viewing date</label><div class="attendance-date-actions"><input id="attendance-view-date" type="date" name="date" required value="<?= e($selectedDate) ?>"><button class="button" type="submit">View</button></div><div class="attendance-day-links"><a href="?date=<?= e((new DateTimeImmutable($selectedDate))->modify('-1 day')->format('Y-m-d')) ?>">Previous day</a><a href="?date=<?= e(date('Y-m-d')) ?>">Today</a><a href="?date=<?= e((new DateTimeImmutable($selectedDate))->modify('+1 day')->format('Y-m-d')) ?>">Next day</a></div></form>
</section>

<section class="attendance-table-panel">
    <div class="section-head"><div><h2>Recent department logs</h2><p class="muted">History only. Department attendance is no longer logged from this screen; these older records are kept for reference and still appear on the Team Calendar.</p></div><span class="section-count"><?= count($recentDepartmentLogs) ?> records</span></div>
    <div class="table-wrap"><table class="ticket-table compact-table"><thead><tr><th>Date</th><th>Department</th><th>Leave type</th><th>Headcount</th><th>Staff on leave</th><th>Logged by</th></tr></thead><tbody>
    <?php foreach ($recentDepartmentLogs as $row): ?>
        <tr><td><a href="team-attendance.php?date=<?= e((string) $row['attendance_date']) ?>"><?= e((string) $row['attendance_date']) ?></a></td><td><?= e((string) $row['department']) ?></td><td><?= e($departmentAttendanceStatuses[(string) $row['status']] ?? (string) $row['status']) ?></td><td><?= (int) $row['headcount'] ?></td><td><?= (int) $row['leave_count'] ?></td><td class="muted"><?= e((string) $row['logger']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$recentDepartmentLogs): ?><tr><td colspan="6" class="empty-table-cell">No department logs on record.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php page_end();
