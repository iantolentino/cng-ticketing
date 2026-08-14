<?php
declare(strict_types=1);

function audit_admin_action(?int $actorId, string $action, string $entityType, ?int $entityId = null, array $details = []): void
{
    db()->prepare('INSERT INTO admin_audit_log(actor_id,action,entity_type,entity_id,details) VALUES(?,?,?,?,?)')->execute([$actorId, $action, $entityType, $entityId, json_encode($details)]);
}
