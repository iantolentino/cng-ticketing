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
        'deleted' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14M9 7V4.75h6V7M7 7l.75 12.25h8.5L17 7M10 10.5v5M14 10.5v5"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3.75 19c.5-3 2.25-4.5 5.25-4.5s4.75 1.5 5.25 4.5M15 7.5h5.25M17.5 5v5"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5.5" width="16" height="14" rx="1"/><path d="M8 3.5v4M16 3.5v4M4 10h16"/></svg>',
        'attendance' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5.75h14v13.5H5zM8 3.75v4M16 3.75v4M5 10h14M8 14h2M12 14h4M8 17h2M12 17h4"/></svg>',
        'leave' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4.75h10l2 2v12.5H7zM17 4.75v4h4M10 13h7M10 16h5"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19.14 12.94c.04-.31.06-.62.06-.94s-.02-.63-.07-.94l2.03-1.58a.5.5 0 0 0 .12-.61l-1.92-3.32a.5.5 0 0 0-.59-.22l-2.39.96a7.28 7.28 0 0 0-1.63-.94l-.36-2.54A.5.5 0 0 0 13.9 2h-3.8a.5.5 0 0 0-.49.41l-.36 2.54c-.59.24-1.14.56-1.63.94l-2.39-.96a.5.5 0 0 0-.59.22L2.72 8.47a.5.5 0 0 0 .12.61l2.03 1.58c-.05.31-.07.63-.07.94s.02.63.07.94l-2.03 1.58a.5.5 0 0 0-.12.61l1.92 3.32a.5.5 0 0 0 .59.22l2.39-.96c.49.38 1.04.7 1.63.94l.36 2.54a.5.5 0 0 0 .49.41h3.8a.5.5 0 0 0 .49-.41l.36-2.54c.59-.24 1.14-.56 1.63-.94l2.39.96a.5.5 0 0 0 .59-.22l1.92-3.32a.5.5 0 0 0-.12-.61l-2.03-1.58ZM12 15.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7Z"/></svg>',
        'paperclip' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5 12.2 20.3a5 5 0 0 1-7.1-7.1l9.3-9.3a3.3 3.3 0 0 1 4.7 4.7l-9.4 9.4a1.6 1.6 0 1 1-2.3-2.3l8.8-8.8"/></svg>',
    ];
    return $icons[$name] ?? '';
}

