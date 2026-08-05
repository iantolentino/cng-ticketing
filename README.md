# CNG / Jamesons Issues Ticketing System

The CNG Ticketing System is an internal web application for recording, assigning, tracking, and reporting workplace issues and service requests. It gives each department one shared place to manage work instead of relying on scattered emails, spreadsheets, and verbal follow-ups.

This guide is written for both system users and non-technical project reporting. It explains what the system does, who uses it, how the main workflows operate, and how the local and production versions are managed.

## 1. Project status

The current version is implemented and tested locally. The older version remains deployed in cPanel.

Before production use, the deployment process must be completed:

1. Back up the production database and files.
2. Apply additive migrations `004` through `016`.
3. Upload the changed application files.
4. Confirm private storage permissions.
5. Run the production smoke checks.

The production upgrade path is documented in [`_brain/deployment/cpanel_upgrade_local_changes_2026-08-05.md`](_brain/deployment/cpanel_upgrade_local_changes_2026-08-05.md). Do not overwrite `config/config.local.php`; it contains environment-specific credentials and is intentionally excluded from Git.

## 2. What problem it solves

The system helps the organisation:

- Capture every issue with a clear subject, description, department, priority, and owner.
- Make responsibilities visible through single or multiple assignees.
- Keep involved departments informed without losing ownership.
- Give each role only the records and actions they should have.
- Track ageing, idle tickets, overdue work, comments, attachments, leave, attendance, and follow-ups.
- Produce operational reports for management and department review.
- Preserve administrative history and provide basic system health visibility.

## 3. Main user roles

### Super Admin

The full system administrator. Super Admins can manage users, roles, permissions, password resets, deleted-ticket restoration, audit logs, calendar administration, imports, reports, system health, and all ticket actions.

### Management

Reviews operational work and reports. Access is controlled through the permissions assigned to the account.

### Department Head

Reviews tickets and department workload within the account’s access. Department Heads approve leave after the Team Leader stage and can review approved leave on the Team Calendar.

### Team Leader

Works on tickets assigned to the Team Leader and can participate in department workload tracking. Team Leaders approve Team Member leave before Department Head review.

### Team Member

Uses the system for the access granted to the account. Team Members can submit and track leave requests and supporting files. Ticket access is permission-controlled.

### CNG Admin

A restricted ticket-view role. CNG Admin can view and filter permitted tickets but does not receive ticket editing, creation, export, My Work, department workload, calendar administration, or attendance-management actions unless a Super Admin changes the account’s permissions.

## 4. Core ticket workflow

1. A user creates a ticket with the subject, description, department, priority, and optional files.
2. A permitted user assigns one or more people and may identify involved departments.
3. Assignees work the ticket through its status stages: open, in progress, pending, or closed.
4. Users add public comments for normal collaboration or private comments for restricted internal communication.
5. The system records ticket activity, updates notification recipients, and calculates age, idle age, and SLA status.
6. The ticket can be filtered, exported, reported, or followed up until it is resolved.
7. A deleted ticket is soft-deleted and can be restored only by a Super Admin.

## 5. Ticket features

- Single and multiple assignees.
- Multiple involved departments.
- Priority levels: low, normal, high, and urgent.
- Status workflow and editable ticket details.
- Permission-aware ticket visibility.
- Team Leader assigned-ticket-only visibility.
- Search, filtering, pagination, and export to CSV/Excel.
- Dashboard drill-down links for total, open work, urgent, overdue, idle, and unassigned tickets.
- Age and idle-age indicators.
- Configurable priority-based SLA rules.
- SLA states: on track, watch, overdue, and closed.
- Follow-up button for pending or overdue tickets.
- In-app notifications for assignment, comments, approvals, and follow-ups.
- Public ticket comments and restricted private comments.
- Confidential ticket attachments with private download routes.
- Soft deletion and Super Admin-only restoration.
- Administrative audit history for sensitive changes.

## 6. Dashboard and reporting

The Dashboard provides a management-friendly overview of current work:

- Ticket totals for a selected date range.
- Open, in-progress, pending, and closed counts.
- Urgent, overdue, idle-watch, and unassigned counts.
- Clickable cards that open the matching filtered ticket register.
- Ticket trend chart with daily, weekly, or monthly views.
- Recent activity for new comments, updated tickets, and closed tickets.

The Reports page provides operational views for:

- SLA performance.
- Ticket workload by department.
- Resolution and closure activity.
- Leave activity and approval status.

Reports are intended to support weekly management reporting, workload discussions, and follow-up decisions. They describe the records visible to the logged-in user.

## 7. Leave, calendar, and attendance

### Leave requests

Team Members submit leave requests with dates, a reason, and a required screenshot, photo, or PDF supporting file. The approval path is:

`Team Member → Team Leader → Department Head`

Each approver sees the queue appropriate to their role. Supporting files remain private and are available only through permission-checked download routes.

### Team Calendar

The Team Calendar shows:

- Company holidays.
- Public holidays for the configured countries.
- Team events.
- Department Head-approved leave.
- Attendance coverage records.

Calendar items open a detail view. Super Admin-controlled calendar actions can edit or delete events and holidays with CSRF protection.

### Team Attendance

