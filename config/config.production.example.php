<?php

// Copy this file to config.local.php on the production server and replace
// the placeholder values with the live database and SMTP credentials.
// Do not commit or upload the completed config.local.php file.
return [
    'app' => [
        'name' => 'CNG / Jamesons Issues',
        'base_url' => '',
        'session_name' => 'cng_ticketing_session',
    ],
    'db' => [
        'host' => 'LIVE_DATABASE_HOST',
        'port' => '3306',
        'database' => 'LIVE_DATABASE_NAME',
        'username' => 'LIVE_DATABASE_USERNAME',
        'password' => 'LIVE_DATABASE_PASSWORD',
    ],
    'smtp' => [
        'host' => 'LIVE_SMTP_HOST',
        'port' => 587,
        'username' => 'LIVE_SMTP_USERNAME',
        'password' => 'LIVE_SMTP_PASSWORD',
        'encryption' => 'tls',
        'from_email' => 'LIVE_FROM_EMAIL',
        'from_name' => 'CNG / Jamesons Issues',
    ],
];
