<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/bootstrap.php';
require __DIR__.'/../app/roster.php';
$pdo=db();
foreach (['calendar_events','company_holidays','admin_audit_log'] as $table) {
    $sql=$pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
    $sql=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$sql);
    $sql=preg_replace('/^\s+CONSTRAINT .*\n/m','',$sql);
    $pdo->exec(preg_replace('/,\n\)/',"\n)",$sql));
}
$user=['id'=>9999,'role_slug'=>'super-admin']; $canManageCalendar=roster_can_manage_calendar($user);
$month='2026-11'; $eventTypes=['team_event'=>'Team event']; $holidayCountries=['COMPANY'=>'Company'];
$_SERVER['REQUEST_METHOD']='POST'; $_GET=[];
$source=file_get_contents(__DIR__.'/../team-calendar.php');
$start=strpos($source,'$postedDate =');
$code=substr($source,$start,strpos($source,'$notices =')-$start);
$code=preg_replace('/redirect\((.*?)\);/s','$testRedirect = true;',$code);
$valid=['csrf_token'=>csrf_token(),'action'=>'add_event','title'=>'Fixture','event_type'=>'team_event','event_date'=>'2026-11-01'];
$_POST=$valid; $error=''; $testRedirect=false; eval($code);
if ($error!=='' || !$testRedirect || (int)$pdo->query('SELECT COUNT(*) FROM calendar_events')->fetchColumn()!==1 || (int)$pdo->query('SELECT COUNT(*) FROM admin_audit_log')->fetchColumn()!==1) throw new RuntimeException('Event/history save failed.');
$pdo->exec("ALTER TABLE admin_audit_log ADD CONSTRAINT fixture_reject_holiday_audit CHECK(action <> 'company_holiday_saved')");
$_POST=['csrf_token'=>csrf_token(),'action'=>'add_holiday','label'=>'Fixture holiday','country_code'=>'COMPANY','date'=>'2026-11-02'];
$error=''; $testRedirect=false; eval($code);
if ($error==='' || $testRedirect || (int)$pdo->query('SELECT COUNT(*) FROM company_holidays')->fetchColumn()!==0) throw new RuntimeException('Failed history did not roll back holiday.');
if (roster_can_manage_calendar(['role_slug'=>'cng-admin']) || roster_can_manage_calendar(['role_slug'=>'team-member']) || !roster_can_manage_calendar(['role_slug'=>'team-leader'])) throw new RuntimeException('Calendar role boundary failed.');
echo "PASS: calendar save/history commit together, failed history rolls back holiday, CNG Admin/Team Member are read-only, TL retains CRUD.\n";
