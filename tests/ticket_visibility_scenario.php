<?php
/** Exact 15-ticket acceptance scenario; CLI only, isolated schema and application copy. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$project = dirname(__DIR__);
if (($argv[1] ?? '') === 'page') {
    $root=$argv[2]; $role=$argv[3]; $page=$argv[4];
    if (!preg_match('/visibility-15-[a-f0-9]{12}$/',$root)) exit(2);
    chdir($root);$cfg=require $root.'/config/config.local.php';
    require_once $root.'/app/security.php';
    $sessions=app_runtime_directory($root).'/sessions';
    if(!is_dir($sessions))mkdir($sessions,0700,true);
    session_save_path($sessions);session_name($cfg['app']['session_name']);
    session_id('scenario'.bin2hex(random_bytes(12)));session_start();
    $_SESSION['user_id']=$role==='super-admin'?2:1;
    $fixtureDb=$cfg['db'];
    $fixturePdo=new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$fixtureDb['host'],$fixtureDb['port'],$fixtureDb['database']),$fixtureDb['username'],$fixtureDb['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $fixtureUser=$fixturePdo->prepare('SELECT password_hash FROM users WHERE id=?');
    $fixtureUser->execute([$_SESSION['user_id']]);
    $_SESSION['auth_password_hash']=$fixtureUser->fetchColumn();
    $fixturePdo=null;
    session_write_close();
    $_SERVER['REQUEST_METHOD']='GET';$_SERVER['PHP_SELF']='/'.$page;$_SERVER['SCRIPT_FILENAME']=$root.'/'.$page;
    $_SERVER['HTTP_HOST']='localhost';$_SERVER['SERVER_NAME']='localhost';$_SERVER['REMOTE_ADDR']='127.0.0.1';
    $_GET=[];parse_str($argv[5]??'',$_GET);
    ob_start();
    register_shutdown_function(static function()use($root,$page,$role):void{
        $html=(string)ob_get_clean();$error=error_get_last();
        $fatal=$error&&in_array($error['type'],[E_ERROR,E_PARSE,E_COMPILE_ERROR],true);
        $status=http_response_code()?:200;$doc=new DOMDocument();@$doc->loadHTML($html?:'<html></html>');$xp=new DOMXPath($doc);
        $ids=[];foreach($xp->query('//td[contains(concat(" ",normalize-space(@class)," ")," ticket-id ")]') as $cell){if(preg_match('/#(\d+)/',$cell->textContent,$m))$ids[]=(int)$m[1];}
        $links=[];foreach($xp->query('//a[@href]') as $a){$id=function_exists('ticket_visibility_url_id')?ticket_visibility_url_id($a->getAttribute('href')):null;if($id!==null)$links[]=$id;}
        $headers=[];foreach($xp->query('//table[contains(@class,"ticket-table")]//th') as $th)$headers[]=trim($th->textContent);
        $classes=[];foreach($xp->query('//table[contains(@class,"ticket-table")]/tbody/tr[1]/td') as $td)$classes[]=$td->getAttribute('class');
        $result=['page'=>$page,'role'=>$role,'status'=>$status,'fatal'=>(bool)$fatal,'ids'=>$ids,'links'=>$links,'row_handlers'=>$xp->query('//*[@data-ticket-href]')->length,
            'disabled_links'=>$xp->query('//a[@aria-disabled="true" and not(@href)]')->length,'headers'=>$headers,'cell_classes'=>$classes,
            'total'=>$GLOBALS['totalTickets']??$GLOBALS['dashboardTotal']??null,'pages'=>$GLOBALS['pagination']['totalPages']??null,
            'comments'=>isset($GLOBALS['recentComments'])?count($GLOBALS['recentComments']):null,
            'response'=>$status!==200?$html:null,'csv_rows'=>$page==='export-tickets.php'?max(0,count(preg_split('/\r?\n/',trim($html)))-1):null];
        file_put_contents($root.'/evidence/'.str_replace('/','-',$page).'-'.$role.'.html',$html);
        if(session_status()===PHP_SESSION_ACTIVE){$_SESSION=[];session_destroy();}
        echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
    });
    require $root.'/'.$page;exit;
}
$checks=[];
function scenario_check(bool $ok,string $message):void{
    global $checks;$checks[]=['passed'=>$ok,'check'=>$message];
    echo ($ok?'PASS: ':'FAIL: ').$message.PHP_EOL;
    if(!$ok)throw new RuntimeException($message);
}
function scenario_page(string $root,string $role,string $page,string $query=''):array{
    $process=proc_open([PHP_BINARY,__FILE__,'page',$root,$role,$page,$query],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Cannot start page worker');
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    $result=json_decode(trim($out),true);
    if($exit!==0||!is_array($result)||$result['fatal']||$err!=='')throw new RuntimeException('Page failed: '.$page.' '.substr($err,0,500));
    return $result;
}
$config=require $project.'/config/config.local.php';$db=$config['db'];
if(!in_array($db['host'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Local database required');
$pdo=new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$db['host'],$db['port'],$db['database']),$db['username'],$db['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$token=bin2hex(random_bytes(6));$database='cng_visibility_15_'.$token;$root=$project.'/.local/visibility-15-'.$token;
$sourceDb='`'.str_replace('`','``',$db['database']).'`';
$originalConfigHash=hash_file('sha256',$project.'/ticket_visibility.php');
$designFiles=glob($project.'/assets/css/*.css');$designFiles=array_merge($designFiles,glob($project.'/assets/js/*.js'));$designHashes=[];
foreach($designFiles as $file)$designHashes[$file]=hash_file('sha256',$file);
mkdir($root,0775,true);foreach(['app','config','.local/sessions','storage/private/ticket-attachments','evidence'] as $dir)mkdir($root.'/'.$dir,0775,true);
foreach(array_merge(glob($project.'/*.php'),glob($project.'/app/*.php')) as $file){$rel=substr($file,strlen($project)+1);copy($file,$root.'/'.$rel);}
file_put_contents($root.'/config/config.local.php',"<?php\n\$config=require ".var_export($project.'/config/config.local.php',true).";\n\$config['db']['database']=".var_export($database,true).";\n\$config['external_sources']=[];\n\$config['smtp']['host']='';\nreturn \$config;\n");
$helper=file_get_contents($project.'/ticket_visibility.php');
file_put_contents($root.'/ticket_visibility.php',$helper."\n\$HIDDEN_TICKET_IDS=[];\n\$LOCKED_TICKET_IDS=[];\n");
$pdo->prepare('CREATE DATABASE `'.$database.'`')->execute();$created=true;
try {
    $tables=$pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE'");$tables->execute([$db['database']]);
    foreach($tables->fetchAll(PDO::FETCH_COLUMN) as $table){$quoted='`'.str_replace('`','``',$table).'`';$pdo->prepare('CREATE TABLE `'.$database.'`.'.$quoted.' LIKE '.$sourceDb.'.'.$quoted)->execute();}
    // Copy only application reference settings; no original users or tickets are copied.
    foreach(['roles','permissions','role_permissions','departments','sla_rules','app_settings'] as $table)$pdo->prepare('INSERT INTO `'.$database.'`.`'.$table.'` SELECT * FROM '.$sourceDb.'.`'.$table.'`')->execute();
    $pdo->prepare('USE `'.$database.'`')->execute();
    $dept=(int)$pdo->query('SELECT id FROM departments ORDER BY id LIMIT 1')->fetchColumn();
    foreach(['cng-admin'=>1,'super-admin'=>2] as $role=>$id){$q=$pdo->prepare('SELECT id FROM roles WHERE slug=?');$q->execute([$role]);$roleId=(int)$q->fetchColumn();$pdo->prepare('INSERT INTO users(id,role_id,department_id,username,full_name,password_hash,must_change_password,is_active) VALUES(?,?,?,?,?,?,0,1)')->execute([$id,$roleId,$dept,'visibility_fixture_'.$id,'Visibility Fixture '.$role,password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT)]);}
    require_once $project.'/ticket_visibility.php';
    ticket_visibility_prepare_allocator($pdo);$pdo->beginTransaction();
    for($i=1;$i<=15;$i++){
        $id=next_ticket_id($pdo);
        $pdo->prepare('INSERT INTO tickets(id,ticket_number,issue_escalator,subject,category,department_id,employee_name,description,status,assignee_id,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$id,'LOCAL-15-'.$i,'Local QA','Local test ticket '.$i,'Performance',$dept,'Fixture Employee','Private fixture description '.$i,'open',1,2]);
        $pdo->prepare('INSERT INTO ticket_comments(ticket_id,user_id,body) VALUES(?,?,?)')->execute([$id,2,'PRIVATE-FIXTURE-COMMENT-'.$i]);
        $pdo->prepare('INSERT INTO notifications(user_id,actor_id,type,title,body,url) VALUES(?,?,?,?,?,?)')->execute([1,2,'assignment','Fixture ticket '.$i,'PRIVATE-FIXTURE-NOTIFICATION-'.$i,'ticket.php?id='.$id]);
        $pdo->prepare('INSERT INTO ticket_attachments(ticket_id,uploaded_by,file_name,file_path,stored_name,original_name,mime_type,file_size) VALUES(?,?,?,?,?,?,?,?)')->execute([$id,2,'fixture.txt','storage/private/ticket-attachments/fixture.txt','fixture.txt','fixture.txt','text/plain',7]);
    }
    $pdo->commit();file_put_contents($root.'/storage/private/ticket-attachments/fixture.txt','fixture');
    scenario_check((int)$pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn()===15,'Created exactly 15 tickets in isolated local application database');
    $baseline=scenario_page($root,'cng-admin','index.php');$baseDash=scenario_page($root,'cng-admin','dashboard.php');
    scenario_check($baseline['status']===200&&$baseline['total']===15&&$baseline['pages']===2,'Before restrictions: CNG Admin list counts 15 across two pages');
    scenario_check($baseDash['total']===15,'Before restrictions: CNG Admin dashboard counts 15');
    scenario_check(count($baseline['links'])>=10&&$baseline['row_handlers']===10,'Before restrictions: visible ticket rows are clickable');
    // Only the isolated application's configuration changes.
    file_put_contents($root.'/ticket_visibility.php',$helper."\n\$HIDDEN_TICKET_IDS=range(1,5);\n\$LOCKED_TICKET_IDS=range(6,15);\n");
    $after=scenario_page($root,'cng-admin','index.php');$afterDash=scenario_page($root,'cng-admin','dashboard.php');
    $ids=$after['ids'];sort($ids);
    scenario_check($after['total']===10&&$after['pages']===1&&$ids===range(6,15),'After restrictions: exactly 10 listed tickets; first 5 absent; one page');
    scenario_check($afterDash['total']===10,'After restrictions: dashboard total is exactly 10');
    scenario_check($after['links']===[]&&$after['row_handlers']===0&&$after['disabled_links']===20,'All 10 remaining rows and their 20 ID/title links have no navigation');
    scenario_check($afterDash['links']===[]&&$afterDash['comments']===0,'Dashboard does not link to locked tickets or expose their comment previews');
    scenario_check($baseline['headers']===$after['headers']&&$baseline['cell_classes']===$after['cell_classes'],'Ticket headings, column order and styling classes are unchanged');
    $admin=scenario_page($root,'super-admin','index.php');$adminDash=scenario_page($root,'super-admin','dashboard.php');
    scenario_check($admin['total']===15&&$adminDash['total']===15&&$admin['row_handlers']===10,'Super Admin still sees all 15 and retains clickable rows');
    $missing=scenario_page($root,'cng-admin','ticket.php','id=999999');
    for($id=1;$id<=15;$id++){
        $denied=scenario_page($root,'cng-admin','ticket.php','id='.$id);
        scenario_check($denied['status']===404&&$denied['response']===$missing['response'],'CNG Admin direct URL #'.$id.' matches nonexistent-ticket 404');
        $allowed=scenario_page($root,'super-admin','ticket.php','id='.$id);
        scenario_check($allowed['status']===200,'Super Admin can open fixture #'.$id);
    }
    $attachment=scenario_page($root,'cng-admin','download-attachment.php','id=6');$missingAttachment=scenario_page($root,'cng-admin','download-attachment.php','id=999999');
    scenario_check($attachment['status']!==200&&$attachment['status']===$missingAttachment['status']&&$attachment['response']===$missingAttachment['response'],'Locked attachment is denied like a nonexistent attachment');
    $export=scenario_page($root,'cng-admin','export-tickets.php');
    scenario_check($export['status']===200&&$export['csv_rows']===10,'CNG Admin summary CSV includes the 10 listed tickets only');
    $restoredHelper=$helper."\n\$HIDDEN_TICKET_IDS=[];\n\$LOCKED_TICKET_IDS=[];\n";file_put_contents($root.'/ticket_visibility.php',$restoredHelper);
    $restored=scenario_page($root,'cng-admin','index.php');
    scenario_check($restored['total']===15&&$restored['row_handlers']===10,'Removing restrictions restores all 15 and their navigation');
    scenario_check(hash_file('sha256',$project.'/ticket_visibility.php')===$originalConfigHash,'Real ticket configuration never changed during scenario');
    foreach($designHashes as $file=>$hash)if(hash_file('sha256',$file)!==$hash)throw new RuntimeException('Design asset changed');
    scenario_check(true,'All CSS and JavaScript files unchanged');
} finally {
    if($pdo->inTransaction())$pdo->rollBack();
    $pdo->prepare('USE '.$sourceDb)->execute();
    if($created)$pdo->prepare('DROP DATABASE `'.$database.'`')->execute();
    $report=['scenario'=>'15 local tickets: hide 5, list/count 10 without navigation','database_removed'=>true,'application_copy'=>$root,'checks'=>$checks];
    file_put_contents($project.'/.local/ticket-visibility-15-results.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}
echo 'SCENARIO PASSED: '.count($checks).' checks; disposable database removed; real tickets untouched.'.PHP_EOL;
