<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
$superAdminUsername = 'superadmin@stratastaff.com';
$superAdminName = 'Super Admin';
$temporaryPassword = 'superadmin2026!';
$pdo = db();
try {
    $pdo->beginTransaction();
    $roleId = (int) $pdo->query("SELECT id FROM roles WHERE slug='super-admin'")->fetchColumn();
    if (!$roleId) throw new RuntimeException('Super Admin role is missing. Apply schema and migrations first.');
    foreach (['leave_request_attachments','leave_requests','team_attendance_leave','team_attendance','calendar_events','company_holidays','ticket_comments','ticket_activity','ticket_activity_log','ticket_attachments','ticket_assignees','ticket_departments','notifications','api_feed_access_log','api_tokens'] as $table) $pdo->exec('DELETE FROM ' . $table);
    $pdo->exec('DELETE FROM tickets');
    $pdo->exec('DELETE FROM user_permission_overrides');
    $pdo->prepare('DELETE FROM users WHERE username <> ?')->execute([$superAdminUsername]);
    $save = $pdo->prepare("INSERT INTO users(role_id,username,full_name,email,password_hash,must_change_password,is_active,approval_status) VALUES(?,?,?,?,?,1,1,'approved') ON DUPLICATE KEY UPDATE role_id=VALUES(role_id),full_name=VALUES(full_name),password_hash=VALUES(password_hash),must_change_password=1,is_active=1,approval_status='approved'");
    $save->execute([$roleId, $superAdminUsername, $superAdminName, $superAdminUsername, password_hash($temporaryPassword, PASSWORD_DEFAULT)]);
    $pdo->prepare("INSERT INTO app_settings(setting_key,setting_value) VALUES('setup_complete','1') ON DUPLICATE KEY UPDATE setting_value='1'")->execute();
    $pdo->commit();
    if (!unlink(__FILE__)) throw new RuntimeException('Cleanup completed, but self-deletion failed. Remove this file manually immediately.');
    echo 'Cleanup completed. The Super Admin account was created/reset and this script deleted itself.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    exit('Cleanup failed: ' . $e->getMessage());
}
