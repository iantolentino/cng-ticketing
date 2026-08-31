<?php
require __DIR__ . '/app/bootstrap.php';
require_permission('manage_users');
redirect('reports.php#csv-import');
