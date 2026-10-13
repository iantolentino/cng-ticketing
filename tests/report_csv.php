<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/report_csv.php';

$passed = 0;
function expect_csv(bool $condition, string $message): void {
    global $passed;
    if (!$condition) throw new RuntimeException($message);
    $passed++;
    echo "PASS: $message\n";
}
function parse_csv(string $csv, string $type = 'departments', array $headers = ['name', 'code']): array {
    $handle = fopen('php://memory', 'w+');
    fwrite($handle, $csv);
    rewind($handle);
    try { return report_csv_preview($handle, $type, $headers); }
    finally { fclose($handle); }
}
$result = parse_csv("name,code\nOperations,OPS\n");
expect_csv(count($result['rows']) === 1 && !$result['errors'], 'valid department file');
$result = parse_csv("\xEF\xBB\xBFname,code\r\nOperations,OPS\r\n\r\n");
expect_csv(count($result['rows']) === 1 && !$result['errors'], 'UTF-8 BOM and blank lines');
$result = parse_csv("name,code\n\"Operations, Support\",OPS\n");
expect_csv($result['rows'][0]['name'] === 'Operations, Support', 'quoted commas preserved');
expect_csv((bool) parse_csv("wrong,header\nA,B\n")['errors'], 'wrong headers rejected');
expect_csv((bool) parse_csv("name,code\nA,B,C\n")['errors'], 'extra columns rejected');
expect_csv((bool) parse_csv("name,code\nA\n")['errors'], 'missing columns rejected');
expect_csv((bool) parse_csv("name,code\n")['errors'], 'header-only file rejected');
expect_csv((bool) parse_csv("name,code\n,OPS\n")['errors'], 'required fields rejected');
expect_csv(!parse_csv("name,code\n" . str_repeat("Operations,OPS\n", 1000))['errors'], '1000-row boundary accepted');
expect_csv((bool) parse_csv("name,code\n" . str_repeat("Operations,OPS\n", 1001))['errors'], '1001 rows rejected without silent truncation');
$holidayHeaders = ['date','label','country_code'];
expect_csv((bool) parse_csv("date,label,country_code\n2026-02-30,Holiday,COMPANY\n", 'holidays', $holidayHeaders)['errors'], 'invalid calendar date rejected');
expect_csv(!parse_csv("date,label,country_code\n2028-02-29,Holiday,COMPANY\n", 'holidays', $holidayHeaders)['errors'], 'valid leap date accepted');
expect_csv((bool) parse_csv("name,code\nA,B\n", 'tickets')['errors'], 'unsupported ticket import rejected');
$result = parse_csv("name,code\nGood,G\nBad\n");
expect_csv(count($result['rows']) === 1 && count($result['errors']) === 1, 'mixed file retains sample rows and errors');

function request_string(array $source, string $key): string { return is_scalar($source[$key] ?? null) ? (string) $source[$key] : ''; }
function verify_csrf(): void {}
function redirect(string $url): void { $GLOBALS['csv_test_redirect'] = $url; }
function audit_admin_action(...$args): void {}
class CsvTestDatabase {
    public bool $started = false;
    public bool $committed = false;
    public bool $rolledBack = false;
    public bool $fail = false;
    public int $executions = 0;
    public function beginTransaction(): void { $this->started = true; }
    public function commit(): void { $this->committed = true; }
    public function inTransaction(): bool { return $this->started && !$this->committed && !$this->rolledBack; }
    public function rollBack(): void { $this->rolledBack = true; }
    public function prepare(string $sql): self { return $this; }
    public function execute(array $values): void {
        if ($this->fail) throw new RuntimeException('Test database failure');
        $this->executions++;
    }
}
function run_import_case(array $previewState, array $post, bool $fail = false): array {
    $source = file_get_contents(dirname(__DIR__) . '/reports.php');
    $start = strpos($source, "\$error = '';");
    $end = strpos($source, '$from =', $start);
    $_SESSION = ['csv_preview' => $previewState];
    $_POST = $post; $_GET = []; $_FILES = []; $_SERVER['REQUEST_METHOD'] = 'POST';
    $canImportReports = true; $user = ['id'=>1]; $pdo = new CsvTestDatabase(); $pdo->fail = $fail;
    $GLOBALS['csv_test_redirect'] = '';
    eval(substr($source, $start, $end - $start));
    return ['db'=>$pdo, 'error'=>$error, 'preview'=>$preview, 'session'=>$_SESSION, 'redirect'=>$GLOBALS['csv_test_redirect']];
}
$valid = ['type'=>'departments','rows'=>[['name'=>'Test','code'=>'TEST']], 'errors'=>[], 'token'=>'current'];
$result = run_import_case($valid, ['action'=>'import','preview_token'=>'old']);
expect_csv(!$result['db']->started && str_contains($result['error'],'expired'), 'stale preview token cannot import');
$invalid = $valid; $invalid['errors'] = ['Bad row'];
$result = run_import_case($invalid, ['action'=>'import','preview_token'=>'current']);
expect_csv(!$result['db']->started && $result['error'] !== '', 'mixed-validity file cannot partially import');
$result = run_import_case($valid, ['action'=>'cancel','preview_token'=>'current']);
expect_csv(!$result['db']->started && !isset($result['session']['csv_preview']), 'discard clears preview without writes');
$result = run_import_case($valid, ['action'=>'preview','type'=>'departments']);
expect_csv(!$result['db']->started && !isset($result['session']['csv_preview']) && $result['error'] !== '', 'failed upload clears older preview');
$result = run_import_case($valid, ['action'=>'import','preview_token'=>'current']);
expect_csv($result['db']->committed && $result['db']->executions === 1 && !isset($result['session']['csv_preview']) && isset($result['session']['csv_import_notice']) && $result['redirect'] === 'reports.php#csv-import', 'valid import commits, clears preview, sets success and redirects');
$result = run_import_case($valid, ['action'=>'import','preview_token'=>'current'], true);
expect_csv($result['db']->rolledBack && !$result['db']->committed && $result['error'] !== '', 'database failure rolls back and reports failure');
$unsupported = $valid; $unsupported['type'] = 'tickets';
$result = run_import_case($unsupported, ['action'=>'import','preview_token'=>'current']);
expect_csv(!$result['db']->started, 'old ticket preview cannot produce a false import success');
echo "$passed CSV behavior checks passed\n";