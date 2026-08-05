<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';

$user = require_login();
$pdo = db();

if (isset($_GET['open'])) {
    $id = (int) $_GET['open'];
    $query = $pdo->prepare('SELECT url FROM notifications WHERE id = ? AND user_id = ?');
    $query->execute([$id, $user['id']]);
    $url = (string) ($query->fetchColumn() ?: 'notifications.php');
    $pdo->prepare('UPDATE notifications SET read_at = COALESCE(read_at, NOW()) WHERE id = ? AND user_id = ?')->execute([$id, $user['id']]);
    redirect($url);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'mark_all_read') {
        $pdo->prepare('UPDATE notifications SET read_at = COALESCE(read_at, NOW()) WHERE user_id = ? AND read_at IS NULL')->execute([$user['id']]);
        redirect('notifications.php?notice=read');
    }
}

$unreadCount = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
$unreadCount->execute([$user['id']]);
$unread = (int) $unreadCount->fetchColumn();
$query = $pdo->prepare('SELECT n.*, a.full_name AS actor_name FROM notifications n LEFT JOIN users a ON a.id = n.actor_id WHERE n.user_id = ? ORDER BY n.created_at DESC, n.id DESC LIMIT 80');
$query->execute([$user['id']]);
$notifications = $query->fetchAll();
$notice = ($_GET['notice'] ?? '') === 'read' ? 'Notifications marked as read.' : '';

page_start('Notifications', $user);
?>
<?php if ($notice): ?><p class="action-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
<div class="page-head"><div><p class="eyebrow">Activity inbox</p><h1>Notifications</h1><p class="page-subtitle"><?= (int) $unread ?> unread notification<?= $unread === 1 ? '' : 's' ?>.</p></div><div class="page-head-actions"><?php if ($unread): ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="button button-secondary" name="action" value="mark_all_read">Mark all read</button></form><?php endif; ?></div></div>
<section>
    <div class="notification-list"><?php foreach ($notifications as $notification): ?><a class="notification-item<?= $notification['read_at'] ? '' : ' unread' ?>" href="notifications.php?open=<?= (int) $notification['id'] ?>"><div><strong><?= e($notification['title']) ?></strong><p><?= e($notification['body']) ?></p><small><?= e($notification['actor_name'] ?? 'System') ?> - <?= e(date('M j, g:i A', strtotime($notification['created_at']))) ?></small></div><span class="pill pill-pending"><?= e(ucwords(str_replace('_', ' ', $notification['type']))) ?></span></a><?php endforeach; ?><?php if (!$notifications): ?><p class="muted">No notifications yet.</p><?php endif; ?></div>
</section>
<?php page_end();
