<?php
declare(strict_types=1);

const ROOT_PATH = __DIR__ . '/..';
$configFile = ROOT_PATH . '/config/config.local.php';
if (!is_file($configFile)) {
    $configFile = ROOT_PATH . '/config/config.example.php';
}
$config = require $configFile;
$externalConfigFile = ROOT_PATH . '/config/config.external.php';
if (is_file($externalConfigFile)) {
    $externalConfig = require $externalConfigFile;
    if (is_array($externalConfig)) $config = array_replace_recursive($config, $externalConfig);
}
$sessionPath = ROOT_PATH . '/.local/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0775, true);
}
session_save_path($sessionPath);
session_name($config['app']['session_name']);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params(['path' => '/', 'httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/logging.php';
