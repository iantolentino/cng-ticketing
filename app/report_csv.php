<?php
declare(strict_types=1);

/** Validate a CSV stream without changing application data. */
function report_csv_preview($handle, string $type, array $expected): array
{
    $rows = [];
    $errors = [];
    $head = fgetcsv($handle);
    if ($head && isset($head[0])) $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', $head[0]);
    if (!$head || array_map('trim', $head) !== $expected) {
        return ['type' => $type, 'rows' => [], 'errors' => ['The header must be: ' . implode(',', $expected)]];
    }
    $line = 1;
    $recordCount = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $line++;
        if ($row === [null]) continue;
        if (++$recordCount > 1000) {
            $errors[] = 'This file exceeds 1,000 data rows. Split it into smaller files and validate again. Nothing has been imported.';
            break;
        }
        if (count($row) !== count($expected)) {
            $errors[] = 'Row ' . $line . ': expected ' . count($expected) . ' columns; found ' . count($row) . '.';
            continue;
        }
        $data = array_map(static fn($value): string => trim((string) $value), array_combine($expected, $row));
        if ($type === 'users' && (!$data['full_name'] || !preg_match('/^[a-z0-9._-]{3,80}$/', $data['username']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) || !$data['role'])) {
            $errors[] = 'Row ' . $line . ': enter a full name, username (3-80 lowercase letters, numbers, dots, underscores or hyphens), valid email and role.';
        } elseif ($type === 'departments' && (!$data['name'] || !$data['code'])) {
            $errors[] = 'Row ' . $line . ': department name and code are required.';
        } elseif ($type === 'holidays') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['date']);
            if (!$date || $date->format('Y-m-d') !== $data['date'] || !$data['label']) {
                $errors[] = 'Row ' . $line . ': enter a real date in YYYY-MM-DD format and a holiday label.';
            } else {
                $rows[] = $data;
            }
        } elseif (in_array($type, ['users', 'departments'], true)) {
            $rows[] = $data;
        } else {
            $errors[] = 'This import type is not supported.';
        }
    }
    if (!$rows && !$errors) $errors[] = 'The file has no data rows. Add records below the header and validate again.';
    return ['type' => $type, 'rows' => $rows, 'errors' => $errors];
}