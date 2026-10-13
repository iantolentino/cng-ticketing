<?php
declare(strict_types=1);

/**
 * True when the account may still receive work and notifications: the row must exist and be
 * enabled. Deleted accounts have no row, disabled accounts have is_active=0, so this single
 * check covers both.
 */
function notification_recipient_is_active(int $userId): bool
{
    $query = db()->prepare('SELECT is_active FROM users WHERE id = ?');
    $query->execute([$userId]);
    $isActive = $query->fetchColumn();
    return $isActive !== false && (int) $isActive === 1;
}

function notify_user(int $userId, ?int $actorId, string $type, string $title, string $body, string $url): void
{
    if ($userId <= 0) return;
    // A disabled or deleted account must never be handed work or alerted about it.
    if (!notification_recipient_is_active($userId)) return;
    $ticketId = ticket_visibility_url_id($url);
    if ($ticketId !== null && is_ticket_restricted($ticketId)) {
        global $ADMIN_ROLES;
        $recipient = db()->prepare('SELECT r.slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.is_active=1');
        $recipient->execute([$userId]);
        if (!in_array($recipient->fetchColumn(), $ADMIN_ROLES, true)) return;
    }
    db()->prepare('INSERT INTO notifications(user_id,actor_id,type,title,body,url) VALUES(?,?,?,?,?,?)')->execute([$userId, $actorId ?: null, $type, $title, $body, $url]);
}

function notify_many(array $userIds, ?int $actorId, string $type, string $title, string $body, string $url): void
{
    foreach (array_unique(array_map('intval', $userIds)) as $userId) {
        if ($userId > 0 && $userId !== (int) $actorId) notify_user($userId, $actorId, $type, $title, $body, $url);
    }
}

function active_user_ids_by_role(string $roleSlug): array
{
    $query = db()->prepare('SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.is_active = 1 AND r.slug = ? ORDER BY u.id');
    $query->execute([$roleSlug]);
    return array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
}

function notify_new_ticket(array $assigneeIds, int $actorId, string $ticketNumber, string $subject, int $ticketId): void
{
    $url = 'ticket.php?id=' . $ticketId;
    notify_many($assigneeIds, $actorId, 'assignment', 'Ticket assigned: ' . $ticketNumber, $subject, $url);
    $managementIds = array_merge(active_user_ids_by_role('management'), active_user_ids_by_role('super-admin'));
    notify_many(array_diff($managementIds, $assigneeIds), $actorId, 'new_ticket', 'New ticket: ' . $ticketNumber, $subject, $url);
}
