<?php
declare(strict_types=1);

function sidebar_icon(string $name): string
{
    $icons = [
        'tickets' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4.75h14v14.5H5zM8 9h8M8 13h8M8 17h5"/></svg>',
        'roles' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 17h16M8 4v6M16 14v6"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5.5" width="16" height="14" rx="1"/><path d="M8 3.5v4M16 3.5v4M4 10h16"/></svg>',
    ];
    return $icons[$name] ?? '';
}

function page_start(string $title, ?array $user = null): void
{
    $page = basename($_SERVER['PHP_SELF']);
    $items = [
        ['tickets', 'index.php', ['index.php', 'create-ticket.php', 'ticket.php', 'edit-ticket.php', 'delete-ticket.php'], 'Tickets', 'tickets'],
    ];
    if ($user && user_can('manage_roles')) {
        $items[] = ['roles', 'admin.php', ['admin.php', 'reset-user-password.php'], 'Roles & Access', 'roles'];
    }
    $active = '';
    foreach ($items as $item) {
        if (in_array($page, $item[2], true)) {
            $active = $item[0];
            break;
        }
    }
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="icon" href="assets/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<aside class="topbar" aria-label="Primary navigation">
    <div class="brand">
        <img class="strata-logo" src="assets/stratastaff-logo.png" alt="Strata Staff Global">
        <span class="brand-divider"></span>
        <img class="jamesons-logo" src="assets/jamesons-logo.svg" alt="Jamesons Strata Management">
    </div>
    <nav class="nav">
        <p class="nav-label">Workspace</p>
        <?php foreach ($items as $item): ?>
            <a class="nav-link<?= $active === $item[0] ? ' active' : '' ?>" href="<?= e($item[1]) ?>"<?= $active === $item[0] ? ' aria-current="page"' : '' ?>><?= sidebar_icon($item[4]) ?><span><?= e($item[3]) ?></span></a>
        <?php endforeach; ?>
        <span class="nav-disabled" aria-disabled="true"><?= sidebar_icon('calendar') ?><span>Team Calendar <small>Coming soon</small></span></span>
    </nav>
    <div class="sidebar-footer">
        <span class="account"><?= e($user['full_name'] ?? '') ?></span>
        <?php if ($user): ?><a href="logout.php">Sign out</a><?php endif; ?>
    </div>
</aside>
<main class="page"><div class="content-shell">
<?php
}

function page_end(): void
{
    ?></div></main></body></html><?php
}
