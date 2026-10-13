<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/roster.php';
require __DIR__ . '/app/layout.php';

$admin = require_login();
if (($admin['role_slug'] ?? '') !== 'super-admin') {
    http_response_code(403);
    exit('Super Admin access required.');
}
$pdo = db();
$recruitmentDepartments = $pdo->query('SELECT id,name,code FROM departments ORDER BY name')->fetchAll();
$recruitmentStaffOptions = roster_staff_rows();
$recruitmentLeaderOptions = array_values(array_filter($recruitmentStaffOptions, static function (array $option): bool {
    return (bool) preg_match('/\b(TL|lead)\b/i', (string) $option['team_label']);
}));
$error = '';
$notice = ($_GET['saved'] ?? '') === '1' ? 'Employee details saved.' : '';
$postedRows = is_array($_POST['staff'] ?? null) ? $_POST['staff'] : [];
$selectedStaffId = (int) ($_POST['save_id'] ?? 0);

function recruitment_name_key(string $name): string
{
    return strtolower(trim((string) preg_replace('/\s+/', ' ', $name)));
}

function recruitment_posted_string(array $row, string $key): string
{
    return isset($row[$key]) && is_scalar($row[$key]) ? trim((string) $row[$key]) : '';
}

function recruitment_valid_date(string $value): ?string
{
    if ($value === '') return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('Enter a valid hire date.');
    return $value;
}

function recruitment_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $staffId = $selectedStaffId;
        $postedRow = is_array($postedRows[$staffId] ?? null) ? $postedRows[$staffId] : [];
        if ($staffId < 1 || !$postedRow) throw new InvalidArgumentException('Select one employee row to save.');

        $staffQuery = $pdo->prepare('SELECT id,full_name FROM staff_directory WHERE id=? AND is_active=1');
        $staffQuery->execute([$staffId]);
        $staff = $staffQuery->fetch();
        if (!$staff) throw new InvalidArgumentException('Employee not found. Refresh the page and try again.');

        $hiredDate = recruitment_valid_date(recruitment_posted_string($postedRow, 'hired_date'));
        $positionTitle = recruitment_posted_string($postedRow, 'position_title');
        if (recruitment_length($positionTitle) > 150) throw new InvalidArgumentException('Position must be 150 characters or fewer.');
        $inTraining = isset($postedRow['is_in_training']) && (string) $postedRow['is_in_training'] === '1' ? 1 : 0;
        $departmentId = (int) recruitment_posted_string($postedRow, 'department_id');
        $teamLeaderId = (int) recruitment_posted_string($postedRow, 'team_leader_id');
        $departmentIds = array_map('intval', array_column($recruitmentDepartments, 'id'));
        $rosterIds = array_map('intval', array_column($recruitmentStaffOptions, 'id'));
        if ($departmentId > 0 && !in_array($departmentId, $departmentIds, true)) throw new InvalidArgumentException('Choose a valid department.');
        if ($teamLeaderId > 0 && !in_array($teamLeaderId, $rosterIds, true)) throw new InvalidArgumentException('Choose a valid team leader.');

        // Employment status, shift and replacement fields belong to Team Attendance; an omitted
        // column in an UPDATE keeps its stored value, so those records are left untouched here.
        $pdo->prepare('UPDATE staff_directory SET hired_date=?,is_in_training=?,position_title=?,
            department_id=?,team_leader_id=? WHERE id=?')
            ->execute([
                $hiredDate,
                $inTraining,
                $positionTitle !== '' ? $positionTitle : null,
                $departmentId > 0 ? $departmentId : null,
                $teamLeaderId > 0 ? $teamLeaderId : null,
                $staffId,
            ]);
        audit_admin_action((int) $admin['id'], 'recruitment_details_updated', 'staff_directory', $staffId, [
            'staff_name' => $staff['full_name'],
            'fields' => ['hired_date', 'is_in_training', 'position_title', 'department_id', 'team_leader_id'],
        ]);
        redirect('recruitment.php?saved=1');
    } catch (Throwable $exception) {
        $error = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'The recruitment details could not be saved.';
    }
}

