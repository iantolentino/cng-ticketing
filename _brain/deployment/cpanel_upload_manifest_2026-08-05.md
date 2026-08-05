# cPanel Upload Manifest - 2026-08-05 Local MVP

Use this with `_brain/deployment/cpanel_upgrade_local_changes_2026-08-05.md`.

## Source State

- GitHub repo: `https://github.com/iantolentino/cng-ticketing.git`
- Latest pushed commit with feature code: `cc31060`
- Current local docs have extra uncommitted deployment-preflight notes.

## Upload These Files

Root PHP pages:

- `dashboard.php`
- `index.php`
- `create-ticket.php`
- `edit-ticket.php`
- `ticket.php`
- `my-work.php`
- `department-workload.php`
- `team-calendar.php`
- `team-attendance.php`
- `leave-requests.php`
- `notifications.php`
- `download-attachment.php`
- `download-leave-attachment.php`
- `export-tickets.php`
- `login.php`
- `change-password.php`

App support:

- `app/auth.php`
- `app/bootstrap.php`
- `app/layout.php`
- `app/mailer.php`
- `app/notifications.php`
- `app/tickets.php`

Assets and storage guard:

- `assets/css/app.css`
- `storage/private/.htaccess`

Database/migrations:

- `database/schema.sql`
- `migrations/004_multi_assignees_departments_cng_admin.sql`
- `migrations/005_future_foundation_alignment.sql`
- `migrations/006_calendar_events_and_public_holidays.sql`
- `migrations/007_leave_attachments_calendar_details.sql`
- `migrations/008_ticket_priority.sql`
- `migrations/009_notifications.sql`
- `migrations/010_team_attendance.sql`

Deployment docs to keep for handoff:

- `_brain/deployment/cng_ticketing_deployment.md`
- `_brain/deployment/cpanel_upgrade_local_changes_2026-08-05.md`
- `_brain/deployment/cpanel_upload_manifest_2026-08-05.md`
- `_brain/summaries/current_state.md`
- `_brain/tasks/execution_queue.md`

## Do Not Upload

- `config/config.local.php`
- `.local/`
- `storage/private/leave-attachments/`
- `storage/private/ticket-attachments/`
- `reports/`
- `seed_test_tickets.php`
- `CNG TICKETING USERS.png`
- `_brain/cng-ticketing-session.md`
- `_brain/deployments/production_checklist.md`

## Deployment Order

1. Back up live database and live app folder.
2. Run migrations `004` through `010` in order.
3. Upload files from this manifest, preserving paths.
4. Create/writable-check `storage/private/ticket-attachments/`, `storage/private/leave-attachments/`, and `.local/sessions/`.
5. Run the role smoke tests in the upgrade guide.
