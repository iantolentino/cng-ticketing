# CNG / Jamesons Deployment Runbook

## Before upload

- [ ] Confirm `main` is pushed to GitHub.
- [ ] Export a backup of the production database if one already exists.
- [ ] Obtain the cPanel MySQL database name, user, password, and host.
- [ ] Obtain SMTP host, port, encryption, username, password, sender email, and sender name.
- [ ] Keep SMTP credentials in a password manager; enter them directly into `config/config.local.php` on the target server. Never commit or paste them into Git, `_brain`, support tickets, or chat logs.
- [ ] Obtain Management email addresses and add them to user accounts after setup.
- [x] Jamesons logo is already vendored at `assets/jamesons-logo.svg`.

## cPanel deployment

1. Upload the repository contents to the intended document-root folder.
2. Create the MySQL database and a database user in cPanel; grant all database privileges.
3. For a new database, import `database/schema.sql` in phpMyAdmin.
4. Import `migrations/002_leave_and_calendar_foundation.sql`, then `migrations/003_ticket_export_permission.sql`, then `migrations/004_multi_assignees_departments_cng_admin.sql`, then `migrations/005_future_foundation_alignment.sql`, then `migrations/006_calendar_events_and_public_holidays.sql`, then `migrations/007_leave_attachments_calendar_details.sql`, then `migrations/008_ticket_priority.sql`, then `migrations/009_notifications.sql`, then `migrations/010_team_attendance.sql` in that order.
5. Copy `config/config.example.php` to `config/config.local.php`.
6. Fill the database values in `config/config.local.php`.
7. **Reminder: fill all SMTP values in `config/config.local.php` before enabling live notifications.**
8. Ensure `config/config.local.php` is not publicly downloadable; keep it outside version control.
9. Open `setup.php`, create the Super Admin, save the temporary passwords, then confirm setup redirects to login.

## Go-live verification

- [ ] Sign in as Super Admin.
- [ ] Confirm Team Leader cannot create a ticket and Pod Leader can.
- [ ] Confirm Team Leader can only see tickets assigned to them.
- [ ] Confirm CNG Admin can view and filter all tickets but cannot create, edit, export, or manage roles.
- [ ] Confirm Team Member lands on `leave-requests.php` and cannot access ticketing pages.
- [ ] Create a ticket with multiple assignees and multiple involved departments; confirm register, detail page, filters, and exports display them correctly.
- [ ] Create or edit a ticket with each priority; confirm urgent tickets appear in the dashboard/My Work urgent counts and priority filters.
- [ ] Confirm `department-workload.php` appears for Team Leader, lets them log involved departments on assigned tickets, and remains blocked for CNG Admin.
- [ ] Confirm Leave Requests shows TL approval, Department Head approval, and approved-this-week counts for Team Member, Team Leader, and Department Head roles.
- [ ] Confirm `dashboard.php` appears in the sidebar, shows ticket dashboard/recent activity, and card drill-down links open filtered `index.php` register views.
- [ ] Confirm Notifications appears in the sidebar, unread count updates, mark-all-read works, and assignment/comment/leave approval notifications are created.
- [ ] Confirm pending or overdue assigned tickets show the Follow up button and notify assignees.
- [ ] Confirm Dashboard ticket trend chart switches between daily, weekly, and monthly views with 3/6/9/12-month ranges.
- [ ] Confirm Team Attendance appears in the sidebar, Team Leaders can log department coverage, and records appear on Team Calendar.
- [ ] Change a role permission in `admin.php`; confirm it applies immediately.
- [ ] Create, assign, update, comment on, close, and reopen a ticket.
- [ ] Confirm audit activity appears and soft-deleted tickets are hidden.
- [ ] Add Management emails, configure SMTP, create a ticket, and confirm notification delivery.
- [ ] Remove any temporary test accounts or tickets.

## Rollback

1. Put the previous application files back in place.
2. Restore the database backup if the schema/data needs to be reverted.
3. Record the incident and corrective action in `_brain/fixes/fix_log.md`.
