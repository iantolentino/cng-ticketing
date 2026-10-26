<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/bootstrap.php';
$pdo=db();
foreach (['users','admin_audit_log'] as $table) {
    $sql=$pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
    $sql=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$sql);
    $sql=preg_replace('/^\s+CONSTRAINT .*\n/m','',$sql);
    $pdo->exec(preg_replace('/,\n\)/',"\n)",$sql));
}
$user=['id'=>9999,'role_slug'=>'super-admin']; $canImportReports=true;
$source=file_get_contents(__DIR__.'/../reports.php'); $start=strpos($source,"\$error = '';");
$code=substr($source,$start,strpos($source,'$from =',$start)-$start);
$code=preg_replace('/redirect\((.*?)\);/s','$testRedirect = true;',$code);
$_GET=[]; $_FILES=[]; $_SERVER['REQUEST_METHOD']='POST';
$_SESSION['csv_preview']=['type'=>'users','token'=>'fixture','errors'=>[],'rows'=>[['full_name'=>'Credential fixture','username'=>'credential-fixture','email'=>'fixture@example.com','role'=>'team-member','department'=>'']]];
$_POST=['csrf_token'=>csrf_token(),'action'=>'import','preview_token'=>'fixture']; $testRedirect=false;
eval($code);
$credentials=$_SESSION['csv_import_credentials'] ?? [];
$created=$pdo->query('SELECT password_hash,must_change_password FROM users')->fetch();
if (!$testRedirect || count($credentials)!==1 || !$created || !password_verify($credentials[0]['password'],$created['password_hash']) || (int)$created['must_change_password']!==1) throw new RuntimeException('Imported credential handoff failed.');
$history=(string)$pdo->query('SELECT details FROM admin_audit_log')->fetchColumn();
if (str_contains($history,$credentials[0]['password']) || str_contains($history,$created['password_hash'])) throw new RuntimeException('Password was stored in audit history.');
$_SERVER['REQUEST_METHOD']='GET'; $_POST=[]; eval($code);
if (count($importCredentials)!==1 || isset($_SESSION['csv_import_credentials'])) throw new RuntimeException('Credentials were not consumed once.');
echo "PASS: imported temporary password matches stored hash, first-login change required, one-time display and no credential in history.\n";
