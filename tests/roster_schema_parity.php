<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';
$pdo = db();
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
$freshLinks = [];
preg_match_all('/(?:CREATE TABLE (\w+) \((.*?)\) ENGINE=[^;]*;|ALTER TABLE (\w+) ([^;]*);)/s', $schema, $definitions, PREG_SET_ORDER);
foreach ($definitions as $definition) {
    $table = $definition[1] ?: $definition[3];
    $body = $definition[1] ? $definition[2] : $definition[4];
    preg_match_all('/FOREIGN KEY\((\w+)\) REFERENCES (\w+)\((\w+)\)/', $body, $links, PREG_SET_ORDER);
    foreach ($links as $link) $freshLinks[] = implode(':', [$table,$link[1],$link[2],$link[3]]);
}
$existingLinks = $pdo->query("SELECT CONCAT(TABLE_NAME,':',COLUMN_NAME,':',REFERENCED_TABLE_NAME,':',REFERENCED_COLUMN_NAME) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
sort($freshLinks); sort($existingLinks);
if ($freshLinks !== $existingLinks) throw new RuntimeException('Fresh-schema foreign keys differ from migrated local database.');
echo "PASS: all 44 fresh-schema foreign-key links match migrated database.\n";
preg_match_all('/CREATE TABLE (\w+) \(/', $schema, $schemaTables);
foreach ($schemaTables[1] as $table) {
    $existing = array_column($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(), 'Type', 'Field');
    if (!preg_match('/CREATE TABLE ' . $table . ' \((.*?)\) ENGINE=[^;]*;/s', $schema, $match)) throw new RuntimeException('Missing schema table: ' . $table);
    $body = preg_replace('/FOREIGN KEY\([^)]*\) REFERENCES \w+\([^)]*\)(?: ON DELETE (?:SET NULL|CASCADE))?/', '', $match[1]);
    do { $body = preg_replace('/,\s*,/', ',', $body, -1, $count); } while ($count > 0);
    $body = preg_replace('/,\s*$/', '', $body);
    $pdo->exec("CREATE TEMPORARY TABLE `$table` ($body) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $fresh = array_column($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(), 'Type', 'Field');
    ksort($existing); ksort($fresh);
    if ($existing !== $fresh) throw new RuntimeException('Schema mismatch: ' . $table . ' ' . json_encode(['existing'=>$existing,'fresh'=>$fresh]));
    echo "PASS: fresh $table columns/types match migrated local database.\n";
}
// Prove the guarded legacy link without modifying persistent application data.
$pdo->exec("INSERT INTO leave_requests(id,staff_id,start_date,end_date,leave_type,reason,status) VALUES(1,1,'2026-11-01','2026-11-02','annual','','department_head_approved'),(2,2,'2026-11-03','2026-11-03','annual','','approved')");
$pdo->exec("INSERT INTO calendar_events(id,title,staff_id,event_date,end_date,leave_type,notes,event_type,created_by) VALUES(1,'Fixture',1,'2026-11-01','2026-11-02','annual','Approved leave request #1','leave',9999),(2,'Edited fixture',2,'2026-11-04',NULL,'annual','Approved leave request #2','leave',9999)");
$migration = file_get_contents(__DIR__ . '/../migrations/032_linked_leave_workflow.sql');
$sql = substr($migration, strpos($migration, 'UPDATE calendar_events target'));
$pdo->exec($sql); $pdo->exec($sql);
if ((int)$pdo->query('SELECT leave_request_id FROM calendar_events WHERE id=1')->fetchColumn() !== 1 || $pdo->query('SELECT leave_request_id FROM calendar_events WHERE id=2')->fetchColumn() !== null) throw new RuntimeException('Legacy backfill guessed an edited record or missed an exact match.');
echo "PASS: exact legacy linkage, edited-row preservation and repeat-run safety.\n";
