<?php
require __DIR__ . '/app/bootstrap.php';
$admin = require_login();
if (($admin['role_slug'] ?? '') !== 'super-admin') { http_response_code(403); exit('Super Admin access required.'); }
redirect('audit-log.php');
