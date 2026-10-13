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
const APP_DISPLAY_TIMEZONE = 'Asia/Manila';
function app_datetime_display(?string $value, string $format = 'M j, Y · g:i A', string $sourceTimezone = 'UTC'): string
{
    $value = trim((string) $value);
    if ($value === '') return '—';
    try {
        return (new DateTimeImmutable($value, new DateTimeZone($sourceTimezone)))->setTimezone(new DateTimeZone(APP_DISPLAY_TIMEZONE))->format($format);
    } catch (Throwable) {
        return $value;
    }
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
require_once ROOT_PATH . '/ticket_visibility.php';
// Role is refreshed from the authenticated database record by current_user().
unset($_SESSION['role']);
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/pagination.php';
require_once __DIR__ . '/logging.php';
