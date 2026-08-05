# Current State

## System State

EXECUTION_MODE

## Current Phase

Multi-assignee, ticket visibility, dashboard, priority, attachment, leave, and calendar UI implemented locally; production migration/deploy still pending

## Last Completed Task

ATTEND-01 - Team Attendance module feeding Team Calendar
Completed: 2026-08-05

## Next Task

DEP-02 - Apply local migrations/files to cPanel and run live role/filter checks
Depends on: local user approval and production maintenance window

## Active Blockers

Waiting for the user to approve the local changes, apply the new additive migration in cPanel, and upload the changed files.

## Local-Only Reminder

The 2026-08-04/2026-08-05 changes are local only until cPanel deploy: multi-assignees, involved departments, Team Leader assigned-ticket-only visibility, CNG Admin role, filter layout improvements, dedicated Dashboard page, dashboard metrics, ticket priority, department workload logging, in-app notifications, confidential attachments, leave request UI, approval queues, Team Calendar UI, interactive calendar events/holidays, PH/AU/CA 2026 public holiday seeding, leave request supporting files, calendar item details, Team Attendance, backlog/task-list updates, and migrations `004`, updated `005`, `006`, `007`, `008`, `009`, and `010`.

## Session Notes

TKT-04 completed locally: tickets now support multiple assignees through `ticket_assignees`, multiple involved departments through `ticket_departments`, Team Leaders are scoped to tickets assigned to them, and `cng-admin` is seeded as a view/filter-only role.

Filters were moved into a clearer register panel and exports now preserve the active ticket filters. SMTP credentials must be entered directly into ignored `config/config.local.php`.

Future foundation completed locally: `ticket_attachments` has medical-certificate-friendly metadata aliases, `company_holidays` and `leave_requests` are in the base schema, and the login flow now refreshes cached user state before choosing the landing page.

2026-08-05 UI pass completed locally: dashboard range counts appear above the ticket register; confidential attachments can be uploaded/listed/downloaded from ticket detail by permitted roles; Team Members can submit and track leave requests; Team Leaders approve pending leave first; Department Heads only see Team Leader-approved requests; Team Calendar shows company holidays and Department Head-approved leave. CNG Admin remains ticket view/filter-only and Team Member has no ticket/calendar nav.

CAL-03 completed locally: Team Calendar is now a clickable month grid. Calendar viewers can click a date to add a team event or company holiday. Department Head-approved leave appears automatically. Public holidays for PH, AU, and CA are seeded for 2026 via migration `006_calendar_events_and_public_holidays.sql`.

CAL-04/ATT-02 completed locally: Clicking a holiday/event/leave chip on Team Calendar opens a details panel. Holidays show date/type/country, events show creator/date, and leave shows the employee, date range, reason, plus private supporting file download links. New Team Member leave requests require a screenshot/photo/PDF and store it in `leave_request_attachments`.

TKT-05 completed locally: ticket aging is computed from `created_at`, idle age from `updated_at`, and SLA states are shown as On track, Watch, Overdue, or Closed. Dashboard now includes Overdue and Idle watch cards, the register has SLA/Age/Idle columns, and ticket detail shows an SLA summary band. Thresholds are 3+ days idle for Watch and 7+ days open for Overdue.

TKT-06 completed locally: `my-work.php` shows only tickets assigned to the logged-in user, either via legacy `assignee_id` or the multi-assignee pivot. It includes SLA/Age/Idle signals, status filtering, and assigned-work metric cards. Sidebar shows My Work for ticket roles except `cng-admin`; Team Member remains leave-only.

TKT-07 completed locally: tickets now have a priority field (`low`, `normal`, `high`, `urgent`) with create/edit validation, register and My Work filters/columns, ticket-detail display, CSV/Excel export support, and urgent count cards on the dashboard and My Work.

UI-08 completed locally: dashboard metric cards are now clickable drill-down shortcuts using `dashboard_view`. Total, Open Work, Urgent, Overdue, Idle Watch, and Unassigned apply matching register filters, show an active card state, preserve the dashboard range, and export CSV/Excel with the same virtual filter.

UI-09 completed locally: the Issues register dashboard now includes a Recent activity panel with New comments, Recently updated, and Recently closed columns. Each item links to the ticket, uses existing comments/ticket data, and respects Team Leader assigned-ticket visibility through the shared ticket scope.

DEP-03 completed locally: `department-workload.php` adds a sidebar Department workload page for ticket roles except `cng-admin`. It shows workload by department and lets Team Leaders log involved departments for tickets they can access, using `ticket_departments`, CSRF protection, and `ticket_scope_sql()` visibility.

LVE-04 completed locally: `leave-requests.php` now shows a Leave dashboard with Team Leader approval count, Department Head approval count, and approved leave overlapping the current week. Approvers see workflow-wide counts; Team Members see their own leave status counts.

UI-11 completed locally: `dashboard.php` is now the sidebar Dashboard page. Ticket dashboard metrics and Recent activity moved out of `index.php`; the Tickets page is again focused on filters and the register. Dashboard cards still drill into `index.php` with `dashboard_view` filters.

NTF-02 completed locally: `notifications` table and `app/notifications.php` helper added, with `notifications.php` center, unread sidebar count, mark-all-read, and open-and-mark-read behavior. Ticket assignment/comment and leave approval workflow hooks now create in-app notifications. Ticket users now land on `dashboard.php` after login.

UI-10 completed locally: `dashboard.php` now has a Ticket trend bar chart with daily, weekly, and monthly views plus 3/6/9/12-month ranges. Counts are generated server-side from `tickets.created_at` and respect Team Leader ticket scope.

ATTEND-01 completed locally: `team-attendance.php` lets Team Leaders and Super Admin log daily department coverage with status, headcount, and notes. Records are stored in `team_attendance`, appear as attendance chips on `team-calendar.php`, and open in the existing calendar details popover.

NTF-03 completed locally: pending or overdue assigned tickets now show a Follow up button on ticket detail for users who can comment. It emails active assignees when SMTP is configured, creates in-app follow-up notifications, and records `follow_up_sent` in ticket activity.

Deployment guide created locally: `_brain/deployment/cpanel_upgrade_local_changes_2026-08-05.md` documents how to upgrade the already deployed cPanel site without re-running setup or overwriting `config/config.local.php`. It instructs backing up production, applying additive migrations `004` through `010`, uploading changed files, setting private storage permissions, and smoke-testing role boundaries.

Last updated: 2026-08-05