function page_start(string $title, ?array $user = null): void
{
    $page = basename($_SERVER['PHP_SELF']);
    $pageClasses = [
        'dashboard.php' => 'dashboard-page', 'index.php' => 'tickets-page', 'my-work.php' => 'my-work-page',
        'department-workload.php' => 'departments-page', 'team-calendar.php' => 'team-calendar-page',
        'team-attendance.php' => 'team-attendance-page', 'leave-requests.php' => 'leave-requests-page',
        'notifications.php' => 'notifications-page', 'admin.php' => 'roles-access-page',
        'users.php' => 'users-page', 'pending-activations.php' => 'pending-activations-page', 'recruitment.php' => 'recruitment-page', 'reports.php' => 'reports-page', 'audit-log.php' => 'audit-page',
        'deleted-tickets.php' => 'deleted-tickets-page',
        'calendar-admin.php' => 'calendar-admin-page', 'health.php' => 'health-page', 'import.php' => 'import-page',
        'create-ticket.php' => 'ticket-form-page', 'edit-ticket.php' => 'ticket-form-page',
        'ticket.php' => 'ticket-detail-page', 'reset-user-password.php' => 'account-action-page',
        'change-password.php' => 'settings-page',
    ];
    $items = [];
    // CNG Admin manages staff leave and rostering, so the Team Calendar is available to every
    // role that can read the ticket workspace. Team Members still get the read-only calendar.
    $canViewCalendar = $user && user_can('view_all_tickets');
    $canViewAttendance = $canViewCalendar && ($user['role_slug'] ?? '') !== 'team-member';
    $canViewWorkQueue = $user && user_can('view_all_tickets') && !in_array(($user['role_slug'] ?? ''), ['cng-admin', 'management', 'team-member'], true);
    $unreadNotifications = 0;
    $notificationItems = [];
    if ($user) {
        [$notificationClause, $notificationParams] = ticket_visibility_notification_clause();
        [$notificationListClause, $notificationListParams] = ticket_visibility_notification_clause('n.url');
        $notificationCount = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL' . $notificationClause);
        $notificationCount->execute(array_merge([(int) $user['id']], $notificationParams));
        $unreadNotifications = (int) $notificationCount->fetchColumn();
        $notificationList = db()->prepare('SELECT n.id, n.type, n.title, n.body, n.url, n.read_at, n.created_at, a.full_name AS actor_name FROM notifications n LEFT JOIN users a ON a.id = n.actor_id WHERE n.user_id = ?' . $notificationListClause . ' ORDER BY n.created_at DESC, n.id DESC LIMIT 5');
        $notificationList->execute(array_merge([(int) $user['id']], $notificationListParams));
        $notificationItems = $notificationList->fetchAll();
    }
    if ($user && user_can('view_all_tickets')) {
        $items[] = ['dashboard', 'dashboard.php', ['dashboard.php'], 'Dashboard', 'dashboard'];
        $items[] = ['tickets', 'index.php', ['index.php', 'create-ticket.php', 'ticket.php', 'edit-ticket.php', 'delete-ticket.php'], 'Tickets', 'tickets'];
    }
    if ($canViewWorkQueue) {
        $items[] = ['departments', 'department-workload.php', ['department-workload.php'], 'Departments', 'departments'];
    }
    if ($canViewCalendar) {
        $items[] = ['calendar', 'team-calendar.php', ['team-calendar.php'], 'Team Calendar', 'calendar'];
    }
    if ($canViewAttendance) {
        $items[] = ['attendance', 'team-attendance.php', ['team-attendance.php'], 'Staff Directory', 'attendance'];
        if (($user['role_slug'] ?? '') === 'super-admin') $items[] = ['calendar-admin', 'calendar-admin.php', ['calendar-admin.php'], 'Calendar admin', 'calendar'];
    }
    if ($user && ($user['role_slug'] ?? '') !== 'management' && user_can('access_leave_request_module')) {
        $items[] = ['leave', 'leave-requests.php', ['leave-requests.php'], 'Leave Requests', 'leave'];
    }
    if ($user && ($user['role_slug'] ?? '') !== 'management' && user_can('manage_roles')) {
        $items[] = ['roles', 'admin.php', ['admin.php', 'reset-user-password.php'], 'Roles & Access', 'roles'];
    }
    if ($user && ($user['role_slug'] ?? '') === 'super-admin') {
        $items[] = ['pending-activations', 'pending-activations.php', ['pending-activations.php'], 'Pending activations', 'users'];
    }
    if ($user && ($user['role_slug'] ?? '') !== 'management' && user_can('manage_users')) {
        $items[] = ['users', 'users.php', ['users.php'], 'Users', 'users'];
    }
    if ($user && ($user['role_slug'] ?? '') === 'super-admin') {
        $items[] = ['recruitment', 'recruitment.php', ['recruitment.php'], 'Recruitment', 'users'];
    }
    if ($user && user_can('view_all_tickets') && ($user['role_slug'] ?? '') !== 'team-member') {
        $canImportReports = user_can('manage_users');
        $items[] = ['reports', 'reports.php', $canImportReports ? ['reports.php', 'import.php'] : ['reports.php'], $canImportReports ? 'Reports & CSV import' : 'Reports', 'dashboard'];
    }
    if ($user && ($user['role_slug'] ?? '') === 'super-admin') {
        $items[] = ['admin-history', 'audit-log.php', ['audit-log.php', 'deleted-tickets.php'], 'Admin history', 'deleted'];
        $items[] = ['health', 'health.php', ['health.php'], 'System health', 'dashboard'];
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css?v=20261016-ticket-tablet-r1">
    <link rel="stylesheet" href="assets/css/ui-fixes.css?v=20261016-leave-tablet-r4">
    <script src="assets/js/app.js?v=20261014-header-action-icons" defer></script>
</head>
<body class="<?= e($pageClasses[$page] ?? '') ?>">
<a class="skip-link" href="#main-content">Skip to main content</a>
<header class="app-header">
    <div class="app-header-title"><h1><?= e($title) ?></h1><p class="app-header-date"><?= e(app_datetime_display('now', 'l, F j, Y')) ?></p></div>
    <div class="app-header-actions">
        <?php if ($page === 'dashboard.php'): ?><a class="button button-secondary" href="index.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4.75h14v14.5H5zM8 9h8M8 13h8M8 17h5"/></svg>View tickets</a><?php endif; ?>
        <?php if ($user && user_can('create_tickets') && in_array($page, ['dashboard.php', 'index.php', 'my-work.php', 'department-workload.php', 'team-calendar.php', 'team-attendance.php', 'leave-requests.php', 'notifications.php', 'admin.php', 'ticket.php', 'edit-ticket.php'], true)): ?><a class="button" href="create-ticket.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Create ticket</a><?php endif; ?>
        <?php if ($user): ?>
            <?php if (($user['role_slug'] ?? '') !== 'management'): ?><div class="notification-control">
                <a class="notification-link<?= $page === 'notifications.php' ? ' active' : '' ?>" href="notifications.php" data-notification-popover-trigger aria-haspopup="dialog" aria-expanded="false" aria-controls="notification-popover" aria-label="Notifications<?= $unreadNotifications ? ' (' . min($unreadNotifications, 99) . ' unread)' : '' ?>" title="Notifications"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.75 10.75a5.25 5.25 0 0 1 10.5 0c0 3 1.25 4.5 2 5.5H4.75c.75-1 2-2.5 2-5.5M9.75 18.25a2.25 2.25 0 0 0 4.5 0"/></svg><?php if ($unreadNotifications): ?><span class="notification-count"><?= min($unreadNotifications, 99) ?></span><?php endif; ?></a>
                <div class="notification-popover" id="notification-popover" data-notification-popover role="dialog" aria-modal="false" aria-labelledby="notification-popover-title" hidden>
                    <div class="notification-popover-head">
                        <div><p class="eyebrow">Updates</p><h2 id="notification-popover-title">Notifications</h2></div>
                        <button class="icon-button notification-popover-close" type="button" data-notification-popover-close aria-label="Close notifications"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg></button>
                    </div>
                    <div class="notification-popover-list">
                        <?php foreach ($notificationItems as $notification): ?>
                            <a class="notification-popover-item<?= $notification['read_at'] ? '' : ' unread' ?>" href="notifications.php?open=<?= (int) $notification['id'] ?>">
                                <span class="notification-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6.75 10.75a5.25 5.25 0 0 1 10.5 0c0 3 1.25 4.5 2 5.5H4.75c.75-1 2-2.5 2-5.5M9.75 18.25a2.25 2.25 0 0 0 4.5 0"/></svg></span>
                                <span class="notification-popover-copy"><strong><?= e($notification['title']) ?></strong><span><?= e($notification['body']) ?></span><small><?= e($notification['actor_name'] ?? 'System') ?> &middot; <?= e(app_datetime_display($notification['created_at'], 'M j, g:i A')) ?></small></span>
                            </a>
                        <?php endforeach; ?>
                        <?php if (!$notificationItems): ?><div class="notification-popover-empty">No notifications yet.</div><?php endif; ?>
                    </div>
                    <div class="notification-popover-actions"><a class="button button-secondary" href="notifications.php">View all notifications</a></div>
                </div>
            </div><?php endif; ?>
        <?php endif; ?>
        <?php if ($user): ?>
            <a class="notification-link header-settings-link<?= $page === 'change-password.php' ? ' active' : '' ?>" href="change-password.php" aria-label="Settings" title="Settings"><?= sidebar_icon('settings') ?></a>
            <a class="notification-link header-signout-link" href="logout.php" aria-label="Sign out" title="Sign out"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5H4v14h5M14 8l4 4-4 4M8 12h10"/></svg></a>
        <?php endif; ?>
    </div>
</header>
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
    <?php if ($user): ?>
        <div class="sidebar-footer" aria-label="Signed in account">
            <div class="sidebar-account">
                <span class="app-header-avatar" aria-hidden="true"><?= e(strtoupper(substr((string) ($user['full_name'] ?? 'U'), 0, 1))) ?></span>
                <span class="sidebar-account-copy"><strong><?= e($user['full_name']) ?></strong></span>
            </div>
        </div>
    <?php endif; ?>
</aside>
<main id="main-content" class="page"><div class="content-shell">
<?php
}

function page_end(): void
{
    ?><script>
    (function(){
        document.querySelectorAll('form[method="post"]').forEach(function(form){form.addEventListener('submit',function(event){
            if(event.defaultPrevented)return;
            var button=event.submitter||form.querySelector('button[type="submit"],button');
            if(!button)return;
            var csvForm=!!form.closest('#csv-import');
            var csvAction=csvForm?form.querySelector('input[name="action"]'):null;
            if(csvForm){
                if(csvAction&&csvAction.value==='import'&&!window.confirm('Import all validated rows?')){event.preventDefault();return;}
            }else if(!button.dataset.confirm&&!form.dataset.confirm&&!window.confirm('Are you sure you want to continue?')){event.preventDefault();return;}
            if(button.name){
                var submittedAction=document.createElement('input');
                submittedAction.type='hidden';
                submittedAction.name=button.name;
                submittedAction.value=button.value;
                form.appendChild(submittedAction);
            }
            button.disabled=true;button.classList.add('is-processing');button.textContent=button.dataset.processing||button.textContent;
        });});
    })();
    </script></div></main></body></html><?php
}
