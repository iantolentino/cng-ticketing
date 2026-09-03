<?php
declare(strict_types=1);

/**
 * Read-only adapters for the four external ticket databases.
 *
 * The source configuration is loaded separately from config/config.external.php.
 * Each adapter receives its own PDO connection and never writes to the source.
 */

function external_source_configs(): array
{
    global $config;
    $sources = $config['external_sources'] ?? [];
    return is_array($sources) ? $sources : [];
}

function external_source_config(string $sourceKey): ?array
{
    $source = external_source_configs()[$sourceKey] ?? null;
    return is_array($source) ? $source : null;
}

function external_source_url(string $sourceKey, array $source): ?string
{
    $defaults = [
        'stratast_escalations' => 'https://escalations.stratastaffglobal.com/',
        'stratast_support' => 'http://support.stratastaffglobal.com/',
        'stratast_wp346' => 'https://learning.stratastaffglobal.com/',
        'stratast_requisition' => 'https://requisition.stratastaffglobal.com/',
    ];
    $url = trim((string) ($source['url'] ?? $defaults[$sourceKey] ?? ''));
    return preg_match('/\Ahttps?:\/\/[^\s]+\z/i', $url) ? $url : null;
}

function external_identifier(string $value, string $label): string
{
    if ($value === '' || !preg_match('/\A[A-Za-z0-9_]+\z/', $value)) {
        throw new InvalidArgumentException('Invalid external ' . $label . '.');
    }
    return '`' . $value . '`';
}

