<?php
declare(strict_types=1);

/** Read existing definitions only; never create, migrate, or backfill live data. */
function app_schema_columns(string $table, ?PDO $pdo = null): array
{
    if (!preg_match('/\A[a-z_]+\z/', $table)) throw new InvalidArgumentException('Invalid table name.');
    $pdo ??= db();
    static $cache;
    $cache ??= new WeakMap();
    $tables = $cache[$pdo] ?? [];
    if (!isset($tables[$table])) {
        $tables[$table] = array_column($pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC), null, 'Field');
        $cache[$pdo] = $tables;
    }
    return $tables[$table];
}

function app_schema_has(string $table, string $column, ?PDO $pdo = null): bool
{
    return isset(app_schema_columns($table, $pdo)[$column]);
}

function app_linked_leave_available(?PDO $pdo = null): bool
{
    $status = app_schema_columns('leave_requests', $pdo)['status']['Type'] ?? '';
    return app_schema_has('calendar_events', 'leave_request_id', $pdo)
        && str_contains($status, "'approved'") && str_contains($status, "'cancelled'");
}

function app_public_leave_available(): bool
{
    return app_schema_has('leave_requests', 'requester_email')
        && (app_schema_columns('leave_request_attachments')['uploaded_by']['Null'] ?? '') === 'YES';
}
