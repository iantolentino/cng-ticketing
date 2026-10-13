<?php
declare(strict_types=1);

/**
 * Shared listing pagination.
 *
 * Both index.php and department-workload.php merge a native query with the
 * federated external tickets and then page through the combined list, so the
 * window maths and the pager markup live here rather than being duplicated
 * per screen.
 */

/**
 * Normalise a requested page into a clamped 1-based window.
 *
 * @return array{current:int,totalPages:int,offset:int,start:int,end:int,perPage:int,total:int}
 */
function pagination_state(int $totalItems, int $perPage, int $requestedPage = 0): array
{
    $perPage = max(1, $perPage);
    $total = max(0, $totalItems);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $current = $requestedPage > 0 ? $requestedPage : 1;
    if ($current > $totalPages) { $current = $totalPages; }
    return [
        'current' => $current,
        'totalPages' => $totalPages,
        'offset' => ($current - 1) * $perPage,
        'start' => max(1, $current - 2),
        'end' => min($totalPages, $current + 2),
        'perPage' => $perPage,
        'total' => $total,
    ];
}

/** Read and validate ?page= from the current request. */
function pagination_requested_page(): int
{
    return filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
}

/** Page URL for a listing screen; blank filters are dropped so URLs stay clean. */
function pagination_url(string $script, int $page, array $query): string
{
    $params = array_filter($query, static fn($value): bool => $value !== '' && $value !== null);
    if ($page > 1) { $params['page'] = $page; } else { unset($params['page']); }
    $queryString = http_build_query($params);
    return $script . ($queryString !== '' ? '?' . $queryString : '');
}

/**
 * Pager markup. Returns an empty string when there is nothing to page, which
 * matches the previous "only render when the list has rows" behaviour.
 */
function pagination_nav(string $script, array $query, array $state, string $ariaLabel): string
{
    if ((int) $state['total'] <= 0) { return ''; }

    $current = (int) $state['current'];
    $totalPages = (int) $state['totalPages'];
    $link = static function (int $page, string $label, bool $isCurrent) use ($script, $query): string {
        if ($isCurrent) { return '<span class="current" aria-current="page">' . e($label) . '</span>'; }
        return '<a href="' . e(pagination_url($script, $page, $query)) . '">' . e($label) . '</a>';
    };

    $html = '<nav class="pagination" aria-label="' . e($ariaLabel) . '">';
    $html .= $current > 1
        ? '<a href="' . e(pagination_url($script, $current - 1, $query)) . '">Previous</a>'
        : '<span class="disabled">Previous</span>';
    if ((int) $state['start'] > 1) {
        $html .= $link(1, '1', false);
        if ((int) $state['start'] > 2) { $html .= '<span class="disabled">...</span>'; }
    }
    for ($page = (int) $state['start']; $page <= (int) $state['end']; $page++) {
        $html .= $link($page, (string) $page, $page === $current);
    }
    if ((int) $state['end'] < $totalPages) {
        if ((int) $state['end'] < $totalPages - 1) { $html .= '<span class="disabled">...</span>'; }
        $html .= $link($totalPages, (string) $totalPages, false);
    }
    $html .= $current < $totalPages
        ? '<a href="' . e(pagination_url($script, $current + 1, $query)) . '">Next</a>'
        : '<span class="disabled">Next</span>';
    return $html . '</nav>';
}
