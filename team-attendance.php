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
$staffFormPost = null;

/* -------------------------------------------------------------------------
 * Staff roster details, shift and employment status
 * ---------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_staff') {
    verify_csrf();
    if (!$canManage) { http_response_code(403); exit('You do not have permission to manage attendance.'); }
    $staffFormPost = $_POST;
    $staffId = (int) request_string($_POST, 'staff_id');
    $staff = roster_staff_load($staffId);
    if (!$staff) {
        $error = 'Choose a staff member from the roster.';
    } else {
        $teamLabel = trim((string) request_string($_POST, 'team_label'));
        $departmentId = (int) request_string($_POST, 'department_id');
        $departmentIds = array_map('intval', array_column(active_departments(), 'id'));
        $hiredDate = trim((string) request_string($_POST, 'hired_date'));
        $hiredDateValue = $hiredDate === '' ? null : DateTimeImmutable::createFromFormat('!Y-m-d', $hiredDate);
        $teamLeaderName = trim((string) request_string($_POST, 'team_leader_name'));
        $shiftLabel = trim((string) request_string($_POST, 'shift_label'));
        $shiftDays = roster_shift_days_from_day_off_post($_POST);
        $shiftStartRaw = trim((string) request_string($_POST, 'shift_start'));
        $shiftEndRaw = trim((string) request_string($_POST, 'shift_end'));
        $shiftStart = roster_time_value($_POST, 'shift_start');
        $shiftEnd = roster_time_value($_POST, 'shift_end');
        $dstStartRaw = trim((string) request_string($_POST, 'shift_dst_start'));
        $dstEndRaw = trim((string) request_string($_POST, 'shift_dst_end'));
        $dstStart = roster_time_value($_POST, 'shift_dst_start');
        $dstEnd = roster_time_value($_POST, 'shift_dst_end');
        $breakRaw = trim((string) request_string($_POST, 'break_minutes'));
        $status = trim((string) request_string($_POST, 'employment_status'));
        $trainingRaw = trim((string) request_string($_POST, 'is_in_training'));
        $effective = trim((string) request_string($_POST, 'status_effective_date'));
        $statusNote = trim((string) request_string($_POST, 'status_note'));
        $replacementForId = (int) request_string($_POST, 'replacement_for_id');
        $staffIds = array_map('intval', array_column(roster_staff_rows(), 'id'));
        $effectiveValue = DateTimeImmutable::createFromFormat('!Y-m-d', $effective);

        if ($teamLabel === '') {
            $error = 'Enter the staff member’s CNG role.';
        } elseif (mb_strlen($teamLabel) > 120) {
            $error = 'Keep the CNG role under 120 characters.';
        } elseif ($departmentId > 0 && !in_array($departmentId, $departmentIds, true)) {
            $error = 'Choose a valid CNG department.';
        } elseif ($hiredDate !== '' && (!$hiredDateValue || $hiredDateValue->format('Y-m-d') !== $hiredDate)) {
            $error = 'Enter a valid hire date.';
        } elseif (mb_strlen($teamLeaderName) > 150) {
            $error = 'Keep the Team Leader name under 150 characters.';
        } elseif (mb_strlen($shiftLabel) > 30) {
            $error = 'Keep the shift label under 30 characters.';
        } elseif (!in_array($trainingRaw, ['0', '1'], true)) {
            $error = 'Choose a valid training status.';
        } elseif (($shiftStartRaw === '') !== ($shiftEndRaw === '')) {
            $error = 'Enter both a shift start and a shift end time, or leave both empty.';
        } elseif ($shiftStartRaw !== '' && ($shiftStart === null || $shiftEnd === null)) {
            $error = 'Enter valid standard shift times.';
        } elseif ($shiftStart !== null && $shiftEnd !== null && $shiftStart === $shiftEnd) {
            $error = 'Standard shift start and end times cannot be the same.';
        } elseif (($dstStartRaw === '') !== ($dstEndRaw === '')) {
            $error = 'Enter both DST shift times, or leave both empty.';
        } elseif ($dstStartRaw !== '' && ($dstStart === null || $dstEnd === null)) {
            $error = 'Enter valid DST shift times.';
        } elseif ($dstStart !== null && $dstEnd !== null && $dstStart === $dstEnd) {
            $error = 'DST shift start and end times cannot be the same.';
        } elseif (!isset(ROSTER_STATUSES[$status])) {
            $error = 'Choose a valid employment status.';
        } elseif ($effective !== '' && (!$effectiveValue || $effectiveValue->format('Y-m-d') !== $effective)) {
            $error = 'Enter a valid effective date.';
        } elseif ($breakRaw !== '' && !ctype_digit($breakRaw)) {
            $error = 'Break minutes must be a whole number.';
        } elseif ((int) ($breakRaw === '' ? 60 : $breakRaw) > 480) {
            $error = 'Break minutes must be 480 or fewer.';
        } elseif ($replacementForId === $staffId && $staffId > 0) {
            $error = 'A staff member cannot be their own replacement.';
        } elseif ($replacementForId > 0 && !in_array($replacementForId, $staffIds, true)) {
            $error = 'Choose a valid staff member to replace.';
        } elseif (mb_strlen($statusNote) > 190) {
            $error = 'Keep the status note under 190 characters.';
        } else {
            $breakMinutes = (int) ($breakRaw === '' ? 60 : $breakRaw);
            $leaderQuery = $pdo->prepare('SELECT id FROM staff_directory WHERE full_name = ? LIMIT 2');
            $leaderQuery->execute([$teamLeaderName]);
            $leaderMatches = $teamLeaderName !== '' ? $leaderQuery->fetchAll(PDO::FETCH_COLUMN) : [];
            $teamLeaderId = count($leaderMatches) === 1 ? (int) $leaderMatches[0] : null;
            try {
                if (!app_schema_has('staff_directory', 'team_leader_name') && $teamLeaderName !== '' && $teamLeaderId === null) throw new InvalidArgumentException('Choose a Team Leader whose full name is already in the roster.');
                foreach (['shift_label'=>$shiftLabel, 'shift_dst_start'=>$dstStartRaw, 'shift_dst_end'=>$dstEndRaw] as $column=>$value) {
                    if ($value !== '' && !app_schema_has('staff_directory', $column)) throw new InvalidArgumentException('Use the standard shift schedule for this staff member.');
                }
                $pdo->beginTransaction();
                $beforeQuery = $pdo->prepare('SELECT * FROM staff_directory WHERE id=? FOR UPDATE');
                $beforeQuery->execute([$staffId]);
                $beforeRoster = $beforeQuery->fetch();
            $rosterValues = [
                'team_label'=>$teamLabel, 'department_id'=>$departmentId > 0 ? $departmentId : null,
                'hired_date'=>$hiredDate !== '' ? $hiredDate : null, 'is_in_training'=>(int)$trainingRaw,
                'team_leader_name'=>$teamLeaderName, 'team_leader_id'=>$teamLeaderId,
                'shift_label'=>$shiftLabel !== '' ? $shiftLabel : null, 'shift_days'=>$shiftDays,
                'shift_start'=>$shiftStart, 'shift_end'=>$shiftEnd, 'shift_dst_start'=>$dstStart,
                'shift_dst_end'=>$dstEnd, 'break_minutes'=>$breakMinutes,
                'shift_schedule'=>roster_shift_schedule_string($shiftDays, $shiftStart, $shiftEnd, $shiftLabel),
                'employment_status'=>$status, 'status_effective_date'=>$effective !== '' ? $effective : null,
                'status_note'=>$statusNote !== '' ? $statusNote : null,
                'replacement_for_id'=>$replacementForId > 0 ? $replacementForId : null,
            ];
            $rosterValues = array_intersect_key($rosterValues, app_schema_columns('staff_directory', $pdo));
            $set = implode(',', array_map(static fn(string $column): string => $column . '=?', array_keys($rosterValues)));
            $pdo->prepare('UPDATE staff_directory SET ' . $set . ' WHERE id=?')->execute(array_merge(array_values($rosterValues), [$staffId]));
            $afterQuery = $pdo->prepare('SELECT * FROM staff_directory WHERE id=?');
            $afterQuery->execute([$staffId]);
            audit_admin_action((int) $user['id'], 'staff_roster_updated', 'staff_directory', $staffId, ['before'=>$beforeRoster,'after'=>$afterQuery->fetch()]);
            $pdo->commit();
            redirect('team-attendance.php?staff=' . $staffId . '&notice=staff_saved');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'The roster changes could not be saved.';
            }
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
$activeStaffRows = array_values(array_filter(
    $staffRows,
    static fn(array $row): bool => (string) $row['employment_status'] === 'active'
));
$inactiveStaffRows = array_values(array_filter(
    $staffRows,
    static fn(array $row): bool => (string) $row['employment_status'] !== 'active'
));

$selectedStaffId = (int) request_string($_GET, 'staff');
if ($selectedStaffId < 1 && is_array($staffFormPost)) $selectedStaffId = (int) request_string($staffFormPost, 'staff_id');
$selectedStaff = roster_staff_load($selectedStaffId);
if ($selectedStaffId > 0 && !$selectedStaff) $selectedStaffId = 0;
$staffEditor = $selectedStaff;
$postedDayOff = null;
if ($staffEditor) $staffEditor['team_leader_name'] = (string) ($staffEditor['team_leader_display'] ?? '');
if ($staffEditor && is_array($staffFormPost) && (int) request_string($staffFormPost, 'staff_id') === $selectedStaffId) {
    foreach (['team_label', 'department_id', 'hired_date', 'team_leader_name', 'shift_label', 'shift_start', 'shift_end', 'shift_dst_start', 'shift_dst_end', 'break_minutes', 'employment_status', 'is_in_training', 'status_effective_date', 'status_note', 'replacement_for_id'] as $field) {
        if (isset($staffFormPost[$field]) && is_scalar($staffFormPost[$field])) $staffEditor[$field] = trim((string) $staffFormPost[$field]);
    }
    $postedDayOff = roster_day_off_days_from_post($staffFormPost);
}

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

page_start('Staff Directory', $user);
?>
<div class="attendance-screen">
<?php /* While the modal is open it covers the page, so its own save notice is shown
         inside the modal instead - otherwise the confirmation would be hidden behind
         the overlay. */ ?>
