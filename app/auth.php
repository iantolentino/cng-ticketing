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
    $user = $q->fetch() ?: null;
    $_SESSION['role'] = $user['role_slug'] ?? null;
    return $user;
}
function refresh_current_user(): ?array {
    return current_user(true);
}
/**
 * "UPDATE ... SET" fragment that writes a user's enabled state together with users.disabled_at.
 *
 * Disabling stamps NOW(), enabling clears the stamp, and re-saving an account that is already
 * disabled keeps its original stamp — otherwise every unrelated edit would rewrite the
 * "last disabled" date. An account can therefore never be disabled without a disable timestamp,
 * nor re-enabled while still carrying one.
 *
 * The fragment always binds users.disabled_at, in both directions, so the caller's parameter
 * list length never depends on which direction the state is moving.
 *
 * @param string|null $currentDisabledAt users.disabled_at of the row being written.
 * @return array{0:string,1:array<int,string|null>} SQL fragment plus the two parameters it expects.
 */
function user_active_state_update_sql(bool $isActive, ?string $currentDisabledAt = null): array
{
    return $isActive
        ? ['is_active=?, disabled_at=?', [1, null]]
        : ['is_active=?, disabled_at=COALESCE(?, NOW())', [0, $currentDisabledAt]];
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
function require_non_team_member(array $user): array {
    if (($user['role_slug'] ?? '') === 'team-member') { http_response_code(403); exit('This workspace is not available to Team Members.'); }
    return $user;
}
