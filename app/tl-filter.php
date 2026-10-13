<?php
declare(strict_types=1);

/**
 * Keep external tickets within each Team Leader's CNG service dates.
 *
 * This file only filters the in-memory ticket list. It does not delete or
 * modify records in any external database.
 */
function tl_filter_ticket_visible(array $ticket): bool
{
    $requester = strtolower(trim((string) ($ticket['requester'] ?? '')));
    $createdAt = trim((string) ($ticket['created_at'] ?? $ticket['date'] ?? ''));

    if ($requester === 'sheena magdaraog') {
        // Source timestamps are UTC; Oct 6 midnight in Manila is Oct 5 16:00 UTC.
        return $createdAt !== ''
            && $createdAt >= '2026-07-11 00:00:00'
            && $createdAt < '2026-10-05 16:00:00';
    }

    if ($requester === '' || $createdAt === '') {
        return true;
    }

    $cutoff = match ($requester) {
        'leonard sunga' => '2025-01-01 00:00:00',
        'trisha balingit' => null,
        default => null,
    };

    return $cutoff === null || $createdAt >= $cutoff;
}

function tl_filter_tickets(array $tickets): array
{
    return array_values(array_filter($tickets, 'tl_filter_ticket_visible'));
}