<?php if (!$selectedStaff): ?>
<?php if ($notice): ?><p class="action-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="auth-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php endif; ?>

<section class="attendance-roster-panel" aria-label="Staff directory">
    <form method="get" class="attendance-filter-bar" aria-label="Filter the staff list">
        <label class="attendance-filter-field"><span>Search</span><input type="search" name="search" value="<?= e($filterSearch) ?>" placeholder="Name, team, or department" autocomplete="off"></label>
        <label class="attendance-filter-field"><span>Department</span><select name="department"><option value="">All departments</option><option value="none"<?= $filterUnassigned ? ' selected' : '' ?>>Not assigned yet</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"<?= $filterDepartment === (int) $department['id'] ? ' selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></label>
        <label class="attendance-filter-field"><span>Team</span><select name="team"><option value="">All teams</option><?php foreach (roster_team_labels() as $teamLabel): ?><option value="<?= e((string) $teamLabel) ?>"<?= $filterTeam === (string) $teamLabel ? ' selected' : '' ?>><?= e((string) $teamLabel) ?></option><?php endforeach; ?></select></label>
        <label class="attendance-filter-field"><span>Status</span><select name="status"><option value="">All statuses</option><?php foreach (ROSTER_STATUSES as $statusKey => $statusLabel): ?><option value="<?= e($statusKey) ?>"<?= $filterStatus === $statusKey ? ' selected' : '' ?>><?= e($statusLabel) ?></option><?php endforeach; ?></select></label>
        <button class="button" type="submit">Apply</button>
        <a class="button button-secondary" href="team-attendance.php">Reset</a>
    </form>
    <div class="attendance-roster-columns">
        <?php foreach ([['id' => 'active', 'title' => 'Active', 'rows' => $activeStaffRows], ['id' => 'inactive', 'title' => 'Inactive', 'rows' => $inactiveStaffRows]] as $group): ?>
        <section class="attendance-roster-column" aria-labelledby="attendance-<?= e($group['id']) ?>-heading">
            <h2 class="attendance-roster-heading" id="attendance-<?= e($group['id']) ?>-heading"><?= e($group['title']) ?><span><?= count($group['rows']) ?></span></h2>
            <div class="attendance-roster-list" role="list">
                <?php foreach ($group['rows'] as $row): $rowId = (int) $row['id']; $isSelected = $rowId === $selectedStaffId; ?>
                    <a class="attendance-roster-card<?= $isSelected ? ' is-selected' : '' ?><?= $group['id'] === 'inactive' ? ' is-inactive' : '' ?>" role="listitem" href="<?= e($rosterListQuery(['staff' => $rowId])) ?>">
                        <span class="attendance-roster-name"><?= e($row['full_name']) ?></span>
                        <span class="attendance-roster-meta"><?= e($row['team_label']) ?><?= $row['department'] ? ' &middot; ' . e((string) $row['department']) : '' ?></span>
                        <span class="attendance-roster-shift<?= roster_shift_summary($row) === '' ? ' is-missing' : '' ?>"><?= e(roster_shift_summary($row) ?: 'No shift set') ?></span>
                        <span class="attendance-roster-tags">
                            <span class="pill pill-<?= (string) $row['employment_status'] === 'active' ? 'closed' : 'pending' ?>"><?= e(roster_status_label((string) $row['employment_status'])) ?></span>
                            <?php if ((int) ($row['linked_account_count'] ?? 0) > 0 && (int) ($row['linked_account_active'] ?? 0) === 0): ?><span class="pill pill-pending">Login disabled</span><?php endif; ?>
                            <?php if (isset($leaveTodayByStaff[$rowId])): ?><span class="pill pill-leave">On leave today &middot; <?= e($leaveTodayByStaff[$rowId]) ?></span><?php elseif ((int) ($row['upcoming_leave'] ?? 0) > 0): ?><span class="pill pill-pending"><?= (int) $row['upcoming_leave'] ?> upcoming</span><?php endif; ?>
                            <?php if ($row['team_leader_display']): ?><span class="attendance-roster-leader">TL: <?= e((string) $row['team_leader_display']) ?></span><?php endif; ?>
                        </span>
                    </a>
                <?php endforeach; ?>
                <?php if (!$group['rows']): ?><p class="muted attendance-empty-note">No <?= strtolower(e($group['title'])) ?> staff match these filters.</p><?php endif; ?>
            </div>
        </section>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($selectedStaff): ?>
