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
