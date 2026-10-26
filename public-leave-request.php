<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/notifications.php';
require __DIR__ . '/app/roster.php';

if (!app_is_setup()) redirect('setup.php');

$pdo = db();
$publicLeaveAvailable = app_public_leave_available();
if (!$publicLeaveAvailable && $_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(409);
    exit('Please ask your Team Leader to file your leave request.');
}
$staffStatement = $pdo->prepare(
    'SELECT sd.id, sd.full_name, sd.team_label, d.name AS department
     FROM staff_directory sd
     LEFT JOIN departments d ON d.id = sd.department_id
     WHERE sd.is_active = 1 AND sd.employment_status IN (?, ?)
     ORDER BY sd.team_label, sd.full_name'
);
$staffOptions = [];
if ($publicLeaveAvailable) {
    $staffStatement->execute(ROSTER_WORKING_STATUSES);
    $staffOptions = $staffStatement->fetchAll();
}
$staffById = [];
foreach ($staffOptions as $staffOption) {
    $staffById[(int) $staffOption['id']] = $staffOption;
}

$error = '';
$submitted = isset($_GET['submitted']) && $_GET['submitted'] === '1';
$requesterEmail = '';
$staff = null;
$staffId = 0;
$startDate = '';
$endDate = '';
$leaveType = 'annual';
$reason = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $requesterEmail = trim(request_string($_POST, 'requester_email'));
    $staffId = filter_var(request_string($_POST, 'staff_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $staff = $staffById[$staffId] ?? null;
    $startDate = trim(request_string($_POST, 'start_date'));
    $endDate = trim(request_string($_POST, 'end_date'));
    $leaveType = trim(request_string($_POST, 'leave_type'));
    $reason = trim(request_string($_POST, 'reason'));
    $file = $_FILES['supporting_file'] ?? null;
    $hasFile = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
    $reasonLength = function_exists('mb_strlen') ? mb_strlen($reason, 'UTF-8') : strlen($reason);

    if (request_string($_POST, 'website') !== '') {
        http_response_code(400);
        exit('Invalid request.');
    } elseif (strlen($requesterEmail) > 254 || !filter_var($requesterEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address so the team can contact you about this request.';
    } elseif (!$staff) {
        $error = 'Select your name from the staff list. If you cannot find it, contact your Team Leader.';
    } elseif (!$start || !$end || $start->format('Y-m-d') !== $startDate || $end->format('Y-m-d') !== $endDate || $endDate < $startDate) {
        $error = 'Choose a valid leave date range.';
    } elseif (!isset(ROSTER_LEAVE_TYPES[$leaveType])) {
        $error = 'Choose a valid leave type.';
    } elseif ($reasonLength > 5000) {
        $error = 'Keep the reason under 5,000 characters.';
    } elseif ($hasFile && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_INI_SIZE
            ? 'The supporting file exceeds the server upload limit.'
            : 'Choose a supporting screenshot, photo, or PDF.';
    } elseif ($hasFile && ($file['size'] ?? 0) > 10 * 1024 * 1024) {
        $error = 'The supporting file must be 10 MB or smaller.';
    } else {
        $originalName = basename((string) ($file['name'] ?? ''));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $finfo = $hasFile ? finfo_open(FILEINFO_MIME_TYPE) : false;
        $mime = $finfo ? (finfo_file($finfo, (string) ($file['tmp_name'] ?? '')) ?: 'application/octet-stream') : 'application/octet-stream';
        if ($finfo) finfo_close($finfo);
        $allowedTypes = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
        if ($hasFile && (!isset($allowedTypes[$extension]) || $allowedTypes[$extension] !== $mime)) {
            $error = 'Only PDF, JPG, and PNG supporting files are allowed.';
        } else {
            $requestId = 0;
            $relativePath = '';
            $pdo->beginTransaction();
            try {
                $insert = $pdo->prepare(
                    'INSERT INTO leave_requests(employee_user_id, staff_id, created_by, requester_email, leave_type, start_date, end_date, reason, status)
                     VALUES(NULL, ?, NULL, ?, ?, ?, ?, ?, ?)'
                );
                $insert->execute([$staffId, $requesterEmail, $leaveType, $startDate, $endDate, $reason, 'pending']);
                $requestId = (int) $pdo->lastInsertId();

                if ($hasFile) {
                    $storageDir = ROOT_PATH . '/storage/private/leave-attachments/' . $requestId;
                    if (!is_dir($storageDir) && !mkdir($storageDir, 0770, true) && !is_dir($storageDir)) {
                        throw new RuntimeException('Unable to prepare private leave attachment storage.');
                    }
                    $relativePath = 'storage/private/leave-attachments/' . $requestId . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
                    if (!move_uploaded_file((string) $file['tmp_name'], ROOT_PATH . '/' . $relativePath)) {
                        throw new RuntimeException('Unable to store the leave attachment.');
                    }
                    $pdo->prepare(
                        'INSERT INTO leave_request_attachments(leave_request_id, uploaded_by, file_name, file_path, mime_type, file_size)
                         VALUES(?, NULL, ?, ?, ?, ?)'
                    )->execute([$requestId, $originalName, $relativePath, $mime, (int) $file['size']]);
                }

                $summary = (string) $staff['full_name'] . ' requested ' . ROSTER_LEAVE_TYPES[$leaveType] . ' leave from ' . $startDate . ' to ' . $endDate . ' through the public form.';
                notify_many(active_user_ids_by_role('team-leader'), null, 'approval', 'Public leave request needs Team Leader review', $summary, 'leave-requests.php');
                audit_admin_action(null, 'public_leave_request_created', 'leave_request', $requestId, ['staff_id' => $staffId, 'status' => 'pending', 'leave_type' => $leaveType]);
                $pdo->commit();
                redirect('leave-request.php?submitted=1');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($relativePath !== '' && is_file(ROOT_PATH . '/' . $relativePath)) unlink(ROOT_PATH . '/' . $relativePath);
                $error = 'Your leave request could not be saved. Please try again or contact your Team Leader.';
            }
        }
    }
}

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Leave Request Form</title>
    <link rel="icon" href="assets/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css?v=20261014-public-leave-r1">
    <link rel="stylesheet" href="assets/css/ui-fixes.css?v=20261014-public-leave-form-r1">
</head>
<body class="auth-page public-leave-page">
<main class="auth-shell">
    <section class="auth-intro">
        <p class="auth-eyebrow">CNG TICKETING</p>
        <h1>Request time away.</h1>
        <div class="auth-features"><span>Roster-linked request</span><span>Private supporting file</span><span>Team Leader review</span></div>
    </section>
    <section class="auth-card">
        <div class="auth-logos"><img class="auth-strata-logo" src="assets/stratastaff-logo.png" alt="Strata Staff Global"><span class="auth-logo-divider"></span><img class="auth-jamesons-logo" src="assets/jamesons-logo.svg" alt="Jamesons Strata Management"></div>
        <h1>Leave Request Form</h1>
        <?php if ($submitted): ?><p class="action-notice" role="status">Your request has been sent to the Team Leader approval queue.</p><?php endif; ?>
        <?php if ($error): ?><p class="auth-error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <?php if (!$publicLeaveAvailable): ?><p class="action-notice" role="status">Please ask your Team Leader to file your leave request.</p><a class="button button-secondary" href="login.php">Sign in</a><?php elseif (!$staffOptions): ?><p class="auth-error" role="status">The staff roster is temporarily unavailable. Please contact your Team Leader.</p><?php else: ?>
        <form method="post" enctype="multipart/form-data" class="public-leave-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label class="public-leave-honeypot" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
            <label class="field-full">Staff member
                <select name="staff_id" required>
                    <option value="">Select your name</option>
                    <?php foreach ($staffOptions as $staffOption): ?>
                        <?php
                        $identityParts = array_filter([
                            trim((string) ($staffOption['department'] ?? '')),
                            trim((string) ($staffOption['team_label'] ?? '')),
                        ], static fn(string $value): bool => $value !== '');
                        $identityLabel = implode(' / ', array_unique($identityParts));
                        $staffLabel = (string) $staffOption['full_name'] . ($identityLabel !== '' ? ' - ' . $identityLabel : '');
                        ?>
                        <option value="<?= (int) $staffOption['id'] ?>"<?= $staffId === (int) $staffOption['id'] ? ' selected' : '' ?>><?= e($staffLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field-full">Your email
                <input type="email" name="requester_email" required maxlength="254" autocomplete="email" value="<?= e($requesterEmail) ?>" placeholder="name@example.com">
            </label>
            <label>Leave type
                <select name="leave_type">
                    <?php foreach (ROSTER_LEAVE_TYPES as $leaveTypeKey => $leaveTypeLabel): ?>
                        <option value="<?= e($leaveTypeKey) ?>"<?= $leaveType === $leaveTypeKey ? ' selected' : '' ?>><?= e($leaveTypeLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Supporting proof
                <input type="file" name="supporting_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
                <small>PDF, JPG, or PNG · up to 10 MB</small>
            </label>
            <label>Start date<input type="date" name="start_date" required value="<?= e($startDate) ?>"></label>
            <label>End date<input type="date" name="end_date" required value="<?= e($endDate) ?>"></label>
            <label class="field-full">Reason
                <textarea name="reason" rows="4" maxlength="5000" placeholder="Briefly explain your leave request."><?= e($reason) ?></textarea>
            </label>
            <button class="button field-full" type="submit">Submit leave request</button>
        </form>
        <?php endif; ?>
        <p class="auth-footnote"><a href="login.php">Back to sign in</a></p>
    </section>
</main>
</body>
</html>
