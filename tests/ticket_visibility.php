<?php
/** CLI-only integration tests. Uses a disposable local database, never real tickets. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/ticket_visibility.php';
require_once dirname(__DIR__) . '/app/tickets.php';
require_once dirname(__DIR__) . '/app/external_tickets.php';
$config = require dirname(__DIR__) . '/config/config.local.php';
$db = $config['db'];
if (!in_array($db['host'], ['127.0.0.1', 'localhost', '::1'], true)) throw new RuntimeException('Tests require a local database.');
function connection(array $db, string $database): PDO {
    return new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $database), $db['username'], $db['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
}
function check_visibility(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    echo 'PASS: ' . $label . PHP_EOL;
}
// Child only connects to the parent's disposable database and exercises contention.
if (($argv[1] ?? '') === 'worker') {
    $database = $argv[2] ?? '';
    if (!preg_match('/\Acng_visibility_test_[a-f0-9]{12}\z/', $database)) throw new RuntimeException('Invalid test database.');
    $pdo = connection($db, $database);
    $HIDDEN_TICKET_IDS = [2,3];
    ticket_visibility_prepare_allocator($pdo);
    $pdo->beginTransaction();
    echo "READY\n"; flush();
    $id = next_ticket_id($pdo);
    $pdo->prepare('INSERT INTO tickets(id,subject) VALUES(?,?)')->execute([$id,'worker']);
    $pdo->commit();
    echo $id . "\n";
    exit;
}
$originalIds = $HIDDEN_TICKET_IDS;
check_visibility(count(ticket_visibility_ids()) === 30, '30 hidden IDs');
foreach ([6119,5156] as $id) check_visibility(is_ticket_hidden($id), 'configured ID ' . $id);
$_SESSION['role'] = 'team-leader';
check_visibility(!can_view_hidden(), 'normal role has no exemption');
$_SESSION['role']='cng-admin';
[$cngClause,$cngParams]=hidden_ids_sql_clause();
check_visibility(in_array(511,$cngParams,true) && !in_array(90,$cngParams,true),'CNG Admin excludes 511 but counts second-list tickets');
check_visibility(!ticket_access_allowed(90) && !ticket_access_allowed(511),'CNG Admin cannot open locked or hidden tickets');
$_SESSION['role']='team-leader';
[$clause,$params] = hidden_ids_sql_clause('t.id');
check_visibility(count($params) === 30 && substr_count($clause,'?') === 30, 'prepared exclusion parameters');
try { hidden_ids_sql_clause('id); DROP TABLE tickets'); throw new RuntimeException('Identifier accepted'); }
catch (InvalidArgumentException $e) { echo "PASS: rejects unsafe SQL identifier\n"; }
$_SESSION['role'] = 'super-admin';
check_visibility(can_view_hidden() && hidden_ids_sql_clause() === ['',[]], 'Super Admin sees configured IDs');
$forced=[];
check_visibility(ticket_visibility_sql($forced,'t.id',true,true) !== '' && count($forced) === 54, 'API excludes hidden IDs even with admin session');
$HIDDEN_TICKET_IDS=[];
$_SESSION['role']='team-leader';
check_visibility(hidden_ids_sql_clause() === ['',[]], 'empty config restores visibility');
$HIDDEN_TICKET_IDS=$originalIds;
check_visibility(!external_ticket_visible_to_user(['external_id'=>6119],['role_slug'=>'team-leader']), 'external hidden ticket rejected');
$_SESSION['role']='super-admin';
check_visibility(external_ticket_visible_to_user(['external_id'=>6119],['role_slug'=>'super-admin']), 'external admin exception');
$_SESSION['role']='team-leader';
check_visibility(count(ticket_locked_ids()) === 24 && !in_array(511,ticket_locked_ids(),true) && is_ticket_hidden(511),'24 locked IDs; 511 is fully hidden');
foreach (ticket_locked_ids() as $lockedId) {
    check_visibility(!is_ticket_hidden($lockedId) && !ticket_access_allowed($lockedId),'listed but locked ID '.$lockedId);
}
check_visibility(external_ticket_visible_to_user(['external_id'=>90],['role_slug'=>'team-leader']),'locked external ticket remains listed');
check_visibility(external_ticket_find('unused',90,['role_slug'=>'team-leader']) === null,'locked external detail denied before source lookup');
$_SESSION['role']='super-admin';
check_visibility(ticket_access_allowed(90) && ticket_access_allowed(511),'Super Admin can open locked IDs');
$LOCKED_TICKET_IDS=array_values(array_diff($LOCKED_TICKET_IDS,[90]));
$_SESSION['role']='team-leader';
check_visibility(ticket_access_allowed(90),'removing locked ID restores opening');
$LOCKED_TICKET_IDS[]=90;
$database='cng_visibility_test_'.bin2hex(random_bytes(6));
$server=connection($db,$db['database']);
$server->prepare('CREATE DATABASE `'.$database.'`')->execute();
try {
    $pdo=connection($db,$database);
    $pdo->prepare('CREATE TABLE tickets(id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,subject VARCHAR(100),deleted_at DATETIME NULL) ENGINE=InnoDB')->execute();
    $pdo->prepare('INSERT INTO tickets(id,subject) VALUES(?,?),(?,?),(?,?)')->execute([90,'locked',91,'locked',93,'visible']);
    $_SESSION['role']='team-leader';
    [$listedClause,$listedParams]=hidden_ids_sql_clause();
    $listed=$pdo->prepare('SELECT COUNT(*) FROM tickets WHERE '.$listedClause);$listed->execute($listedParams);
    check_visibility((int)$listed->fetchColumn() === 3,'locked native records remain in dashboard count');
    $detailParams=[];$detailClause=ticket_visibility_sql($detailParams,'id',false,true);
    $details=$pdo->prepare('SELECT id FROM tickets WHERE 1=1'.$detailClause);$details->execute($detailParams);
    check_visibility(array_map('intval',$details->fetchAll(PDO::FETCH_COLUMN)) === [93],'detail API excludes locked rows');
    $HIDDEN_TICKET_IDS=[90,91];
    $originalIds=$HIDDEN_TICKET_IDS;
    $_SESSION['role']='team-leader';
    [$clause,$params]=hidden_ids_sql_clause();
    $q=$pdo->prepare('SELECT id FROM tickets WHERE '.$clause);$q->execute($params);
    check_visibility(array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)) === [93], 'SQL list hides configured records');
    $count=$pdo->prepare('SELECT COUNT(*) FROM tickets WHERE '.$clause);$count->execute($params);
    check_visibility((int)$count->fetchColumn() === 1,'SQL dashboard count excludes hidden records');
    $scoped=[];$scope=ticket_scope_sql(['id'=>1,'role_slug'=>'team-leader'],$scoped);
    $q=$pdo->prepare('SELECT COUNT(*) FROM tickets t WHERE 1=1'.$scope);$q->execute($scoped);
    check_visibility((int)$q->fetchColumn() === 1,'shared native scope filters before aggregate');
    [$nc,$np]=ticket_visibility_notification_clause('url');
    $pdo->prepare('CREATE TABLE notifications(id INT PRIMARY KEY AUTO_INCREMENT,url VARCHAR(255),read_at DATETIME NULL) ENGINE=InnoDB')->execute();
    foreach (['ticket.php?id=90','ticket.php?id=900','ticket.php?external=source:91','ticket.php?external=source%3A91','ticket.php?id=00090','ticket.php?id=93','leave-requests.php?id=90'] as $url) {
        $pdo->prepare('INSERT INTO notifications(url) VALUES(?)')->execute([$url]);
    }
    $q=$pdo->prepare('SELECT url FROM notifications WHERE 1=1'.$nc.' ORDER BY id LIMIT 80');$q->execute($np);
    check_visibility($q->fetchAll(PDO::FETCH_COLUMN) === ['ticket.php?id=900','ticket.php?id=93','leave-requests.php?id=90'],'notifications match exact ticket IDs and external URLs');
    $q=$pdo->prepare('UPDATE notifications SET read_at=NOW() WHERE read_at IS NULL'.$nc);$q->execute($np);
    check_visibility($q->rowCount() === 3,'mark read excludes hidden notifications');
    // Exercise the actual external SQL adapter against isolated source fixtures.
    foreach ([
        'CREATE TABLE ext_customers(id INT PRIMARY KEY,name VARCHAR(100),email VARCHAR(100))',
        'CREATE TABLE ext_statuses(id INT PRIMARY KEY,name VARCHAR(100))',
        'CREATE TABLE ext_priorities(id INT PRIMARY KEY,name VARCHAR(100))',
        'CREATE TABLE ext_categories(id INT PRIMARY KEY,name VARCHAR(100))',
        'CREATE TABLE ext_threads(ticket INT,type VARCHAR(30),body TEXT,date_created DATETIME)',
        'CREATE TABLE ext_tickets(id INT PRIMARY KEY,subject VARCHAR(100),customer INT,status INT,priority INT,category INT,assigned_agent VARCHAR(100),date_created DATETIME,date_closed DATETIME)',
    ] as $sql) $pdo->prepare($sql)->execute();
    $pdo->prepare('INSERT INTO ext_customers VALUES(?,?,?)')->execute([1,'Fixture','fixture@example.test']);
    $pdo->prepare('INSERT INTO ext_statuses VALUES(?,?)')->execute([1,'Open']);
    $pdo->prepare('INSERT INTO ext_tickets(id,subject,customer,status) VALUES(?,?,?,?),(?,?,?,?)')->execute([90,'Hidden external',1,1,93,'Visible external',1,1]);
    $source=['label'=>'Fixture','filter'=>['customer_names'=>['Fixture']],'tables'=>[
        'tickets'=>'ext_tickets','customers'=>'ext_customers','statuses'=>'ext_statuses',
        'priorities'=>'ext_priorities','categories'=>'ext_categories','threads'=>'ext_threads',
    ]];
    check_visibility(array_column(external_ticket_rows($pdo,$source),'id') === [93],'external SQL adapter excludes configured ID');
    $_SESSION['role']='super-admin';
    $externalIds=array_column(external_ticket_rows($pdo,$source),'id');sort($externalIds);
    check_visibility($externalIds === [90,93],'external SQL adapter keeps both tickets for Super Admin');
    $_SESSION['role']='team-leader';
    // Restore by removing one ID; no ticket update is needed.
    $HIDDEN_TICKET_IDS=array_values(array_diff($originalIds,[90]));
    [$clause,$params]=hidden_ids_sql_clause();
    $q=$pdo->prepare('SELECT id FROM tickets WHERE '.$clause.' ORDER BY id');$q->execute($params);
    check_visibility(array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)) === [90,93],'config-only restoration');
    $pdo->prepare('TRUNCATE TABLE tickets')->execute();
    $HIDDEN_TICKET_IDS=[2,3];
    ticket_visibility_prepare_allocator($pdo);
    try {next_ticket_id($pdo);throw new RuntimeException('No transaction accepted');}
    catch(LogicException $e){echo "PASS: allocator requires caller transaction\n";}
    $pdo->beginTransaction();$id=next_ticket_id($pdo);
    check_visibility($id===1,'empty table starts at one');
    $pdo->prepare('INSERT INTO tickets(id,subject) VALUES(?,?)')->execute([$id,'first']);
    $worker=proc_open([PHP_BINARY,__FILE__,'worker',$database],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($worker)) throw new RuntimeException('Worker failed to start.');
    fclose($pipes[0]);
    check_visibility(trim((string)fgets($pipes[1]))==='READY','concurrent worker started');
    usleep(250000);
    check_visibility(proc_get_status($worker)['running'],'second allocator waits while first holds lock');
    $pdo->commit();
    $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($worker);
    check_visibility($exit===0 && trim($output)==='4' && $error==='','concurrent allocation skips 2 and 3 and returns unique ID 4');
    $pdo->beginTransaction();$id=next_ticket_id($pdo);$pdo->rollBack();
    check_visibility($id===5,'next ID after committed concurrent insert');
    $pdo->beginTransaction();$retry=next_ticket_id($pdo);$pdo->rollBack();
    check_visibility($retry===5,'rolled-back reservation is safely reusable');
    echo "All visibility integration checks passed.\n";
} finally {
    if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $pdo=null;
    $server->prepare('DROP DATABASE `'.$database.'`')->execute();
}