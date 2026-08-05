<?php
declare(strict_types=1);

function app_is_setup(): bool {
    try { $q = db()->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'setup_complete'"); $q->execute(); return $q->fetchColumn() === '1'; } catch (PDOException) { return false; }
}
function current_user(bool $refresh = false): ?array {
    static $user = false;
    if ($refresh) $user = false;
    if ($user !== false) return $user;
    if (empty($_SESSION['user_id'])) return $user = null;
    $q = db()->prepare('SELECT u.*, r.name AS role_name, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.is_active = 1');
    $q->execute([$_SESSION['user_id']]);
    return $user = ($q->fetch() ?: null);
}
function refresh_current_user(): ?array {
    return current_user(true);
}
function user_can(string $permissionKey): bool {
    $user = current_user(); if ($user === null) return false;
    $q = db()->prepare('SELECT COALESCE(uo.granted, rp.granted, 0) FROM permissions p LEFT JOIN role_permissions rp ON rp.permission_id = p.id AND rp.role_id = :role_id LEFT JOIN user_permission_overrides uo ON uo.permission_id = p.id AND uo.user_id = :user_id WHERE p.permission_key = :permission_key');
    $q->execute(['role_id' => $user['role_id'], 'user_id' => $user['id'], 'permission_key' => $permissionKey]);
    return (bool) $q->fetchColumn();
}
function default_landing_page(): string {
    if (user_can('view_all_tickets')) return 'dashboard.php';
    if (user_can('access_leave_request_module')) return 'leave-requests.php';
    return 'change-password.php';
}
function require_login(): array {
    $user = current_user(); if ($user === null) redirect('login.php');
    if ($user['must_change_password'] && basename($_SERVER['PHP_SELF']) !== 'change-password.php') redirect('change-password.php');
    return $user;
}
function require_permission(string $permissionKey): array {
    $user = require_login(); if (!user_can($permissionKey)) { http_response_code(403); exit('You do not have permission to access this action.'); } return $user;
}
