<?php
/** Exercise the code-only release on October 15 definitions with synthetic data only. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
$project = dirname(__DIR__);
if (($argv[1] ?? '') === 'page') {
    $root = $argv[2] ?? '';
    if (!preg_match('~/legacy-release-[a-f0-9]{12}$~', str_replace('\\', '/', $root))) exit(2);
    $input = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    chdir($root);
    $config = require $root . '/config/config.local.php';
    $db = $config['db'];
    $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']), $db['username'], $db['password']);
    require_once $root . '/app/security.php';
    $sessions = app_runtime_directory($root) . '/sessions';
    if (!is_dir($sessions)) mkdir($sessions, 0700, true);
    session_save_path($sessions); session_name($config['app']['session_name']);
    session_id('legacy' . bin2hex(random_bytes(12))); session_start();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    foreach ($input['session'] ?? [] as $key=>$value) $_SESSION[$key] = $value;
    if ($input['user']) {
        $_SESSION['user_id'] = $input['user'];
        $query = $pdo->prepare('SELECT password_hash FROM users WHERE id=?');
        $query->execute([$input['user']]); $_SESSION['auth_password_hash'] = $query->fetchColumn();
    }
    $_POST = $input['post'];
    if ($_POST) $_POST['csrf_token'] = $_SESSION['csrf_token'];
    session_write_close(); $pdo = null;
    $_GET = $input['get']; $_FILES = [];
    $_SERVER = array_merge($_SERVER, ['REQUEST_METHOD'=>$input['method'], 'PHP_SELF'=>'/' . $input['page'], 'SCRIPT_FILENAME'=>$root . '/' . $input['page'], 'HTTP_HOST'=>'localhost', 'SERVER_NAME'=>'localhost', 'REMOTE_ADDR'=>'127.0.0.1']);
    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (error_reporting() & $severity) throw new ErrorException($message, 0, $severity, $file, $line);
        return false;
    });
    ob_start();
    register_shutdown_function(static function () use ($root, $input): void {
        $html = (string) ob_get_clean();
        $error = error_get_last();
        $fatal = $error && in_array($error['type'], [E_ERROR,E_PARSE,E_COMPILE_ERROR], true);
        file_put_contents($root . '/evidence/' . $input['page'] . '-' . $input['user'] . '-' . $input['method'] . '.html', $html);
        echo json_encode(['status'=>http_response_code() ?: 200, 'fatal'=>(bool)$fatal, 'html'=>$html, 'error'=>$GLOBALS['error'] ?? '', 'approved_count'=>$GLOBALS['approvedRequestCount'] ?? null, 'unmaterialized_requests'=>isset($GLOBALS['approvedLeave']) ? count($GLOBALS['approvedLeave']) : null], JSON_THROW_ON_ERROR);
    });
    require $root . '/' . $input['page'];
    exit;
}

$config = require $project . '/config/config.local.php';
$db = $config['db'];
if (!in_array($db['host'], ['127.0.0.1','localhost','::1'], true)) throw new RuntimeException('Local database required.');
$token = bin2hex(random_bytes(6));
$database = 'cng_legacy_release_' . $token;
$root = $project . '/.local/legacy-release-' . $token;
$server = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $db['host'], $db['port']), $db['username'], $db['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$passed = 0;
$check = static function (bool $ok, string $message) use (&$passed): void {
    if (!$ok) throw new RuntimeException($message);
    $passed++; echo 'PASS: ' . $message . PHP_EOL;
};
$page = static function (string $name, int $user = 1, array $get = [], array $post = [], ?string $method = null, array $session = []) use ($root): array {
    $process = proc_open([PHP_BINARY, __FILE__, 'page', $root], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start page worker.');
    fwrite($pipes[0], json_encode(['page'=>$name, 'user'=>$user, 'get'=>$get, 'post'=>$post, 'session'=>$session, 'method'=>$method ?? ($post ? 'POST' : 'GET')], JSON_THROW_ON_ERROR)); fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $errors = stream_get_contents($pipes[2]); fclose($pipes[2]); $exit = proc_close($process);
    $result = json_decode($output, true);
    if ($exit !== 0 || !$result || $result['fatal'] || $errors !== '') throw new RuntimeException('Route failed: ' . $name . ': ' . substr($errors ?: $output, 0, 1200));
    return $result;
};
$configHashes = [];
foreach (['config/config.local.php','config/config.external.php','ticket_visibility.php'] as $file) if (is_file($project . '/' . $file)) $configHashes[$file] = hash_file('sha256', $project . '/' . $file);
$created = false;
try {
    $server->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'); $created = true;
    $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $database), $db['username'], $db['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $dump = file_get_contents($project . '/database/sscomadmin_cng_ticketing.sql');
    preg_match_all('/^CREATE TABLE `[^`]+` \(.*?\) ENGINE=[^;]+;/ms', $dump, $definitions);
    preg_match_all('/^ALTER TABLE `[^`]+`\s+.*?;/ms', $dump, $alterations);
    $check(count($definitions[0]) === 29, 'Use exactly the 29 saved October 15 table definitions; no saved application rows imported');
    foreach (array_merge($definitions[0], $alterations[0]) as $ddl) $pdo->exec($ddl);
    $shape = static function () use ($pdo): array {
        $result = [];
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) $result[$table] = preg_replace('/ AUTO_INCREMENT=\d+/', '', $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1]);
        ksort($result); return $result;
    };
    $schemaBefore = $shape();
    $roles = ['super-admin','team-leader','department-head','team-member','cng-admin'];
    foreach ($roles as $index=>$role) $pdo->prepare('INSERT INTO roles(id,name,slug) VALUES(?,?,?)')->execute([$index+1,$role,$role]);
    $permissions = ['view_all_tickets','create_tickets','edit_tickets','delete_tickets','assign_tickets','close_tickets','view_attachments','upload_attachments','access_leave_request_module','manage_users','manage_roles','manage_recruitment','export_tickets','bulk_ticket_actions','manage_calendar'];
    foreach ($permissions as $index=>$permission) {
        $pdo->prepare('INSERT INTO permissions(id,permission_key,label,description) VALUES(?,?,?,?)')->execute([$index+1,$permission,$permission,'Synthetic permission']);
        foreach (range(1,5) as $role) {
            $granted = $role === 1 || ($role !== 4 && in_array($permission, ['view_all_tickets','access_leave_request_module'], true)) || ($role === 4 && $permission === 'access_leave_request_module');
            $pdo->prepare('INSERT INTO role_permissions(role_id,permission_id,granted) VALUES(?,?,?)')->execute([$role,$index+1,$granted ? 1 : 0]);
        }
    }
    $codes = ['rm','customer-care','admin','insurance','compliance'];
    foreach ($codes as $index=>$code) $pdo->prepare('INSERT INTO departments(id,name,code) VALUES(?,?,?)')->execute([$index+1,ucwords(str_replace('-', ' ', $code)),$code]);
    $pdo->exec("INSERT INTO departments(id,name,code) VALUES(6,'QA-CODEX-ALL-20261023 Department','qa-all-20261023')");
    foreach ($roles as $index=>$role) $pdo->prepare('INSERT INTO users(id,role_id,department_id,username,full_name,email,password_hash,must_change_password) VALUES(?,?,1,?,?,?,?,0)')->execute([$index+1,$index+1,'legacy-'.$role,'Fixture ' . $role,$role . '@example.com',password_hash('SyntheticOnly!2026',PASSWORD_DEFAULT)]);
    $pdo->exec("INSERT INTO app_settings(setting_key,setting_value) VALUES('setup_complete','1')");
    $pdo->exec("INSERT INTO staff_directory(id,full_name,team_label,department_id) VALUES(1,'Fixture Member','Fixture',1),(2,'Fixture Leader','TL',1)");
    $pdo->exec("INSERT INTO tickets(id,ticket_number,issue_escalator,subject,issue,category,subcategory,department_id,employee_name,description,created_by,assignee_id) VALUES(1,'LEGACY-1','Fixture','Fixture ticket','Fixture issue','Performance','Capacity',1,'Fixture Member','Fixture description',1,2)");
    $pdo->exec("INSERT INTO tickets(id,ticket_number,issue_escalator,subject,issue,category,subcategory,department_id,employee_name,description,created_by,assignee_id) VALUES(2,'LEGACY-2','Fixture','Other fixture ticket','Other fixture issue','Performance','Capacity',1,'Fixture Member','Other fixture description',1,2)");
    $pdo->exec('INSERT INTO ticket_departments(ticket_id,department_id) VALUES(1,1),(1,2),(2,1)');
    foreach (['app','config','.local/sessions','storage/private/ticket-attachments','storage/private/leave-attachments','evidence'] as $dir) if (!is_dir($root . '/' . $dir)) mkdir($root . '/' . $dir, 0700, true);
    if (($argv[1] ?? '') === '--package') {
        $packagePath = realpath($argv[2] ?? '');
        if (!$packagePath || !str_starts_with($packagePath, $project . DIRECTORY_SEPARATOR)) throw new RuntimeException('Local release package required.');
        $baselinePath = $root . '/baseline.zip';
        $archiveProcess = proc_open(['git','archive','--format=zip','--output=' . $baselinePath,'HEAD','--',':(top,glob)*.php',':(top,glob)app/*.php','assets'], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $archivePipes, $project);
        if (!is_resource($archiveProcess)) throw new RuntimeException('Cannot export baseline application.');
        fclose($archivePipes[0]); stream_get_contents($archivePipes[1]); fclose($archivePipes[1]);
        $archiveError = stream_get_contents($archivePipes[2]); fclose($archivePipes[2]);
        if (proc_close($archiveProcess) !== 0) throw new RuntimeException($archiveError);
        $baseline = new ZipArchive();
        if ($baseline->open($baselinePath) !== true) throw new RuntimeException('Cannot read baseline application archive.');
        $baseline->extractTo($root); $baseline->close(); unlink($baselinePath);
        $package = new ZipArchive();
        if ($package->open($packagePath) !== true) throw new RuntimeException('Cannot read release package.');
        for ($index=0; $index<$package->numFiles; $index++) {
            $path = $package->getNameIndex($index);
            if (preg_match('~(^|/)(config|database|migrations|tests|storage|uploads)(/|$)|(^|/)\.htaccess$|\.sql$|\.\.~i', $path)) throw new RuntimeException('Unexpected release package entry.');
        }
        $package->extractTo($root); $package->close();
        $check(true, 'Actual release ZIP overlays baseline application files without configuration/database files');
    } else {
        foreach (array_merge(glob($project . '/*.php'), glob($project . '/app/*.php')) as $file) copy($file, $root . '/' . substr($file, strlen($project)+1));
    }
    file_put_contents($root . '/config/config.local.php', "<?php\n\$config=require " . var_export($project . '/config/config.local.php', true) . ";\n\$config['db']['database']=" . var_export($database, true) . ";\n\$config['external_sources']=[];\n\$config['smtp']['host']='';\nreturn \$config;\n");
    file_put_contents($root . '/ticket_visibility.php', file_get_contents($project . '/ticket_visibility.php') . "\n\$HIDDEN_TICKET_IDS=[];\n\$LOCKED_TICKET_IDS=[];\n");
    foreach (['dashboard.php','index.php','ticket.php','edit-ticket.php','create-ticket.php','department-workload.php','my-work.php','team-attendance.php','team-calendar.php','calendar-admin.php','leave-requests.php','users.php','pending-activations.php','admin.php','recruitment.php','reports.php','audit-log.php','health.php','notifications.php','change-password.php'] as $route) {
        $response = $page($route, 1, ['id'=>'1','staff'=>'1','month'=>'2026-11']);
        $check($response['status'] === 200, $route . ' renders on the unmodified October 15 schema');
    }
    $calendarLabel = $page('team-calendar.php', 1, ['month'=>'2026-11']);
    $staffLabel = $page('team-attendance.php', 1, ['staff'=>'1']);
    $check(str_contains($calendarLabel['html'], 'Team Calendar') && !str_contains($calendarLabel['html'], 'Team Calendar / Users Info') && str_contains($staffLabel['html'], 'Staff Directory'), 'Team Calendar keeps its name and Team Attendance is renamed Staff Directory');
    $departmentsPage = $page('department-workload.php', 1);
    $summaryStart = strpos($departmentsPage['html'], '<section class="department-summary-panel"');
    $ticketPanelStart = strpos($departmentsPage['html'], '<section class="department-ticket-panel"');
    $summaryHtml = $summaryStart !== false && $ticketPanelStart !== false ? substr($departmentsPage['html'], $summaryStart, $ticketPanelStart - $summaryStart) : '';
    $check($summaryHtml !== '' && !str_contains($summaryHtml, 'QA-CODEX-ALL-20261023') && stripos($summaryHtml, 'involved and current totals include closed tickets') !== false, 'Departments summary omits the marked QA fixture and explains metric counting');
    $departmentFilteredPage = $page('department-workload.php', 1, ['department'=>'2']);
    $check(str_contains($departmentFilteredPage['html'], 'name="department" value="2"') && str_contains($departmentFilteredPage['html'], 'LEGACY-1') && !str_contains($departmentFilteredPage['html'], 'LEGACY-2'), 'Department involved filter matches a supporting department and excludes unrelated tickets');
    $attendance = $page('team-attendance.php', 1, ['staff'=>'1']);
    $directory = $page('recruitment.php', 1);
    $check(str_contains($directory['html'], 'Team member details') && str_contains($directory['html'], 'Fixture Member') && !str_contains($directory['html'], 'directory is unavailable'), 'Recruitment displays actual employee cards on the legacy schema, not merely a 200 error page');
    copy($project . '/_brain/deployments/support/qa-fixtures.php', $root . '/qa-fixtures.php');
    $check($page('qa-fixtures.php', 4)['status'] === 404, 'Temporary live QA helper refuses non-Super Admins');
    $seed = $page('qa-fixtures.php', 1, [], ['action'=>'seed']);
    $check($seed['status'] === 200 && (int)$pdo->query("SELECT COUNT(*) FROM staff_directory WHERE team_label='QA-CODEX-ALL-20261023'")->fetchColumn() === 2, 'Temporary QA helper seeds only two marked staff rows on the old schema');
    $cleanup = $page('qa-fixtures.php', 1, [], ['action'=>'cleanup']);
    $check($cleanup['status'] === 200 && (int)$pdo->query('SELECT COUNT(*) FROM staff_directory')->fetchColumn() === 2, 'Temporary QA cleanup removes its staff fixtures and preserves baseline staff');
    $check(!str_contains($attendance['html'], 'name="shift_dst_start"') && !str_contains($attendance['html'], 'name="shift_label"'), 'Unsupported DST and shift-label controls are not offered');
    $public = $page('leave-request.php', 0);
    $check(!str_contains($public['html'], 'name="staff_name"') && str_contains($public['html'], 'ask your Team Leader'), 'Public intake is unavailable without inventing anonymous upload attribution');
    $check($page('leave-request.php', 0, [], ['staff_name'=>'Fixture Member'])['status'] === 409 && (int)$pdo->query('SELECT COUNT(*) FROM leave_requests')->fetchColumn() === 0, 'Unsupported public POST is refused without adding a request');
    $check(!str_contains($page('login.php', 0)['html'], 'Submit a public leave request'), 'Login does not advertise unavailable intake');
    $filtered = $page('index.php', 1, ['department'=>'hr']);
    $check($filtered['status'] === 200 && str_contains($filtered['html'], 'IT Department') && str_contains($filtered['html'], 'Finance Department'), 'Five department filter options work without inserting department rows');
    $page('team-attendance.php', 1, [], ['action'=>'save_staff','staff_id'=>'1','team_label'=>'Fixture updated','department_id'=>'1','hired_date'=>'2026-01-01','team_leader_name'=>'Fixture Leader','is_in_training'=>'0','shift_start'=>'09:00','shift_end'=>'18:00','break_minutes'=>'60','employment_status'=>'active','days_off'=>['Sat','Sun']]);
    $check($pdo->query('SELECT team_label FROM staff_directory WHERE id=1')->fetchColumn() === 'Fixture updated' && (int)$pdo->query('SELECT team_leader_id FROM staff_directory WHERE id=1')->fetchColumn() === 2, 'Attendance saves existing standard schedule and leader columns');
    $invalidRoster = $page('team-attendance.php', 1, [], ['action'=>'save_staff','staff_id'=>'1','team_label'=>'Must not save','department_id'=>'1','team_leader_name'=>'Fixture Leader','is_in_training'=>'0','shift_start'=>'09:00','shift_end'=>'18:00','shift_dst_start'=>'08:00','shift_dst_end'=>'17:00','employment_status'=>'active']);
    $check($invalidRoster['error'] !== '' && $pdo->query('SELECT team_label FROM staff_directory WHERE id=1')->fetchColumn() === 'Fixture updated', 'Unsupported schedule POST is rejected without partially saving roster changes');
    $page('leave-requests.php', 2, [], ['action'=>'create','staff_id'=>'1','start_date'=>'2026-11-03','end_date'=>'2026-11-04','leave_type'=>'annual','reason'=>'Synthetic request']);
    $requestId = (int)$pdo->query('SELECT id FROM leave_requests ORDER BY id DESC LIMIT 1')->fetchColumn();
    $check($requestId > 0, 'Signed-in leave submission works without requester_email');
    $pdo->prepare('UPDATE leave_requests SET employee_user_id=4 WHERE id=?')->execute([$requestId]);
    $pdo->prepare('INSERT INTO leave_request_attachments(leave_request_id,uploaded_by,file_name,file_path,mime_type,file_size) VALUES(?,1,?,?,?,1)')->execute([$requestId,'QA <proof>.png','storage/private/leave-attachments/'.$requestId.'/qa.png','image/png']);
    $proofId=(int)$pdo->lastInsertId();
    $ownerPage=$page('leave-requests.php',4)['html'];
    $check(str_contains($ownerPage,'My requests') && str_contains($ownerPage,'download-leave-attachment.php?id='.$proofId) && str_contains($ownerPage,'QA &lt;proof&gt;.png'), 'Team Member can see own request status and an escaped supporting-file link');
    $check(str_contains($page('leave-requests.php',2)['html'],'download-leave-attachment.php?id='.$proofId) && !str_contains($page('leave-requests.php',5)['html'],'download-leave-attachment.php?id='.$proofId), 'Approver queue shows proof links while unrelated CNG Admin receives no download link');
    $teamLeaderDecision = $page('leave-requests.php', 2, [], ['action'=>'approve','request_id'=>(string)$requestId]);
    $teamLeaderStatus = (string)$pdo->query('SELECT status FROM leave_requests WHERE id=' . $requestId)->fetchColumn();
    $calendarLeaveCount = (int)$pdo->query('SELECT COUNT(*) FROM calendar_events')->fetchColumn();
    $check($teamLeaderDecision['error'] === '' && $teamLeaderStatus === 'department_head_approved' && $calendarLeaveCount === 1, 'Team Leader approval is final and immediately creates the legacy calendar/attendance record: status=' . $teamLeaderStatus . ', events=' . $calendarLeaveCount . ', error=' . $teamLeaderDecision['error']);
    $pdo->exec("INSERT INTO leave_requests(employee_user_id,staff_id,created_by,leave_type,start_date,end_date,reason,status) VALUES(4,1,4,'annual','2026-11-05','2026-11-05','Previously Team Leader approved','team_leader_approved')");
    $oldStageRequestId = (int)$pdo->lastInsertId();
    $queue = $page('leave-requests.php', 2);
    $check(str_contains($queue['html'], 'Previously Team Leader approved'), 'Team Leader queue includes legacy requests awaiting the now-removed Department Head step');
    $check(str_contains($queue['html'], 'name="action" value="admin_delete"') && !str_contains($queue['html'], 'href="leave-requests.php?edit='), 'Team Leader gets leave removal controls without Super Admin edit controls');
    $blockedRemoval = $page('leave-requests.php', 2, [], ['action'=>'admin_delete','request_id'=>(string)$oldStageRequestId]);
    $check($blockedRemoval['status'] !== 403 && str_contains($blockedRemoval['error'], 'Apply the additive leave removal migration') && $pdo->query('SELECT status FROM leave_requests WHERE id=' . $oldStageRequestId)->fetchColumn() === 'team_leader_approved', 'Team Leader removal respects legacy retention-column guard and preserves the request');
    $page('leave-requests.php', 2, [], ['action'=>'approve','request_id'=>(string)$oldStageRequestId]);
    $check($pdo->query('SELECT status FROM leave_requests WHERE id=' . $oldStageRequestId)->fetchColumn() === 'department_head_approved' && (int)$pdo->query('SELECT COUNT(*) FROM calendar_events')->fetchColumn() === 2, 'Team Leader can finalize an older request left at the legacy second stage');
    $page('leave-requests.php', 2, [], ['action'=>'approve','request_id'=>(string)$requestId]);
    $check((int)$pdo->query('SELECT COUNT(*) FROM calendar_events')->fetchColumn() === 2, 'Repeated approval does not duplicate leave');
    $pdo->exec("INSERT INTO leave_requests(employee_user_id,staff_id,created_by,leave_type,start_date,end_date,reason,status) VALUES(4,1,4,'sick','2026-11-06','2026-11-06','Synthetic Super Admin approval','pending')");
    $superRequestId = (int)$pdo->lastInsertId();
    $superAdminPage = $page('leave-requests.php', 1);
    $check(str_contains($superAdminPage['html'], 'name="action" value="reject"') && str_contains($superAdminPage['html'], 'href="leave-requests.php?edit=' . $superRequestId), 'Super Admin sees row-level leave decisions and edit controls on the legacy schema');
    $failedDecision = $page('leave-requests.php', 1, ['notice'=>'removed'], ['action'=>'approve','request_id'=>'99999']);
    $check($failedDecision['error'] !== '' && !str_contains($failedDecision['html'], 'Leave request hidden.'), 'A failed decision never replays a stale green removal success notice');
    $check(!str_contains($failedDecision['html'], 'Action completed.') && !str_contains($failedDecision['html'], "cng_action_success"), 'A POST never shows an optimistic green success toast before the server result');
    $preservedAction = strpos($failedDecision['html'], 'submittedAction.name=button.name;');
    $disabledSubmitter = strpos($failedDecision['html'], 'button.disabled=true;');
    $check($preservedAction !== false && strpos($failedDecision['html'], 'form.appendChild(submittedAction);') !== false && $disabledSubmitter !== false && $preservedAction < $disabledSubmitter, 'The clicked submit action is preserved before its button is disabled');
    $superDecision = $page('leave-requests.php', 1, [], ['action'=>'approve','request_id'=>(string)$superRequestId]);
    $check($superDecision['error'] === '' && $pdo->query('SELECT status FROM leave_requests WHERE id=' . $superRequestId)->fetchColumn() === 'department_head_approved' && (int)$pdo->query('SELECT COUNT(*) FROM calendar_events')->fetchColumn() === 3, 'Super Admin can finalize a legacy request and materialize its calendar leave');
    $calendar = $page('team-calendar.php', 1, ['month'=>'2026-11']);
    $check($calendar['approved_count'] === 3 && $calendar['unmaterialized_requests'] === 0, 'Legacy requests are counted in the summary and rendered only once on the calendar');
    $eventId = (int)$pdo->query('SELECT id FROM calendar_events LIMIT 1')->fetchColumn();
    $blockedEdit = $page('calendar-admin.php', 1, [], ['type'=>'event','action'=>'edit','id'=>(string)$eventId,'title'=>'Wrong change','event_type'=>'leave','event_date'=>'2026-11-10','end_date'=>'2026-11-11']);
    $blockedDelete = $page('calendar-admin.php', 1, [], ['type'=>'event','action'=>'delete','id'=>(string)$eventId]);
    $check($blockedEdit['error'] !== '', 'Legacy approved request cannot be edited through an unsynchronized calendar path');
    $check(str_contains($blockedDelete['error'], 'Apply the additive leave removal migration'), 'Legacy approved request requires retention columns before Super Admin removal');
    $check((int)$pdo->query('SELECT COUNT(*) FROM calendar_events')->fetchColumn() === 3 && $pdo->query('SELECT event_date FROM calendar_events WHERE id=' . $eventId)->fetchColumn() === '2026-11-03', 'Blocked calendar changes preserve approved request entries');
    $page('team-calendar.php', 1, [], ['action'=>'add_event','title'=>'Synthetic meeting','event_date'=>'2026-11-12','event_type'=>'team_event']);
    $check((int)$pdo->query("SELECT COUNT(*) FROM calendar_events WHERE title='Synthetic meeting'")->fetchColumn() === 1, 'Manual calendar event saves on the unchanged schema');
    $page('team-calendar.php',1,[],['action'=>'save_leave','staff_id'=>'1','start_date'=>'2026-11-18','end_date'=>'2026-11-18','leave_type'=>'sick','notes'=>'Synthetic manual leave']);
    $manualId=(int)$pdo->query("SELECT id FROM calendar_events WHERE notes='Synthetic manual leave'")->fetchColumn();
    $deleted=$page('team-calendar.php',1,[],['action'=>'delete_leave','leave_id'=>(string)$manualId]);
    $check($manualId>0 && $deleted['error']==='' && (int)$pdo->query("SELECT COUNT(*) FROM calendar_events WHERE notes='Synthetic manual leave'")->fetchColumn()===0,'Manual leave deletion succeeds without a second commit or a false failure message');
    $page('create-ticket.php', 1, [], ['subject'=>'New legacy fixture','issue'=>'Synthetic issue','priority'=>'normal','category'=>'Performance','subcategory'=>'Capacity','department_id'=>'1','department_ids'=>['1'],'assignee_ids'=>['2'],'employee_name'=>'Fixture Member','description'=>'Synthetic creation']);
    $check((int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE subject='New legacy fixture'")->fetchColumn() === 1, 'Ticket creation works with existing departments and allocator');
    $page('edit-ticket.php', 1, ['id'=>'1'], ['subject'=>'Edited legacy fixture','issue'=>'Updated issue','priority'=>'normal','category'=>'Performance','subcategory'=>'Capacity','department_id'=>'1','department_ids'=>['1'],'assignee_ids'=>['2'],'employee_name'=>'Fixture Member','description'=>'Updated fixture','status'=>'in_progress']);
    $check($pdo->query('SELECT subject FROM tickets WHERE id=1')->fetchColumn() === 'Edited legacy fixture', 'Ticket details and assignments save on the unchanged schema');
    $export = $page('export-tickets.php', 1);
    $check($export['status'] === 200 && str_contains($export['html'], 'Edited legacy fixture'), 'Ticket CSV export reads the existing department schema');
    $template = $page('reports.php', 1, ['csv_template'=>'departments']);
    $check(trim($template['html']) === 'name,code', 'Reports supplies the two-column department template for the existing schema');
    $preview = ['type'=>'departments','rows'=>[['name'=>'Synthetic Imported Department','code'=>'fixture-import']], 'errors'=>[], 'token'=>'fixture-token'];
    $page('reports.php', 1, [], ['action'=>'import','preview_token'=>'fixture-token'], 'POST', ['csv_preview'=>$preview]);
    $check((int)$pdo->query("SELECT COUNT(*) FROM departments WHERE code='fixture-import'")->fetchColumn() === 1, 'Actual department CSV import uses existing columns only');
    $pdo->exec("INSERT INTO users(role_id,username,full_name,password_hash,must_change_password,is_active,approval_status) VALUES(4,'pending-fixture','Pending Fixture','unused',0,0,'pending')");
    $pendingId = (int)$pdo->lastInsertId();
    $page('pending-activations.php', 1, [], ['action'=>'approval','id'=>(string)$pendingId,'status'=>'approved']);
    $check((int)$pdo->query('SELECT is_active FROM users WHERE id=' . $pendingId)->fetchColumn() === 1, 'Account activation works without disabled_at');
    $page('users.php', 1, [], ['action'=>'save','id'=>(string)$pendingId,'full_name'=>'Pending Fixture','username'=>'pending-fixture','email'=>'pending@example.com','role_id'=>'4','department_id'=>'1']);
    $check((int)$pdo->query('SELECT is_active FROM users WHERE id=' . $pendingId)->fetchColumn() === 0, 'Account disabling works without adding timestamp columns');
    $page('pending-activations.php', 1, [], ['action'=>'activate','id'=>(string)$pendingId]);
    $check((int)$pdo->query('SELECT is_active FROM users WHERE id=' . $pendingId)->fetchColumn() === 1, 'Existing inactive account can be reactivated');
    $check($page('users.php', 2)['status'] === 403 && $page('create-ticket.php', 5, [], ['subject'=>'Denied'])['status'] === 403, 'Legacy manager and CNG role boundaries reject unauthorized account/ticket actions');
    require_once $root . '/app/report_workload.php';
    $pdo->exec('DELETE FROM ticket_departments WHERE ticket_id=1');
    [$workload] = report_native_workload($pdo, 't.id=1', []);
    $check(count($workload) === 1 && $workload[0]['name'] === 'Rm' && (int)$workload[0]['count'] === 1, 'Legacy reports count the current department when the pivot is absent');
    $savedMailer=file_get_contents($root.'/app/mailer.php');
    $savedFixtureConfig=file_get_contents($root.'/config/config.local.php');
    file_put_contents($root.'/config/config.local.php',str_replace('return $config;', '$config[\'app\'][\'base_url\']=\'\'; return $config;', $savedFixtureConfig));
    file_put_contents($root.'/app/mailer.php', '<?php function send_mail_to_addresses(array $to,string $subject,string $body):void {file_put_contents(__DIR__."/../evidence/reset-mail.json",json_encode([$to,$subject,$body]));}');
    try {
        $recovery=$page('forgot-password.php',0,[],['email'=>'team-member@example.com']);
        $mail=json_decode((string)file_get_contents($root.'/evidence/reset-mail.json'),true,512,JSON_THROW_ON_ERROR);
        $check($recovery['status']===200 && preg_match('~https://cng-tickets\.stratastaff\.com/reset-password\.php\?token=[a-f0-9]{64}~',$mail[2])===1 && !str_contains($mail[2],'localhost'), 'Blank legacy base URL creates a reset email with the trusted live origin, never request Host');
    } finally {
        file_put_contents($root.'/app/mailer.php',$savedMailer);
        file_put_contents($root.'/config/config.local.php',$savedFixtureConfig);
    }
    $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$listener) throw new RuntimeException('Cannot reserve HTTP test port.');
    $address = stream_socket_get_name($listener, false); fclose($listener);
    $httpLog = $root . '/evidence/http-server.log';
    $httpProcess = proc_open([PHP_BINARY, '-S', $address, '-t', $root], [0=>['pipe','r'],1=>['file',$httpLog,'a'],2=>['file',$httpLog,'a']], $httpPipes, $root);
    if (!is_resource($httpProcess)) throw new RuntimeException('Cannot start isolated HTTP server.');
    fclose($httpPipes[0]);
    try {
        $ready = false;
        for ($attempt=0; $attempt<50; $attempt++) {
            $socket = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
            if ($socket) { fclose($socket); $ready = true; break; }
            usleep(50000);
        }
        if (!$ready) throw new RuntimeException('Isolated HTTP server did not start.');
        $request = static function (string $path, string $method='GET', array $post=[], string $cookie='') use ($address): array {
            $headers = ['Connection: close'];
            if ($cookie !== '') $headers[] = 'Cookie: ' . $cookie;
            if ($method === 'POST') $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $context = stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$method === 'POST' ? http_build_query($post) : '', 'ignore_errors'=>true,'follow_location'=>0,'timeout'=>10]]);
            $body = file_get_contents('http://' . $address . '/' . $path, false, $context);
            $responseHeaders = $http_response_header ?? [];
            preg_match('~HTTP/\S+ (\d+)~', $responseHeaders[0] ?? '', $status);
            $cookies = [];
            foreach ($responseHeaders as $header) if (stripos($header, 'Set-Cookie: ') === 0) $cookies[] = substr($header, 12);
            return ['status'=>(int)($status[1] ?? 0),'body'=>(string)$body,'headers'=>$responseHeaders,'cookies'=>$cookies];
        };
        $signIn = $request('login.php');
        preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $signIn['body'], $csrf);
        $anonymousCookie = explode(';', end($signIn['cookies']))[0];
        $signedIn = $request('login.php', 'POST', ['csrf_token'=>$csrf[1] ?? '', 'username'=>'legacy-super-admin','password'=>'SyntheticOnly!2026'], $anonymousCookie);
        $signedCookieLine = end($signedIn['cookies']);
        $signedCookie = explode(';', $signedCookieLine)[0];
        $check($signIn['status'] === 200 && $signedIn['status'] === 302 && str_contains($signedCookieLine, 'Max-Age=2592000') && stripos($signedCookieLine,'HttpOnly') !== false && stripos($signedCookieLine,'SameSite=Lax') !== false, 'Real HTTP sign-in issues a persistent 30-day HttpOnly/SameSite cookie');
        $check($request('dashboard.php','GET',[],$signedCookie)['status'] === 200, 'Issued HTTP session opens the dashboard');
        $check($request('pending-activations.php','POST',['action'=>'activate','id'=>(string)$pendingId,'csrf_token'=>'invalid'],$signedCookie)['status'] === 419, 'HTTP account mutation rejects invalid CSRF token');
        $pdo->prepare('UPDATE users SET password_hash=? WHERE id=1')->execute([password_hash('ChangedFixture!2026', PASSWORD_DEFAULT)]);
        $check($request('dashboard.php','GET',[],$signedCookie)['status'] === 302, 'Changing a password revokes the older HTTP session');
        $anonymous = $request('login.php');
        preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $anonymous['body'], $csrf);
        $cookie = explode(';', end($anonymous['cookies']))[0];
        for ($attempt=1; $attempt<=8; $attempt++) $failed = $request('login.php','POST',['csrf_token'=>$csrf[1] ?? '', 'username'=>'legacy-team-leader','password'=>'WrongFixture!'],$cookie);
        $check($failed['status'] === 429 && $request('login.php','POST',['csrf_token'=>$csrf[1] ?? '', 'username'=>'legacy-team-leader','password'=>'SyntheticOnly!2026'],$cookie)['status'] === 429, 'Real HTTP sign-in blocks after eight failed attempts, including an immediate valid retry');
        $resetToken = bin2hex(random_bytes(32));
        $pdo->prepare('INSERT INTO password_reset_tokens(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR))')->execute([4,hash('sha256',$resetToken)]);
        $check($request('reset-password.php?token=' . $resetToken)['status'] === 200, 'Existing password reset route remains reachable with preserved server configuration');
    } finally {
        proc_terminate($httpProcess); proc_close($httpProcess);
    }
    $check($shape() === $schemaBefore, 'All 29 October 15 table definitions are unchanged after page and write-path checks');
    foreach ($configHashes as $file=>$hash) $check(hash_file('sha256', $project . '/' . $file) === $hash, $file . ' was not changed');
    echo 'PASS: ' . $passed . ' legacy compatibility checks; no migrations or real application rows used.' . PHP_EOL;
} finally {
    if ($created) {
        if (!preg_match('/\Acng_legacy_release_[a-f0-9]{12}\z/', $database)) throw new RuntimeException('Refusing unexpected cleanup target.');
        $server->exec('DROP DATABASE `' . $database . '`');
    }
}
