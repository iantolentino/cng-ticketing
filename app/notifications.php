<?php
declare(strict_types=1);

function notify_user(int $userId, ?int $actorId, string $type, string $title, string $body, string $url): void
{
    if ($userId <= 0) return;
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