Team Leaders can record daily department coverage, status, headcount, and notes. Attendance appears on the Team Calendar as a compact operational indicator.

## 8. Super Admin administration

The Users module is the main account-management area. Super Admins can:

- Create user accounts.
- Read and search existing accounts.
- Update names, emails, roles, departments, and active status.
- Delete or deactivate accounts according to the available account workflow.
- Select permissions for a user’s job function.
- Reset a user’s password.

Additional Super Admin modules include:

- Deleted tickets: review and restore soft-deleted records.
- Audit log: review administrative actions.
- Calendar administration: manage events and holidays.
- CSV import: validate and import supported records.
- Reports: review operational metrics.
- System health: check database availability, SMTP configuration, writable storage paths, recent system logs, and backup-readiness information.

## 9. Security and privacy controls

- Passwords are stored using secure password hashing.
- Password recovery uses expiring, single-use email tokens.
- Forms use CSRF tokens.
- HTML output is escaped through the shared security helper.
- Permission checks are enforced on server-side routes, not only hidden in the interface.
- Private comments are restricted by department and assignee visibility.
- Confidential attachments use permission-checked download routes.
- Super Admin-only operations are protected at the route level.
- SMTP credentials remain in ignored local configuration and are never committed.
- Administrative actions are recorded in the audit log where applicable.

## 10. Local installation

### Requirements

- PHP with PDO MySQL support.
- MySQL or MariaDB.
- Apache, XAMPP, or another PHP-capable web server.
- A writable private storage directory for attachments.

### First-time setup

1. Create a database named `cng_ticketing`.
2. Copy `config/config.example.php` to `config/config.local.php`.
3. Set the local database connection in `config/config.local.php`.
4. Import `database/schema.sql` through phpMyAdmin or the MySQL client.
5. Open `setup.php` and create the initial Super Admin.
6. Sign in and configure users, roles, permissions, and SMTP settings.

### Applying later local migrations

Migrations are additive SQL files in `migrations/`. The current local feature set uses migrations `002` through `016`. Apply them in numeric order and do not run destructive reset commands against a database containing real data.

## 11. Local testing

Run the dependency-free regression harness from the repository root:

```powershell
& 'C:\xampp\php\php.exe' tests\regression.php
```

The harness checks required routes, migration continuity and markers, security primitives, authorization boundaries, and CSRF-protected administrative actions. PHP syntax validation should also be run before deployment:

```powershell
Get-ChildItem -Path . -Filter *.php -File | ForEach-Object { & 'C:\xampp\php\php.exe' -l $_.FullName }
Get-ChildItem -Path app -Filter *.php -File | ForEach-Object { & 'C:\xampp\php\php.exe' -l $_.FullName }
```

## 12. Suggested weekly operating routine

### Daily

- Review the Dashboard.
- Open urgent, overdue, idle-watch, and unassigned tickets.
- Check the My Work queue.
- Review notifications and pending approvals.
- Update ticket status and ownership.

### Weekly reporting

1. Open Reports and review SLA performance.
2. Compare department workload and unresolved ageing.
3. Review urgent, overdue, and idle tickets.
4. Check leave approvals and the Team Calendar.
5. Record actions or follow-ups for the next reporting period.

### Monthly administration

- Review inactive accounts and permissions.
- Review the audit log for sensitive changes.
- Check SMTP and private storage health.
- Verify the database backup process.
- Review SLA rules and department ownership.

## 13. Deployment notes

The cPanel site is a separate production environment. A production upgrade must be treated as a controlled change:

- Back up files and the database first.
- Upload only the files listed in [`_brain/deployment/cpanel_upload_manifest_2026-08-05.md`](_brain/deployment/cpanel_upload_manifest_2026-08-05.md).
- Apply migrations as additive changes in numeric order.
- Keep production `config/config.local.php` unchanged.
- Preserve private storage directories and permissions.
- Test login, role boundaries, ticket visibility, attachments, leave, calendar, reports, and health checks after deployment.

## 14. Repository structure

| Area | Purpose |
|---|---|
| `app/` | Shared authentication, security, database, ticket, notification, audit, logging, and layout helpers |
| Root `*.php` files | User-facing pages and route handlers |
| `database/schema.sql` | Base database schema |
| `migrations/` | Additive database changes |
| `config/` | Example and environment-specific configuration; local secrets stay ignored |
| `assets/` | CSS, JavaScript, and visual assets |
| `storage/private/` | Permission-protected uploaded files |
| `tests/` | Local regression checks |
| `_brain/` | Project operating notes, task queue, progress, deployment, and handoff documentation |

## 15. Plain-language reporting summary

The CNG Ticketing System is an internal control and service-management tool. It centralises issue reporting, assigns accountability, provides visibility by role and department, tracks urgency and ageing, supports leave and attendance coordination, and produces management reports. Its main business value is reducing missed follow-ups and making operational responsibility visible.

The current release is ready for local demonstration and user review. Production release is the next controlled step after the cPanel migrations, file upload, and smoke testing are completed.

## 16. Important operating rule

Never commit passwords, SMTP credentials, production database credentials, or private uploaded files. Keep environment-specific values in `config/config.local.php` and follow the deployment guide for production changes.