$staffRows = [];
$directoryError = '';
try {
    // shift_schedule, employment_status, status_effective_date, status_note and replacement_for_id
    // are read-only here: Team Attendance is their single editor.
    $staffRows = $pdo->query('SELECT id,full_name,team_label,email_domain,hired_date,shift_schedule,is_in_training,position_title,
        department_id,team_leader_id,employment_status,status_effective_date,status_note,replacement_for_id
        FROM staff_directory WHERE is_active=1 ORDER BY team_label,full_name')->fetchAll();
    $accounts = $pdo->query('SELECT full_name,email,username,is_active FROM users ORDER BY id')->fetchAll();
    $accountsByName = [];
    foreach ($accounts as $account) {
        $accountsByName[recruitment_name_key((string) $account['full_name'])] = $account;
    }
    $recruitmentStaffById = [];
    foreach ($recruitmentStaffOptions as $option) {
        $recruitmentStaffById[(int) $option['id']] = (string) $option['full_name'];
    }
    foreach ($staffRows as &$staffRow) {
        $staffRow['account'] = $accountsByName[recruitment_name_key((string) $staffRow['full_name'])] ?? null;
    }
    unset($staffRow);
} catch (Throwable) {
    $directoryError = 'The recruitment directory is unavailable. Apply migrations/022_staff_leave_attendance_updates.sql and 024_recruitment_directory.sql first.';
}

page_start('Recruitment', $admin); ?>
<div class="page-actions"><?php if ($staffRows): ?><span class="section-count"><?= count($staffRows) ?> team members</span><?php endif; ?></div>
<?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?><?php if ($notice): ?><p class="notice"><?= e($notice) ?></p><?php endif; ?>
<?php if ($directoryError): ?>
    <section><h2>Recruitment directory</h2><p class="error"><?= e($directoryError) ?></p></section>
<?php else: ?>
    <section class="recruitment-panel">
        <div class="section-head"><div><h2>Team member details</h2><p>Update hiring details here. Employment status and shift are managed in Team Attendance.</p></div></div>
        <div class="recruitment-card-grid">
            <?php foreach ($staffRows as $staff): ?>
                <?php
                $staffId = (int) $staff['id'];
                $postedRow = $staffId === $selectedStaffId && is_array($postedRows[$staffId] ?? null) ? $postedRows[$staffId] : null;
                $positionTitle = $postedRow ? recruitment_posted_string($postedRow, 'position_title') : (string) ($staff['position_title'] ?? '');
                $hiredDate = $postedRow ? recruitment_posted_string($postedRow, 'hired_date') : (string) ($staff['hired_date'] ?? '');
                $inTraining = $postedRow ? isset($postedRow['is_in_training']) && (string) $postedRow['is_in_training'] === '1' : (bool) $staff['is_in_training'];
                $departmentId = $postedRow ? (int) recruitment_posted_string($postedRow, 'department_id') : (int) ($staff['department_id'] ?? 0);
                $teamLeaderId = $postedRow ? (int) recruitment_posted_string($postedRow, 'team_leader_id') : (int) ($staff['team_leader_id'] ?? 0);
                // Read-only Team Attendance values: never taken from the post, they are not this form's to change.
                $employmentStatus = (string) ($staff['employment_status'] ?? 'active');
                $shiftSchedule = (string) ($staff['shift_schedule'] ?? '');
                $statusEffectiveDate = (string) ($staff['status_effective_date'] ?? '');
                $statusNote = (string) ($staff['status_note'] ?? '');
                $replacementForId = (int) ($staff['replacement_for_id'] ?? 0);
                $replacementForName = $recruitmentStaffById[$replacementForId] ?? '';
                $statusLabel = roster_status_label($employmentStatus);
                $statusSummary = array_filter([
                    $statusEffectiveDate !== '' ? 'Effective ' . date('M j, Y', strtotime($statusEffectiveDate)) : '',
                    $statusNote,
                    $replacementForName !== '' ? 'Replacing ' . $replacementForName : '',
                ], static fn(string $part): bool => $part !== '');
                $account = $staff['account'] ?? null;
                $leaderChoices = $recruitmentLeaderOptions;
                if ($teamLeaderId > 0 && !in_array($teamLeaderId, array_map('intval', array_column($leaderChoices, 'id')), true)) {
                    foreach ($recruitmentStaffOptions as $option) {
                        if ((int) $option['id'] === $teamLeaderId) { $leaderChoices[] = $option; break; }
                    }
                }
                ?>
                <details class="recruitment-card"<?= $staffId === $selectedStaffId && $error ? ' open' : '' ?>>
                    <summary aria-label="<?= e('Edit details for ' . $staff['full_name']) ?>"><span class="recruitment-card-name"><?= e($staff['full_name']) ?></span><span class="pill pill-<?= $employmentStatus === 'active' ? 'closed' : 'pending' ?> recruitment-status-pill"><?= e($statusLabel) ?></span><span class="recruitment-edit-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m14.5 5.5 4 4M5 19l3.75-.75L19.75 7.25a2.12 2.12 0 0 0-3-3L5.75 15.25z"/></svg></span></summary>
                    <form method="post" class="recruitment-card-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <div class="recruitment-card-context"><span><?= e($staff['team_label']) ?></span><span><?= e($account['email'] ?? ('@' . ($staff['email_domain'] ?? ''))) ?></span></div>
                        <div class="recruitment-detail-grid">
                            <label>Position<input name="staff[<?= $staffId ?>][position_title]" value="<?= e($positionTitle) ?>" maxlength="150" aria-label="<?= e('Position for ' . $staff['full_name']) ?>" placeholder="Position"></label>
                            <label>Hire date<input type="date" name="staff[<?= $staffId ?>][hired_date]" value="<?= e($hiredDate) ?>" aria-label="<?= e('Hire date for ' . $staff['full_name']) ?>"></label>
                            <label>Department<select name="staff[<?= $staffId ?>][department_id]" aria-label="<?= e('Department for ' . $staff['full_name']) ?>"><option value="0">Not assigned</option><?php foreach ($recruitmentDepartments as $department): ?><option value="<?= (int) $department['id'] ?>"<?= $departmentId === (int) $department['id'] ? ' selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></label>
                            <label>Team leader<select name="staff[<?= $staffId ?>][team_leader_id]" aria-label="<?= e('Team leader for ' . $staff['full_name']) ?>"><option value="0">Not assigned</option><?php foreach ($leaderChoices as $choice): ?><option value="<?= (int) $choice['id'] ?>"<?= $teamLeaderId === (int) $choice['id'] ? ' selected' : '' ?>><?= e($choice['full_name']) ?> &middot; <?= e((string) $choice['team_label']) ?></option><?php endforeach; ?></select></label>
                            <div class="is-wide attendance-field-label">Employment status <span class="pill pill-<?= $employmentStatus === 'active' ? 'closed' : 'pending' ?>"><?= e($statusLabel) ?></span> <a href="<?= e('team-attendance.php?staff=' . $staffId) ?>">Change in Team Attendance</a><?php if ($statusSummary): ?> <span class="muted"><?= e(implode(' · ', $statusSummary)) ?></span><?php endif; ?></div>
                            <div class="is-wide attendance-field-label">Shift schedule <span class="muted"><?= $shiftSchedule !== '' ? e($shiftSchedule) : 'No shift schedule recorded.' ?></span> <a href="<?= e('team-attendance.php?staff=' . $staffId) ?>">Change in Team Attendance</a></div>
                            <label class="recruitment-training-field"><input type="checkbox" name="staff[<?= $staffId ?>][is_in_training]" value="1" <?= $inTraining ? 'checked' : '' ?> aria-label="<?= e('In training for ' . $staff['full_name']) ?>"> In training</label>
                        </div>
                        <div class="recruitment-card-actions"><button class="button" type="submit" name="save_id" value="<?= $staffId ?>" data-processing="Saving...">Save details</button></div>
                    </form>
                </details>
            <?php endforeach; ?>
            <?php if (!$staffRows): ?><p class="muted recruitment-empty">No active team members found.</p><?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php page_end();
