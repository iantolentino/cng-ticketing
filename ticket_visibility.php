<?php
/* EDIT ONLY THIS CONFIG BLOCK.
 * Remove an ID and re-upload this file to restore visibility (no data is deleted).
 * IDs apply to native and external tickets in CNG. Existing permissions still apply.
 */
$HIDDEN_TICKET_IDS = [
    6119, 6113, 5473, 251, 250, 5405, 5404, 5403, 5372, 5363,
    5273, 236, 5325, 5263, 5254, 5234, 5156, 5225, 5152, 5141,
    5069, 5053, 5038, 5027, 4969, 4929, 4926, 4915, 4893,
    1063, 1061, 1047, 1014, 987, 982, 959, 950, 932, 925, 885, 867,
    834, 818, 808, 789, 788, 752, 720, 718, 555, 511,
];
// Visible in lists/counts, but only admins can open these tickets or their content.
$LOCKED_TICKET_IDS = [
    92, 91, 90,
];
$ADMIN_ROLES = ['admin', 'super-admin'];
$TICKETS_TABLE = 'tickets';
$ID_COLUMN = 'id';
/* END CONFIG. Include this file with require_once; uploading it alone is not enough. */

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    exit('Ticket not found.');
}

/** Validate identifiers: SQL identifiers cannot be bound as statement parameters. */
function ticket_visibility_identifier(string $name): string
{
    if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $name)) {
        throw new InvalidArgumentException('Invalid ticket visibility SQL identifier.');
    }
    return '`' . $name . '`';
}

function ticket_visibility_ids(): array
{
    global $HIDDEN_TICKET_IDS;
    foreach ($HIDDEN_TICKET_IDS as $id) {
        if (!is_int($id) || $id < 1) throw new InvalidArgumentException('Hidden IDs must be positive integers.');
    }
    return array_values(array_unique($HIDDEN_TICKET_IDS));
}

function is_ticket_hidden(int $id): bool
{
    return in_array($id, ticket_visibility_ids(), true);
}

/** IDs that remain listed but cannot be opened by non-admins. */
function ticket_locked_ids(): array
{
    global $LOCKED_TICKET_IDS;
    foreach ($LOCKED_TICKET_IDS as $id) {
        if (!is_int($id) || $id < 1) throw new InvalidArgumentException('Locked IDs must be positive integers.');
    }
    return array_values(array_unique($LOCKED_TICKET_IDS));
}

/** Both kinds reserve their numbers and protect detailed content. */
function ticket_restricted_ids(): array
{
    return array_values(array_unique(array_merge(ticket_visibility_ids(), ticket_locked_ids())));
}

function is_ticket_restricted(int $id): bool
{
    return in_array($id, ticket_restricted_ids(), true);
}

function ticket_access_allowed(int $id): bool
{
    return can_view_hidden() || !is_ticket_restricted($id);
}

/** The application must populate this role from its authenticated user, never request input. */
function can_view_hidden(): bool
{
    global $ADMIN_ROLES;
    return in_array($_SESSION['role'] ?? null, $ADMIN_ROLES, true);
}