function external_connection(array $source): PDO
{
    $connection = $source['connection'] ?? [];
    if (!is_array($connection)) throw new InvalidArgumentException('Invalid external connection settings.');
    foreach (['host', 'database', 'username'] as $key) {
        if (trim((string) ($connection[$key] ?? '')) === '') throw new InvalidArgumentException('External connection is incomplete.');
    }
    $port = (int) ($connection['port'] ?? 3306);
    if ($port < 1 || $port > 65535) throw new InvalidArgumentException('Invalid external database port.');
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $connection['host'], $port, $connection['database']);
    return new PDO($dsn, (string) $connection['username'], (string) ($connection['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
}

function external_filter_sql(array $source, array &$params): string
{
    $filter = $source['filter'] ?? [];
    $parts = [];
    foreach ((array) ($filter['customer_domains'] ?? []) as $index => $domain) {
        $domain = ltrim(strtolower(trim((string) $domain)), '@');
        if ($domain !== '' && preg_match('/\A[A-Za-z0-9.-]+\z/', $domain)) {
            $key = 'external_domain_' . $index;
            $parts[] = 'LOWER(c.email) LIKE :' . $key;
            $params[$key] = '%@' . $domain;
        }
    }
    foreach ((array) ($filter['customer_names'] ?? []) as $index => $name) {
        $name = trim((string) $name);
        if ($name !== '') {
            $key = 'external_name_' . $index;
            $parts[] = 'c.name = :' . $key;
            $params[$key] = $name;
        }
    }
    return $parts ? '(' . implode(' OR ', $parts) . ')' : '1 = 0';
}

function external_table_names(array $source): array
{
    $tables = $source['tables'] ?? [];
    if (!is_array($tables)) throw new InvalidArgumentException('Invalid external table settings.');
    return [
        'tickets' => external_identifier((string) ($tables['tickets'] ?? ''), 'tickets table'),
        'customers' => external_identifier((string) ($tables['customers'] ?? ''), 'customers table'),
        'statuses' => external_identifier((string) ($tables['statuses'] ?? ''), 'statuses table'),
        'priorities' => external_identifier((string) ($tables['priorities'] ?? ''), 'priorities table'),
        'categories' => external_identifier((string) ($tables['categories'] ?? ''), 'categories table'),
        'threads' => external_identifier((string) ($tables['threads'] ?? ''), 'threads table'),
    ];
}

function external_ticket_rows(PDO $pdo, array $source): array
{
    $tables = external_table_names($source);
    $params = [];
    $sql = "SELECT t.id AS ticket_id, t.subject, c.name AS requester_name, c.email AS requester_email,
                    s.name AS status_name, p.name AS priority_name, cat.name AS category_name,
                    t.assigned_agent, t.date_created, t.date_closed
             FROM {$tables['tickets']} t
             JOIN {$tables['customers']} c ON t.customer = c.id
             JOIN {$tables['statuses']} s ON t.status = s.id
             LEFT JOIN {$tables['priorities']} p ON t.priority = p.id
             LEFT JOIN {$tables['categories']} cat ON t.category = cat.id
             WHERE " . external_filter_sql($source, $params) . "
             ORDER BY t.date_created DESC";
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $sourceLabel = trim((string) ($source['label'] ?? 'External source')) ?: 'External source';
    return array_map(static function (array $row) use ($sourceLabel): array {
        $closed = trim((string) ($row['date_closed'] ?? ''));
        return [
            'source' => $sourceLabel,
            'id' => (int) ($row['ticket_id'] ?? 0),
            'requester' => trim((string) ($row['requester_name'] ?? '')),
            'email' => trim((string) ($row['requester_email'] ?? '')),
            'subject' => trim((string) ($row['subject'] ?? '')),
            'status' => trim((string) ($row['status_name'] ?? '')),
            'priority' => trim((string) ($row['priority_name'] ?? '')),
            'category' => trim((string) ($row['category_name'] ?? '')),
            'agent' => trim((string) ($row['assigned_agent'] ?? '')) ?: null,
            'date' => trim((string) ($row['date_created'] ?? '')),
            'date_closed' => ($closed !== '' && $closed !== '0000-00-00 00:00:00') ? $closed : null,
        ];
    }, $statement->fetchAll());
}

function fetch_stratast_support_tickets(PDO $pdo, array $source): array
{
    return external_ticket_rows($pdo, $source);
}

function fetch_stratast_escalations_tickets(PDO $pdo, array $source): array
{
    return external_ticket_rows($pdo, $source);
}

function fetch_stratast_wp346_tickets(PDO $pdo, array $source): array
{
    return external_ticket_rows($pdo, $source);
}

function fetch_stratast_requisition_tickets(PDO $pdo, array $source): array
{
    return external_ticket_rows($pdo, $source);
}

function external_thread_rows(PDO $pdo, array $source, int $ticketId): array
{
    if ($ticketId < 1) throw new InvalidArgumentException('Invalid external ticket ID.');
    $tables = external_table_names($source);
    $statement = $pdo->prepare("SELECT type, body, date_created FROM {$tables['threads']} WHERE ticket = :ticket_id AND type = 'report' ORDER BY date_created ASC");
    $statement->execute(['ticket_id' => $ticketId]);
    return array_map(static fn(array $row): array => [
        'type' => (string) ($row['type'] ?? 'report'),
        'body' => (string) ($row['body'] ?? ''),
        'date' => (string) ($row['date_created'] ?? ''),
    ], $statement->fetchAll());
}

function fetch_stratast_support_thread(PDO $pdo, array $source, int $ticketId): array
{
    return external_thread_rows($pdo, $source, $ticketId);
}

function fetch_stratast_escalations_thread(PDO $pdo, array $source, int $ticketId): array
{
    return external_thread_rows($pdo, $source, $ticketId);
}

function fetch_stratast_wp346_thread(PDO $pdo, array $source, int $ticketId): array
{
    return external_thread_rows($pdo, $source, $ticketId);
}

function fetch_stratast_requisition_thread(PDO $pdo, array $source, int $ticketId): array
{
    return external_thread_rows($pdo, $source, $ticketId);
}

function external_thread_body_html(string $body): string
{
    $body = trim($body);
    if ($body === '') return '';
    if (!class_exists('DOMDocument')) return nl2br(htmlspecialchars(strip_tags($body), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

    $previousErrors = libxml_use_internal_errors(true);
    $document = new DOMDocument('1.0', 'UTF-8');
    $loaded = $document->loadHTML('<div id="cits-external-thread-root">' . $body . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $root = $loaded ? $document->getElementById('cits-external-thread-root') : null;
    $html = '';
    if ($root) {
        foreach ($root->childNodes as $child) $html .= external_thread_render_node($child);
    }
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrors);
    return $html !== '' ? $html : nl2br(htmlspecialchars(strip_tags($body), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

function external_thread_render_node($node): string
{
    if ($node instanceof DOMText || $node instanceof DOMCdataSection) {
        return htmlspecialchars(str_replace("\xC2\xA0", ' ', $node->nodeValue), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    if (!$node instanceof DOMElement) {
        $html = '';
        foreach ($node->childNodes as $child) $html .= external_thread_render_node($child);
        return $html;
    }

    $tag = strtolower($node->tagName);
    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'textarea', 'button', 'select', 'option', 'link', 'meta', 'head', 'title'], true)) return '';
    $children = '';
    foreach ($node->childNodes as $child) $children .= external_thread_render_node($child);
    if ($tag === 'br') return '<br>';
    if (trim(html_entity_decode(strip_tags($children), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '') return '';

    $allowed = ['p', 'div', 'section', 'article', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    if (!in_array($tag, $allowed, true)) return $children;
    if ($tag === 'a') {
        $href = trim($node->getAttribute('href'));
        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
        $safeHref = $href !== '' && in_array($scheme, ['http', 'https', 'mailto'], true) ? ' href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '';
        return '<a' . $safeHref . '>' . $children . '</a>';
    }
    return '<' . $tag . '>' . $children . '</' . $tag . '>';
}

function external_status_key(string $value): string
{
    $status = strtolower(trim($value));
    if (in_array($status, ['open', 'new'], true)) return 'open';
    if (in_array($status, ['in progress', 'in-progress', 'in_progress', 'processing', 'working'], true)) return 'in_progress';
    if (in_array($status, ['pending', 'on hold', 'on-hold', 'on_hold', 'waiting'], true)) return 'pending';
    if (in_array($status, ['closed', 'resolved', 'complete', 'completed'], true)) return 'closed';
    return 'external';
}

function external_priority_key(string $value): string
{
    $priority = strtolower(trim($value));
    return in_array($priority, ['low', 'normal', 'high', 'urgent'], true) ? $priority : 'external';
}

function external_department_label(string $sourceKey): string
{
    return [
        'stratast_support' => 'IT Department',
        'stratast_escalations' => 'HR Department',
        'stratast_requisition' => 'Finance Department',
        'stratast_wp346' => 'L&D',
    ][$sourceKey] ?? 'External source';
}

function external_normalize_ticket(array $ticket, string $sourceKey): array
{
    $id = (int) ($ticket['id'] ?? 0);
    $date = trim((string) ($ticket['date'] ?? '')) ?: null;
    $closed = trim((string) ($ticket['date_closed'] ?? '')) ?: null;
    $statusLabel = trim((string) ($ticket['status'] ?? '')) ?: 'Unknown';
    $priorityLabel = trim((string) ($ticket['priority'] ?? '')) ?: 'N/A';
    $agent = trim((string) ($ticket['agent'] ?? '')) ?: null;
    $department = external_department_label($sourceKey);
    return [
        'is_external' => true,
        'external_key' => $sourceKey,
        'external_id' => $id,
        'id' => $id,
        'ticket_number' => 'EXT-' . strtoupper(str_replace('_', '-', $sourceKey)) . '-' . $id,
        'source' => trim((string) ($ticket['source'] ?? 'External source')) ?: 'External source',
        'source_url' => external_source_url($sourceKey, external_source_config($sourceKey) ?? []),
        'requester' => trim((string) ($ticket['requester'] ?? '')),
        'email' => trim((string) ($ticket['email'] ?? '')),
        'subject' => trim((string) ($ticket['subject'] ?? '')),
        'status' => external_status_key($statusLabel),
        'status_label' => $statusLabel,
        'priority' => external_priority_key($priorityLabel),
        'priority_label' => $priorityLabel,
        'category' => trim((string) ($ticket['category'] ?? '')) ?: 'N/A',
        'subcategory' => '—',
        'agent' => $agent,
        'assignees' => $agent ?: 'Unassigned',
        'employee_name' => trim((string) ($ticket['requester'] ?? '')) ?: '—',
        'department' => $department,
        'departments' => $department,
        'issue_escalator' => '—',
        'description' => '',
        'resolution' => null,
        'created_at' => $date,
        'updated_at' => null,
        'closed_at' => $closed,
        'sort_at' => $date,
    ];
}

function external_ticket_visible_to_user(array $ticket, array $user): bool
{
    if (($user['role_slug'] ?? '') === 'team-member') return false;
    if (($user['role_slug'] ?? '') !== 'team-leader') return true;
    $agent = strtolower(trim((string) ($ticket['agent'] ?? '')));
    $name = strtolower(trim((string) ($user['full_name'] ?? '')));
    return $agent !== '' && $name !== '' && str_contains($agent, $name);
}

function external_ticket_load_source(string $sourceKey): array
{
    $source = external_source_config($sourceKey);
    if (!$source || empty($source['enabled'])) return [];
    $listFunction = (string) ($source['list_function'] ?? '');
    if (!preg_match('/\Afetch_[a-z0-9_]+_tickets\z/', $listFunction) || !is_callable($listFunction)) {
        throw new InvalidArgumentException('Invalid external list adapter.');
    }
    $pdo = external_connection($source);
    return array_map(static fn(array $ticket): array => external_normalize_ticket($ticket, $sourceKey), $listFunction($pdo, $source));
}

function external_ticket_load_all(array $user): array
{
    if (($user['role_slug'] ?? '') === 'team-member') return ['tickets' => [], 'errors' => []];
    $tickets = [];
    $errors = [];
    foreach (external_source_configs() as $sourceKey => $source) {
        if (!is_string($sourceKey) || !is_array($source) || empty($source['enabled'])) continue;
        try {
            foreach (external_ticket_load_source($sourceKey) as $ticket) {
                if (external_ticket_visible_to_user($ticket, $user)) $tickets[] = $ticket;
            }
        } catch (Throwable $exception) {
            $label = trim((string) ($source['label'] ?? $sourceKey)) ?: $sourceKey;
            $errors[] = $label;
            error_log('CITS external source failed: ' . $sourceKey . ' (' . $exception::class . ')');
        }
    }
    return ['tickets' => $tickets, 'errors' => $errors];
}

function external_ticket_find(string $sourceKey, int $ticketId, array $user): ?array
{
    if (($user['role_slug'] ?? '') === 'team-member') return null;
    foreach (external_ticket_load_source($sourceKey) as $ticket) {
        if ((int) $ticket['external_id'] === $ticketId && external_ticket_visible_to_user($ticket, $user)) return $ticket;
    }
    return null;
}

function external_ticket_thread(string $sourceKey, int $ticketId): array
{
    $source = external_source_config($sourceKey);
    if (!$source || empty($source['enabled'])) throw new InvalidArgumentException('External source is unavailable.');
    $threadFunction = (string) ($source['thread_function'] ?? '');
    if (!preg_match('/\Afetch_[a-z0-9_]+_thread\z/', $threadFunction) || !is_callable($threadFunction)) {
        throw new InvalidArgumentException('Invalid external thread adapter.');
    }
    return $threadFunction(external_connection($source), $source, $ticketId);
}

function external_ticket_matches_filters(array $ticket, array $filters, string $dashboardStart, array $user = []): bool
{
    if ($filters['status'] !== '' && $ticket['status'] !== $filters['status']) return false;
    if ($filters['priority'] !== '' && $ticket['priority'] !== $filters['priority']) return false;
    if ($filters['department'] !== '' || $filters['subcategory'] !== '') return false;
    if ($filters['category'] !== '' && strcasecmp((string) $ticket['category'], $filters['category']) !== 0) return false;
    if (($filters['scope'] ?? '') === 'mine') {
        $agent = strtolower(trim((string) ($ticket['agent'] ?? '')));
        $name = strtolower(trim((string) ($user['full_name'] ?? '')));
        if ($agent === '' || $name === '' || !str_contains($agent, $name)) return false;
    }
    if ($filters['search'] !== '') {
        $haystack = implode(' ', [$ticket['ticket_number'], $ticket['source'], $ticket['requester'], $ticket['email'], $ticket['subject'], $ticket['status_label'], $ticket['priority_label'], $ticket['category'], (string) ($ticket['agent'] ?? '')]);
        if (stripos($haystack, $filters['search']) === false) return false;
    }
    if ($filters['date_from'] !== '' && (!$ticket['created_at'] || $ticket['created_at'] < $filters['date_from'] . ' 00:00:00')) return false;
    if ($filters['date_to'] !== '' && (!$ticket['created_at'] || $ticket['created_at'] >= date('Y-m-d 00:00:00', strtotime($filters['date_to'] . ' +1 day')))) return false;
    if (($filters['dashboard_range'] ?? 'all') !== 'all' && (!$ticket['created_at'] || $ticket['created_at'] < $dashboardStart)) return false;
    if ($filters['dashboard_view'] === '') return true;
    return match ($filters['dashboard_view']) {
        'total' => true,
        'open_work' => $ticket['status'] !== 'closed',
        'urgent' => $ticket['status'] !== 'closed' && $ticket['priority'] === 'urgent',
        'overdue', 'idle' => false,
        'unassigned' => empty($ticket['agent']),
        default => true,
    };
}
