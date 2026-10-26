<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';

$config = require __DIR__ . '/../config/config.local.php';
$db = $config['db'];
if (!in_array($db['host'], ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Migration test requires a local database host.');
}
$database = 'cng_migration_test_' . bin2hex(random_bytes(6));
$sourceDatabase = (string) $db['database'];
if (!preg_match('/\A[A-Za-z0-9_]+\z/', $sourceDatabase)) throw new RuntimeException('Unexpected local database name.');
$quotedSourceDatabase = '`' . $sourceDatabase . '`';
$server = new PDO(
    sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $db['host'], $db['port']),
    $db['username'],
    $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$quotedDatabase = '`' . $database . '`';
$created = false;

try {
    $server->exec("CREATE DATABASE $quotedDatabase CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $database),
        $db['username'],
        $db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );

    // Clone definitions only. The user's local application rows are never copied or changed.
    foreach (['departments','roles','permissions','role_permissions','staff_directory','leave_requests','leave_request_attachments','calendar_events'] as $table) {
        $pdo->exec("CREATE TABLE $quotedDatabase.`$table` LIKE $quotedSourceDatabase.`$table`");
    }

    // Synthetic rows exercise the migration's intentional DML only inside the throwaway DB.
    $pdo->exec("INSERT INTO departments(name,code) VALUES ('Legacy IT','internal-it')");
    $pdo->exec("INSERT INTO roles(id,name,slug,is_system) VALUES (1,'Team Leader','team-leader',1)");
    $pdo->exec("INSERT INTO permissions(id,permission_key,label,description) VALUES (1,'view_attachments','View attachments','Test grant')");
    $pdo->exec("INSERT INTO staff_directory(id,full_name,team_label) VALUES (1,'Leader Fixture','QA'),(2,'Member Fixture','QA')");

    // Recreate the pre-028-to-032 columns from the baseline schema in this disposable DB.
    $pdo->exec('ALTER TABLE departments DROP COLUMN department_type');
    $pdo->exec('ALTER TABLE staff_directory DROP COLUMN team_leader_name, DROP COLUMN shift_label, DROP COLUMN shift_dst_start, DROP COLUMN shift_dst_end');
    $pdo->exec('ALTER TABLE leave_requests DROP COLUMN requester_email, MODIFY status ENUM(\'pending\',\'team_leader_approved\',\'department_head_approved\',\'rejected\') NOT NULL DEFAULT \'pending\'');
    $pdo->exec('ALTER TABLE leave_request_attachments MODIFY uploaded_by BIGINT UNSIGNED NOT NULL');
    $foreignKey = $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='calendar_events' AND CONSTRAINT_TYPE='FOREIGN KEY' AND CONSTRAINT_NAME='fk_calendar_leave_request'")->fetchColumn();
    if ($foreignKey) $pdo->exec('ALTER TABLE calendar_events DROP FOREIGN KEY fk_calendar_leave_request');
    $pdo->exec('ALTER TABLE calendar_events DROP INDEX uq_calendar_leave_request, DROP COLUMN leave_request_id');

    $staff = $pdo->query("SHOW COLUMNS FROM staff_directory LIKE 'team_leader_id'")->fetch();
    if (!$staff) throw new RuntimeException('Source schema lacks the expected baseline team_leader_id column.');
    $pdo->exec("UPDATE staff_directory SET team_leader_id=1 WHERE id=2");
    $pdo->exec("INSERT INTO leave_requests(id,employee_user_id,staff_id,start_date,end_date,reason,status,leave_type) VALUES (1,1,2,'2026-11-01','2026-11-02','Fixture','department_head_approved','annual')");
    $pdo->exec("INSERT INTO calendar_events(id,title,event_date,end_date,event_type,created_by,staff_id,leave_type,notes) VALUES (1,'Legacy leave fixture','2026-11-01','2026-11-02','leave',1,2,'annual','Approved leave request #1')");

    $snapshot = static function (PDO $pdo): array {
        $queries = [
            'departments' => 'SELECT * FROM departments ORDER BY id',
            'roles' => 'SELECT * FROM roles ORDER BY id',
            'permissions' => 'SELECT * FROM permissions ORDER BY id',
            'role_permissions' => 'SELECT * FROM role_permissions ORDER BY role_id,permission_id',
            'staff_directory' => 'SELECT * FROM staff_directory ORDER BY id',
            'leave_requests' => 'SELECT * FROM leave_requests ORDER BY id',
            'leave_request_attachments' => 'SELECT * FROM leave_request_attachments ORDER BY id',
            'calendar_events' => 'SELECT * FROM calendar_events ORDER BY id',
        ];
        $result = [];
        foreach ($queries as $table => $sql) {
            $result[$table] = hash('sha256', json_encode($pdo->query($sql)->fetchAll(), JSON_THROW_ON_ERROR));
        }
        return $result;
    };

    $migrations = ['028_team_leader_attachment_access','029_internal_department_types','030_team_attendance_roster_details','031_public_leave_requests','032_linked_leave_workflow'];
    foreach ($migrations as $migration) {
        $pdo->exec(file_get_contents(__DIR__ . '/../migrations/' . $migration . '.sql'));
    }

    $department = $pdo->query("SELECT name,department_type FROM departments WHERE code='internal-it'")->fetch(PDO::FETCH_ASSOC);
    if ($department !== ['name' => 'IT Department', 'department_type' => 'internal']) throw new RuntimeException('Migration 029 fixture upsert did not produce the expected department.');
    if ($pdo->query("SELECT granted FROM role_permissions WHERE role_id=1 AND permission_id=1")->fetchColumn() != 1) throw new RuntimeException('Migration 028 did not create the expected permission grant.');
    if ($pdo->query('SELECT team_leader_name FROM staff_directory WHERE id=2')->fetchColumn() !== 'Leader Fixture') throw new RuntimeException('Migration 030 did not link the fixture leader name.');
    if ((int)$pdo->query('SELECT leave_request_id FROM calendar_events WHERE id=1')->fetchColumn() !== 1) throw new RuntimeException('Migration 032 did not link the exact fixture calendar event.');
    if ($pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'requester_email'")->fetchColumn() === false) throw new RuntimeException('Migration 031 did not add requester_email.');

    $afterFirstPass = $snapshot($pdo);
    foreach ($migrations as $migration) {
        $pdo->exec(file_get_contents(__DIR__ . '/../migrations/' . $migration . '.sql'));
    }
    if ($snapshot($pdo) !== $afterFirstPass) throw new RuntimeException('A migration repeat changed fixture rows in the disposable DB.');

    echo "PASS: migrations 028–032 ran on synthetic fixtures in a disposable database.\n";
    echo "PASS: permission, department, roster, public leave, and exact calendar-link behavior verified.\n";
    echo "PASS: repeating migrations 028–032 leaves disposable fixture values unchanged.\n";
} finally {
    if ($created) {
        if (!preg_match('/\Acng_migration_test_[a-f0-9]{12}\z/', $database)) throw new RuntimeException('Refusing to drop an unexpected database name.');
        $server->exec("DROP DATABASE $quotedDatabase");
    }
}