/** Positional placeholders: use this before ORDER BY/LIMIT, also in COUNT queries. */
function hidden_ids_sql_clause(string $column = 'id'): array
{
    $column = implode('.', array_map('ticket_visibility_identifier', explode('.', $column)));
    $ids = ticket_visibility_ids();
    if (can_view_hidden() || !$ids) return ['', []];
    return [$column . ' NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids];
}

/** Named-placeholder equivalent for CNG's existing queries; force applies to token APIs. */
function ticket_visibility_sql(array &$params, string $column = 't.id', bool $force = false, bool $details = false): string
{
    $column = implode('.', array_map('ticket_visibility_identifier', explode('.', $column)));
    if (!$force && can_view_hidden()) return '';
    $names = [];
    foreach (($details ? ticket_restricted_ids() : ticket_visibility_ids()) as $index => $id) {
        $key = 'hidden_ticket_' . $index;
        while (array_key_exists($key, $params)) $key .= '_';
        $params[$key] = $id;
        $names[] = ':' . $key;
    }
    return $names ? ' AND ' . $column . ' NOT IN (' . implode(',', $names) . ')' : '';
}

/** Same status and body as CNG's missing-ticket response. */
function guard_ticket_access(int $id): void
{
    if (!ticket_access_allowed($id)) {
        http_response_code(404);
        exit('Ticket not found.');
    }
}

/**
 * Run before beginTransaction, once per connection used for creation.
 * Creates a small InnoDB counter table if missing. No ticket data is changed.
 * The DB user needs CREATE on first use (or a DBA can create this table first).
 * DDL stays outside transactions because MySQL CREATE TABLE implicitly commits.
 */
function ticket_visibility_prepare_allocator(PDO $pdo): void
{
    if ($pdo->inTransaction()) throw new LogicException('Prepare the allocator before beginning the transaction.');
    try {
        $pdo->prepare('SELECT target_key FROM ticket_visibility_allocator LIMIT 0')->execute();
    } catch (PDOException $exception) {
        if (($exception->errorInfo[1] ?? 0) !== 1146) throw $exception;
        $pdo->prepare('CREATE TABLE IF NOT EXISTS ticket_visibility_allocator (target_key VARCHAR(150) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, last_id BIGINT UNSIGNED NOT NULL) ENGINE=InnoDB')->execute();
    }
    global $TICKETS_TABLE;
    ticket_visibility_identifier($TICKETS_TABLE);
    $engines = $pdo->prepare('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?)');
    $engines->execute([$TICKETS_TABLE, 'ticket_visibility_allocator']);
    $found = $engines->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ([$TICKETS_TABLE, 'ticket_visibility_allocator'] as $name) {
        if (strcasecmp((string) ($found[$name] ?? ''), 'InnoDB') !== 0) {
            throw new RuntimeException('Ticket allocation requires InnoDB tables.');
        }
    }
}

/**
 * Caller MUST keep its transaction open through the explicit-ID INSERT and commit.
 * Every writer to this ticket table must use this allocator. External read-only
 * source applications have their own writers and must be integrated separately.
 * A permanent counter row avoids races even on an empty tickets table.
 * Lock reference: https://dev.mysql.com/doc/refman/9.1/en/innodb-locking-reads.html
 */
function next_ticket_id(PDO $pdo): int
{
    global $TICKETS_TABLE, $ID_COLUMN;
    if (!$pdo->inTransaction()) throw new LogicException('Begin a transaction before allocating a ticket ID.');
    $table = ticket_visibility_identifier($TICKETS_TABLE);
    $column = ticket_visibility_identifier($ID_COLUMN);
    $key = $TICKETS_TABLE . '.' . $ID_COLUMN;
    $pdo->prepare('INSERT INTO ticket_visibility_allocator(target_key,last_id) VALUES(?,0) ON DUPLICATE KEY UPDATE target_key=VALUES(target_key)')->execute([$key]);
    $lock = $pdo->prepare('SELECT last_id FROM ticket_visibility_allocator WHERE target_key=? FOR UPDATE');
    $lock->execute([$key]);
    $last = (int) $lock->fetchColumn();
    // A locking read sees current committed data, even under REPEATABLE READ.
    $maximum = $pdo->prepare("SELECT $column FROM $table ORDER BY $column DESC LIMIT 1 FOR UPDATE");
    $maximum->execute();
    $last = max($last, (int) $maximum->fetchColumn());
    do {
        if ($last === PHP_INT_MAX) throw new OverflowException('Ticket ID range exhausted.');
        ++$last;
    } while (is_ticket_restricted($last));
    $pdo->prepare('UPDATE ticket_visibility_allocator SET last_id=? WHERE target_key=?')->execute([$last, $key]);
    return $last;
}

/** Filter existing notifications before counts and LIMIT, including external links. */
function ticket_visibility_notification_clause(string $column = 'url'): array
{
    $column = implode('.', array_map('ticket_visibility_identifier', explode('.', $column)));
    $ids = ticket_restricted_ids();
    if (can_view_hidden() || !$ids) return ['', []];
    $ids = implode('|', $ids);
    $pattern = '(^|/)ticket[.]php[?]([^#]*&)?(id=0*(' . $ids . ')|external=[A-Za-z0-9_]+(:|%3[Aa])0*(' . $ids . '))(&|#|$)';
    return [' AND (' . $column . ' IS NULL OR ' . $column . ' NOT REGEXP ?)', [$pattern]];
}

/** Keep existing anchor styling while omitting navigation for restricted tickets. */
function ticket_link_attributes(string $url): string
{
    $id = ticket_visibility_url_id($url);
    if ($id !== null && !ticket_access_allowed($id)) return 'aria-disabled="true" tabindex="-1"';
    return 'href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"';
}

/** The row-click script only attaches to rows with data-ticket-href. */
function ticket_row_navigation_attributes(string $url): string
{
    $id = ticket_visibility_url_id($url);
    if ($id !== null && !ticket_access_allowed($id)) return 'aria-disabled="true"';
    return 'data-ticket-href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" tabindex="0"';
}

/** Resolve a notification target for recipient-based checks, not sender privileges. */
function ticket_visibility_url_id(string $url): ?int
{
    if (basename((string) parse_url($url, PHP_URL_PATH)) !== 'ticket.php') return null;
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    if (isset($query['id']) && is_scalar($query['id']) && ctype_digit((string) $query['id'])) return (int) $query['id'];
    if (isset($query['external']) && is_string($query['external']) && preg_match('/:(\d+)\z/', $query['external'], $match)) return (int) $match[1];
    return null;
}

/* USAGE (examples only):
 * (a) List, search, CSV, pagination and counts:
 * [$clause, $params] = hidden_ids_sql_clause('t.id');
 * $q = $pdo->prepare('SELECT t.* FROM tickets t WHERE t.deleted_at IS NULL'
 *     . ($clause ? ' AND ' . $clause : '') . ' ORDER BY t.id DESC');
 * $q->execute($params);
 *
 * (b) Ticket view/edit/comment: authenticate first, then:
 * guard_ticket_access((int) ($_GET['id'] ?? 0));
 * Continue with your normal ticket lookup and authorization checks.
 * For attachments, resolve the parent ticket ID before guarding access.
 *
 * (c) Create (fill your own required business columns in the INSERT):
 * ticket_visibility_prepare_allocator($pdo);
 * $pdo->beginTransaction();
 * try {
 *     $id = next_ticket_id($pdo);
 *     $pdo->prepare('INSERT INTO tickets(id,subject) VALUES(?,?)')->execute([$id,$subject]);
 *     $pdo->commit();
 * } catch (Throwable $e) {
 *     if ($pdo->inTransaction()) $pdo->rollBack();
 *     throw $e;
 * }
 * Never commit inside next_ticket_id: that would release the reservation too early.
 * On deadlock/lock timeout retry the WHOLE transaction, not just the INSERT.
 *
 * AUTO_INCREMENT: keep the column AUTO_INCREMENT, but insert the explicit ID.
 * MySQL advances its counter when an explicit ID exceeds the counter. No ALTER
 * is needed. An alternative is a DBA-controlled ALTER TABLE ... AUTO_INCREMENT=N
 * above ALL reserved numbers while writers are stopped; never ALTER in a ticket
 * transaction. Future config additions still require updating every writer.
 *
 * INTEGRATION CHECKLIST:
 * - Bootstrap: require_once this file; set $_SESSION['role'] from authenticated DB role.
 * - Lists/search/filters/dashboard/reports/exports/pagination: filter BEFORE aggregation.
 * - View/edit/delete/comments/attachments/download: guard parent ticket, same missing 404.
 * - API/webhooks: filter according to recipient/token, never a browser admin session.
 * - Notifications/counts: filter stored URLs; new notifications/emails check RECIPIENT.
 * - Jobs/imports/all writers: use next_ticket_id + explicit INSERT in one transaction.
 * - Protect stored attachments from direct web access; downloads must use guarded PHP.
 * - Restore visibility: remove from HIDDEN; restore opening: remove from LOCKED. Both lists reserve IDs. Existing soft-deleted tickets still need normal restore.
 * - Previously delivered email/downloads cannot be recalled by this configuration.
 * - External apps remain independently accessible under their own access controls.
 */
