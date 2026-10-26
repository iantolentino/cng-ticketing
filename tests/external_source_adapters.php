<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/external_tickets.php';

$config = require __DIR__ . '/../config/config.local.php';
$db = $config['db'];
if (!in_array($db['host'], ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('External adapter fixture test requires a local database.');
}
$pdo = db();
$sources = [
    ['key'=>'stratast_support', 'prefix'=>'92CpH3vC', 'label'=>'IT Department'],
    ['key'=>'stratast_escalations', 'prefix'=>'Qeby0uHyy', 'label'=>'Escalations'],
    ['key'=>'stratast_wp346', 'prefix'=>'wpnm', 'label'=>'Learning'],
    ['key'=>'stratast_requisition', 'prefix'=>'Qeby0uHyy', 'label'=>'Requisition'],
];

foreach (array_unique(array_column($sources, 'prefix')) as $prefix) {
    $tables = [];
    foreach (['tickets','customers','statuses','priorities','categories','threads'] as $type) {
        $tables[$type] = $prefix . '_psmsc_' . $type;
    }
    $pdo->exec("CREATE TEMPORARY TABLE `{$tables['customers']}` (id INT PRIMARY KEY,name VARCHAR(100),email VARCHAR(100))");
    $pdo->exec("CREATE TEMPORARY TABLE `{$tables['statuses']}` (id INT PRIMARY KEY,name VARCHAR(50))");
    $pdo->exec("CREATE TEMPORARY TABLE `{$tables['priorities']}` (id INT PRIMARY KEY,name VARCHAR(50))");
    $pdo->exec("CREATE TEMPORARY TABLE `{$tables['categories']}` (id INT PRIMARY KEY,name VARCHAR(80))");
    $pdo->exec("CREATE TEMPORARY TABLE `{$tables['threads']}` (ticket INT,type VARCHAR(30),body TEXT,date_created DATETIME)");
    $pdo->exec("CREATE TEMPORARY TABLE `{$tables['tickets']}` (id INT PRIMARY KEY,subject VARCHAR(100),customer INT,status INT,priority INT,category INT,assigned_agent VARCHAR(100),date_created DATETIME,date_closed DATETIME NULL)");
    $pdo->exec("INSERT INTO `{$tables['customers']}` VALUES (1,'Fixture Customer','fixture@example.com')");
    $pdo->exec("INSERT INTO `{$tables['statuses']}` VALUES (1,'Open')");
    $pdo->exec("INSERT INTO `{$tables['priorities']}` VALUES (1,'Normal')");
    $pdo->exec("INSERT INTO `{$tables['categories']}` VALUES (1,'Fixture')");
    $pdo->exec("INSERT INTO `{$tables['tickets']}` VALUES (90,'Hidden fixture',1,1,1,1,'Agent 1','2026-10-01 00:00:00',NULL),(93,'Visible fixture',1,1,1,1,'Agent 2','2026-10-02 00:00:00',NULL)");
    $pdo->exec("INSERT INTO `{$tables['threads']}` VALUES (93,'report','Fixture response','2026-10-02 01:00:00'),(93,'note','Not a customer response','2026-10-02 02:00:00')");
}

$originalHiddenIds = $HIDDEN_TICKET_IDS;
$originalRole = $_SESSION['role'] ?? null;
$HIDDEN_TICKET_IDS = [90];
try {
    foreach ($sources as $sourceInfo) {
        $prefix = $sourceInfo['prefix'];
        $source = [
            'label' => $sourceInfo['label'],
            'tables' => array_combine(
                ['tickets','customers','statuses','priorities','categories','threads'],
                array_map(static fn(string $type): string => $prefix . '_psmsc_' . $type, ['tickets','customers','statuses','priorities','categories','threads'])
            ),
            'filter' => ['customer_names'=>['Fixture Customer']],
        ];
        $listFunction = 'fetch_' . $sourceInfo['key'] . '_tickets';
        $threadFunction = 'fetch_' . $sourceInfo['key'] . '_thread';

        $_SESSION['role'] = 'team-leader';
        $visible = $listFunction($pdo, $source);
        if (array_column($visible, 'id') !== [93]) throw new RuntimeException($sourceInfo['label'] . ' list filtering failed.');
        $thread = $threadFunction($pdo, $source, 93);
        if (count($thread) !== 1 || $thread[0]['body'] !== 'Fixture response') throw new RuntimeException($sourceInfo['label'] . ' thread filtering failed.');

        $_SESSION['role'] = 'super-admin';
        $adminIds = array_column($listFunction($pdo, $source), 'id');
        sort($adminIds);
        if ($adminIds !== [90,93]) throw new RuntimeException($sourceInfo['label'] . ' Super Admin visibility failed.');
        echo 'PASS: ' . $sourceInfo['label'] . ' list, thread and role filtering.' . PHP_EOL;
    }
} finally {
    $HIDDEN_TICKET_IDS = $originalHiddenIds;
    $_SESSION['role'] = $originalRole;
}