<?php /* The staff card is a modal so choosing someone never requires scrolling to
         the bottom of the roster to reach the form. It is rendered open by default
         and closes back to the filtered list via the X, ESC, or a backdrop click. */ ?>
<div class="calendar-modal attendance-modal" id="attendance-staff-modal" role="dialog" aria-modal="true" aria-labelledby="attendance-staff-title">
<div class="calendar-modal-panel attendance-modal-panel">
    <div class="section-head">
        <div><h2 id="attendance-staff-title"><?= e($selectedStaff['full_name']) ?></h2><p class="muted"><?= e($selectedStaff['team_label']) ?><?= $selectedStaff['position_title'] ? ' &middot; ' . e((string) $selectedStaff['position_title']) : '' ?></p></div>
        <div class="attendance-modal-head-actions">
            <span class="section-count"><?= e(roster_status_label((string) $selectedStaff['employment_status'])) ?><?= $selectedStaff['hired_date'] ? ' &middot; since ' . e(date('M j, Y', strtotime((string) $selectedStaff['hired_date']))) : '' ?></span>
            <a class="attendance-modal-close" href="<?= e($rosterListQuery()) ?>" aria-label="Close staff details" data-attendance-modal-close>&times;</a>
        </div>
    </div>
    <?php if ($notice): ?><p class="action-notice attendance-modal-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="auth-error attendance-modal-notice" role="alert"><?= e($error) ?></p><?php endif; ?>
    <div class="attendance-staff-columns">
        <div class="attendance-staff-column">
            <h3 class="attendance-subhead">Team member details</h3>
            <p class="muted attendance-hint">Edit the CNG role, reporting lead, employment status, and roster schedule for this staff member.</p>
            <form method="post" class="attendance-shift-form" data-shift-form>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_staff">
                <input type="hidden" name="staff_id" value="<?= (int) $selectedStaff['id'] ?>">
                <div class="attendance-form-row">
                    <label class="is-wide">CNG role<input type="text" name="team_label" value="<?= e((string) ($staffEditor['team_label'] ?? '')) ?>" maxlength="120" required<?= $canManage ? '' : ' disabled' ?>></label>
                    <label>Hire date<input type="date" name="hired_date" value="<?= e((string) ($staffEditor['hired_date'] ?? '')) ?>"<?= $canManage ? '' : ' disabled' ?>></label>
                    <label>Team Leader<input type="text" name="team_leader_name" value="<?= e((string) ($staffEditor['team_leader_name'] ?? '')) ?>" maxlength="150" placeholder="Enter team leader name"<?= $canManage ? '' : ' disabled' ?>></label>
                    <label>CNG department<select name="department_id"<?= $canManage ? '' : ' disabled' ?>><option value="0">Not assigned</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"<?= (int) ($staffEditor['department_id'] ?? 0) === (int) $department['id'] ? ' selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></label>
                    <label>Training status<select name="is_in_training" required<?= $canManage ? '' : ' disabled' ?>><option value="0"<?= (string) ($staffEditor['is_in_training'] ?? '0') === '0' ? ' selected' : '' ?>>Not in training</option><option value="1"<?= (string) ($staffEditor['is_in_training'] ?? '0') === '1' ? ' selected' : '' ?>>In training</option></select></label>
                    <label>Employment status<select name="employment_status" data-employment-status required<?= $canManage ? '' : ' disabled' ?>><?php foreach (ROSTER_STATUSES as $statusKey => $statusLabel): ?><option value="<?= e($statusKey) ?>"<?= (string) ($staffEditor['employment_status'] ?? 'active') === $statusKey ? ' selected' : '' ?>><?= e($statusLabel) ?></option><?php endforeach; ?></select></label>
                </div>
                <h4 class="attendance-schedule-heading">Schedule</h4>
                <div class="attendance-form-row">
                    <?php if (app_schema_has('staff_directory', 'shift_label')): ?><label>Shift label<input type="text" name="shift_label" value="<?= e((string) ($staffEditor['shift_label'] ?? '')) ?>" maxlength="30" placeholder="AM, MID, GY"<?= $canManage ? '' : ' disabled' ?>></label><?php endif; ?>
                    <label>Standard shift start<input type="time" name="shift_start" value="<?= e(roster_time_input($staffEditor['shift_start'] ?? null)) ?>"<?= $canManage ? '' : ' disabled' ?>></label>
                    <label>Standard shift end<input type="time" name="shift_end" value="<?= e(roster_time_input($staffEditor['shift_end'] ?? null)) ?>"<?= $canManage ? '' : ' disabled' ?>></label>
                    <?php if (app_schema_has('staff_directory', 'shift_dst_start')): ?><label>DST shift start<input type="time" name="shift_dst_start" value="<?= e(roster_time_input($staffEditor['shift_dst_start'] ?? null)) ?>"<?= $canManage ? '' : ' disabled' ?>></label><?php endif; ?>
                    <?php if (app_schema_has('staff_directory', 'shift_dst_end')): ?><label>DST shift end<input type="time" name="shift_dst_end" value="<?= e(roster_time_input($staffEditor['shift_dst_end'] ?? null)) ?>"<?= $canManage ? '' : ' disabled' ?>></label><?php endif; ?>
                    <label>Break (minutes)<input type="number" name="break_minutes" min="0" max="480" step="5" value="<?= e((string) ($staffEditor['break_minutes'] ?? 60)) ?>"<?= $canManage ? '' : ' disabled' ?>></label>
                </div>
                <fieldset class="attendance-weekdays"<?= $canManage ? '' : ' disabled' ?>>
                    <legend>Days off</legend>
                    <div class="attendance-weekday-row">
                        <?php $selectedDaysOff = $postedDayOff ?? roster_day_off_days((string) ($selectedStaff['shift_days'] ?? '')); ?>
                        <?php foreach (ROSTER_WEEKDAYS as $weekday): ?>
                            <label class="attendance-weekday"><input type="checkbox" name="days_off[]" value="<?= e($weekday) ?>"<?= in_array($weekday, $selectedDaysOff, true) ? ' checked' : '' ?>><span><?= e($weekday) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($canManage): ?><div class="attendance-weekday-presets"><button type="button" class="table-link" data-day-off-preset="Sat,Sun">Mon-Fri work week</button><button type="button" class="table-link" data-day-off-preset="Sun">Mon-Sat work week</button><button type="button" class="table-link" data-day-off-preset="">Clear days off</button></div><?php endif; ?>
                </fieldset>
                <div class="attendance-form-row" data-status-extra<?= (string) ($staffEditor['employment_status'] ?? 'active') === 'active' ? ' hidden' : '' ?>>
                    <label>Effective date<input type="date" name="status_effective_date" value="<?= e((string) ($staffEditor['status_effective_date'] ?? '')) ?>"<?= $canManage ? '' : ' disabled' ?>></label>
                    <label class="is-wide">Status note<input name="status_note" maxlength="190" value="<?= e((string) ($staffEditor['status_note'] ?? '')) ?>" placeholder="Reason or replacement note"<?= $canManage ? '' : ' disabled' ?>></label>
                    <label>Replacement for<select name="replacement_for_id"<?= $canManage ? '' : ' disabled' ?>><option value="0">Not a replacement</option><?php foreach (roster_replacement_candidates((int) $selectedStaff['id']) as $candidate): ?><option value="<?= (int) $candidate['id'] ?>"<?= (int) ($staffEditor['replacement_for_id'] ?? 0) === (int) $candidate['id'] ? ' selected' : '' ?>><?= e($candidate['full_name']) ?> &middot; <?= e((string) $candidate['team_label']) ?></option><?php endforeach; ?></select></label>
                </div>
                <?php if ((int) ($staffEditor['replacement_for_id'] ?? 0) > 0 && $selectedStaff['replacement_for_name'] !== null): ?>
                    <p class="attendance-replacement-note">Replacing <strong><?= e((string) $selectedStaff['replacement_for_name']) ?></strong>.</p>
                <?php endif; ?>
                <?php if ($canManage): ?><div class="attendance-form-actions"><button class="button" type="submit">Save member details</button></div><?php endif; ?>
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
