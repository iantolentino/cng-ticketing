<?php
declare(strict_types=1);

const ROOT_PATH = __DIR__ . '/..';
$configFile = ROOT_PATH . '/config/config.local.php';
if (!is_file($configFile)) {
    $configFile = ROOT_PATH . '/config/config.example.php';
}
$config = require $configFile;
session_name($config['app']['session_name']);
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';

