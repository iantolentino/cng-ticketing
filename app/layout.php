<?php
declare(strict_types=1);

function sidebar_icon(string $name): string
{
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.75 13.25h5.5v6h-5.5zM13.75 4.75h5.5v14.5h-5.5zM4.75 4.75h5.5v5.5h-5.5z"/></svg>',
        'tickets' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4.75h14v14.5H5zM8 9h8M8 13h8M8 17h5"/></svg>',
        'work' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6.75h16v10.5H4zM8 6.75v-2h8v2M4 13h5l1.5 2h3l1.5-2h5"/></svg>',
        'departments' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.75 19.25V8.75h6.5v10.5M12.75 19.25V4.75h6.5v14.5M3.75 19.25h16.5M7 12h2M7 15h2M15 8h2M15 11h2M15 14h2"/></svg>',
        'notifications' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.75 10.75a5.25 5.25 0 0 1 10.5 0c0 3 1.25 4.5 2 5.5H4.75c.75-1 2-2.5 2-5.5M9.75 18.25a2.25 2.25 0 0 0 4.5 0"/></svg>',
        'roles' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 17h16M8 4v6M16 14v6"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5.5" width="16" height="14" rx="1"/><path d="M8 3.5v4M16 3.5v4M4 10h16"/></svg>',
        'attendance' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5.75h14v13.5H5zM8 3.75v4M16 3.75v4M5 10h14M8 14h2M12 14h4M8 17h2M12 17h4"/></svg>',
        'leave' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4.75h10l2 2v12.5H7zM17 4.75v4h4M10 13h7M10 16h5"/></svg>',
        'paperclip' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5 12.2 20.3a5 5 0 0 1-7.1-7.1l9.3-9.3a3.3 3.3 0 0 1 4.7 4.7l-9.4 9.4a1.6 1.6 0 1 1-2.3-2.3l8.8-8.8"/></svg>',
    ];
    return $icons[$name] ?? '';
}

function page_start(string $title, ?array $user = null): void
{
    $page = basename($_SERVER['PHP_SELF']);
    $items = [];
    $canViewCalendar = $user && user_can('view_all_tickets') && ($user['role_slug'] ?? '') !== 'cng-admin';
    $canViewWorkQueue = $user && user_can('view_all_tickets') && ($user['role_slug'] ?? '') !== 'cng-admin';
    $unreadNotifications = 0;
    if ($user) {
        $notificationCount = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
        $notificationCount->execute([(int) $user['id']]);
        $unreadNotifications = (int) $notificationCount->fetchColumn();
    }
    if ($user && user_can('view_all_tickets')) {
        $items[] = ['dashboard', 'dashboard.php', ['dashboard.php'], 'Dashboard', 'dashboard'];
        $items[] = ['tickets', 'index.php', ['index.php', 'create-ticket.php', 'ticket.php', 'edit-ticket.php', 'delete-ticket.php'], 'Tickets', 'tickets'];
    }
    if ($canViewWorkQueue) {
        $items[] = ['work', 'my-work.php', ['my-work.php'], 'My Work', 'work'];
        $items[] = ['departments', 'department-workload.php', ['department-workload.php'], 'Departments', 'departments'];
    }
    if ($canViewCalendar) {
        $items[] = ['calendar', 'team-calendar.php', ['team-calendar.php'], 'Team Calendar', 'calendar'];
        $items[] = ['attendance', 'team-attendance.php', ['team-attendance.php'], 'Team Attendance', 'attendance'];
    }
    if ($user && user_can('access_leave_request_module')) {
        $items[] = ['leave', 'leave-requests.php', ['leave-requests.php'], 'Leave Requests', 'leave'];
    }
    if ($user) {
        $items[] = ['notifications', 'notifications.php', ['notifications.php'], 'Notifications' . ($unreadNotifications ? ' (' . min($unreadNotifications, 99) . ')' : ''), 'notifications'];
    }
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
