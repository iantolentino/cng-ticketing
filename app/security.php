<?php
declare(strict_types=1);

function e(?string $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function request_string(array $source, string $key): string { $value = $source[$key] ?? ''; return is_string($value) ? $value : ''; }
function csrf_token(): string { return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32)); }
function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) { http_response_code(419); exit('Your session form token is invalid. Please refresh and try again.'); }
}
function redirect(string $path): never { header('Location: ' . $path); exit; }

/** Private runtime state lives outside the served application directory. */
function app_runtime_directory(string $root): string
{
    return rtrim(sys_get_temp_dir(), '/\\') . '/cng-ticketing-' . hash('sha256', (string) (realpath($root) ?: $root));
}

/** Persist failed sign-in counts per remote IP without storing usernames or IPs in filenames. */
function login_throttle_update(string $action): int
{
    $directory = app_runtime_directory(ROOT_PATH) . '/login-throttle';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Login throttle storage is unavailable.');
    }
    if (DIRECTORY_SEPARATOR === '/') @chmod($directory, 0700);
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $path = $directory . '/' . hash('sha256', $ip) . '.json';
    $handle = fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        throw new RuntimeException('Login throttle storage is unavailable.');
    }
    if (DIRECTORY_SEPARATOR === '/') @chmod($path, 0600);
    try {
        $contents = stream_get_contents($handle);
        $state = is_string($contents) ? json_decode($contents, true) : null;
        $now = time();
        if (!is_array($state) || !isset($state['started'], $state['attempts']) || $now - (int) $state['started'] >= 900) {
            $state = ['started' => $now, 'attempts' => 0];
        }
        if ($action === 'failure') $state['attempts']++;
        if ($action === 'success') $state = ['started' => $now, 'attempts' => 0];
        ftruncate($handle, 0);
        rewind($handle);
        if (fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR)) === false) {
            throw new RuntimeException('Login throttle storage is unavailable.');
        }
        return (int) $state['attempts'] >= 8 ? max(1, 900 - ($now - (int) $state['started'])) : 0;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function private_attachment_path(?string $relativePath, string $collection): ?string
{
    $relativePath = str_replace('\\', '/', trim((string) $relativePath));
    if (!in_array($collection, ['ticket-attachments', 'leave-attachments'], true)
        || $relativePath === '' || str_contains($relativePath, "\0")
        || str_contains('/' . $relativePath . '/', '/../')
        || !str_starts_with($relativePath, 'storage/private/' . $collection . '/')) return null;
    $privateRoot = realpath(ROOT_PATH . '/storage/private');
    $path = realpath(ROOT_PATH . '/' . $relativePath);
    if ($privateRoot === false || $path === false || !is_file($path)) return null;
    $prefix = rtrim($privateRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return str_starts_with($path, $prefix) ? $path : null;
}

function password_meets_policy(string $password): bool
{
    return strlen($password) >= 12 && strlen($password) <= 72;
}
