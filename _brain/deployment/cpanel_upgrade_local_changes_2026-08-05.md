# cPanel Upgrade Guide - Local MVP Changes

This guide upgrades the already deployed cPanel site. Do not re-run `setup.php` and do not replace `config/config.local.php`.

For the exact upload file list, use `cpanel_upload_manifest_2026-08-05.md`.

## 1. Backup First

1. In cPanel/phpMyAdmin, export the live database.
2. In File Manager, download a copy of the current live app folder.
3. Confirm you can restore both before changing production.

## 2. Run Database Migrations

Run the additive migrations before uploading the new PHP files, or put the site in a short maintenance window and do the migration/upload steps back-to-back. The new layout reads the `notifications` table on every authenticated page, so production must have the new tables before users load the upgraded code.

In phpMyAdmin, run these SQL files in order against the live database:

1. `migrations/004_multi_assignees_departments_cng_admin.sql`
2. `migrations/005_future_foundation_alignment.sql`
3. `migrations/006_calendar_events_and_public_holidays.sql`
4. `migrations/007_leave_attachments_calendar_details.sql`
5. `migrations/008_ticket_priority.sql`
6. `migrations/009_notifications.sql`
7. `migrations/010_team_attendance.sql`
8. `migrations/011_password_reset_tokens.sql`
9. `migrations/012_admin_audit_log.sql`
10. `migrations/013_sla_rules.sql`
11. `migrations/014_bulk_ticket_actions_permission.sql`
12. `migrations/015_private_ticket_comments.sql`
13. `migrations/016_system_logs.sql`
14. `migrations/017_ticket_creation_role_scope.sql`

These are additive/idempotent migrations for the local feature set.

## 3. Upload Files

Upload the changed repository files to the existing app folder, preserving paths.

New files to upload:

- `dashboard.php`
- `notifications.php`
- `my-work.php`
- `department-workload.php`
- `team-calendar.php`
- `team-attendance.php`
- `leave-requests.php`
- `download-attachment.php`
- `download-leave-attachment.php`
- `app/notifications.php`
- `storage/private/.htaccess`
- `migrations/004_multi_assignees_departments_cng_admin.sql`
- `migrations/005_future_foundation_alignment.sql`
- `migrations/006_calendar_events_and_public_holidays.sql`
- `migrations/007_leave_attachments_calendar_details.sql`
- `migrations/008_ticket_priority.sql`
- `migrations/009_notifications.sql`
- `migrations/010_team_attendance.sql`
- `migrations/011_password_reset_tokens.sql`
- `migrations/012_admin_audit_log.sql`
- `migrations/013_sla_rules.sql`
- `migrations/014_bulk_ticket_actions_permission.sql`
- `migrations/015_private_ticket_comments.sql`
- `bulk-tickets.php`

Also upload changed existing files, including:

- `app/auth.php`
- `app/bootstrap.php`
- `app/layout.php`
- `app/mailer.php`
- `app/tickets.php`
- `assets/css/app.css`
- `change-password.php`
- `create-ticket.php`
- `database/schema.sql`
- `edit-ticket.php`
- `export-tickets.php`
- `index.php`
- `login.php`
- `ticket.php`
- `forgot-password.php`
- `reset-password.php`
- `audit-log.php`
- `calendar-admin.php`
- `reports.php`
- `health.php`
- `app/logging.php`
- `migrations/016_system_logs.sql`
- `migrations/017_ticket_creation_role_scope.sql`
- `app/audit.php`

Do not upload or overwrite `config/config.local.php` unless you are deliberately changing live DB/SMTP settings.

## 4. Folder Permissions

Ensure these folders exist and are writable by PHP:

- `storage/private/ticket-attachments/`
- `storage/private/leave-attachments/`
- `.local/sessions/`

Keep `storage/private/.htaccess` uploaded so private files are not publicly browsable.

## 5. Smoke Test

Use the known role accounts or live admin accounts:

- Super Admin: Dashboard, Tickets, Notifications, Roles & Access.
- Team Leader: only assigned tickets, My Work, Department workload, Leave approvals, Notifications.
- CNG Admin: can view/filter tickets only; no My Work, Department workload, Calendar, edit/create actions.
- Team Member: Leave Requests and Notifications only; no main ticketing pages.
- Department Head: leave approval queue and ticket access based on role permissions.

Critical checks:

- `dashboard.php` loads and shows ticket metrics, recent activity, and trend chart.
- Dashboard cards open filtered `index.php` register views.
- `index.php` is only the ticket register/filter page.
- Multi-assignee and multiple department selections save and display.
- Team Leader cannot see tickets not assigned to them.
- CNG Admin can view/filter only.
- Leave request submission requires a PDF/JPG/PNG supporting file.
- Team Leader approval happens before Department Head approval.
- Team Calendar shows events, holidays, approved leave, and detail popovers.
- Team Attendance allows Team Leaders to log department coverage and shows those records on Team Calendar.
- Notifications appear, unread count updates, mark-all-read works.
- Pending or overdue assigned tickets show the Follow up button and notify assignees.
- Confidential attachment downloads respect permissions.

## 6. Rollback

If production breaks:

1. Restore the previous app folder backup.
2. Restore the database backup if migrations caused data/schema issues.
3. Keep a note of the failing step and error message before retrying.
