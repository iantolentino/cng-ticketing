<?php
declare(strict_types=1);

const ROOT_PATH = __DIR__ . '/..';
$httpsEnabled = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store, private');
if ($httpsEnabled) header('Strict-Transport-Security: max-age=31536000');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
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
require_once __DIR__ . '/security.php';
$sessionPath = app_runtime_directory(ROOT_PATH) . '/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0700, true);
}
if (DIRECTORY_SEPARATOR === '/') {
    @chmod(dirname($sessionPath), 0700);
    @chmod($sessionPath, 0700);
}
session_save_path($sessionPath);
session_name($config['app']['session_name']);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.gc_maxlifetime', '2592000');
session_set_cookie_params(['lifetime' => 2592000, 'path' => '/', 'httponly' => true, 'secure' => $httpsEnabled, 'samesite' => 'Lax']);
session_start();
setcookie(session_name(), session_id(), ['expires' => time() + 2592000, 'path' => '/', 'httponly' => true, 'secure' => $httpsEnabled, 'samesite' => 'Lax']);
require_once ROOT_PATH . '/ticket_visibility.php';
// Role is refreshed from the authenticated database record by current_user().
unset($_SESSION['role']);
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/pagination.php';
require_once __DIR__ . '/logging.php';
