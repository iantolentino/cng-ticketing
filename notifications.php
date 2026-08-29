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
<div class="notifications-screen">
    <?php if ($notice): ?><p class="action-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
    <section class="notifications-panel">
        <div class="notifications-toolbar"><div><strong><?= (int) $unread ?></strong><span>unread notification<?= $unread === 1 ? '' : 's' ?></span></div><?php if ($unread): ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="button button-secondary" name="action" value="mark_all_read"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Mark all read</button></form><?php endif; ?></div>
        <div class="notification-list"><?php foreach ($notifications as $notification): ?><a class="notification-item<?= $notification['read_at'] ? '' : ' unread' ?>" href="notifications.php?open=<?= (int) $notification['id'] ?>"><span class="notification-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6.75 10.75a5.25 5.25 0 0 1 10.5 0c0 3 1.25 4.5 2 5.5H4.75c.75-1 2-2.5 2-5.5M9.75 18.25a2.25 2.25 0 0 0 4.5 0"/></svg></span><div class="notification-copy"><div><strong><?= e($notification['title']) ?></strong><span class="pill pill-pending"><?= e(ucwords(str_replace('_', ' ', $notification['type']))) ?></span></div><p><?= e($notification['body']) ?></p><small><?= e($notification['actor_name'] ?? 'System') ?> · <?= e(date('M j, g:i A', strtotime($notification['created_at']))) ?></small></div><svg class="notification-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></a><?php endforeach; ?><?php if (!$notifications): ?><div class="notifications-empty"><span aria-hidden="true">✓</span><h2>You're all caught up</h2><p>No notifications yet.</p></div><?php endif; ?></div>
    </section>
</div>
<?php page_end();
