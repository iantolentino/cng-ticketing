# CITS — CNG/Jamesons Issues Ticketing System

CITS is an internal ticketing and operations system for the CX department. It centralises issue reporting, assignment, status tracking, collaboration, leave coordination, attendance visibility, administration, and management reporting.

## Dashboard

### What it does

The Dashboard gives users a role-aware summary of current ticket activity and directs attention to work that needs action.

### What it contains

- **Ticket stat cards:**
  - **Total Created** — tickets created within the selected date range.
  - **Open Work** — tickets that remain active and require work.
  - **Urgent** — tickets marked with urgent priority.
  - **Overdue** — tickets that have passed their applicable SLA target.
  - **Idle Watch** — tickets with no recent activity and requiring follow-up.
  - **Unassigned** — tickets without an assigned owner.
- **Status breakdown:** counts for **Open**, **In Progress**, **Pending**, and **Closed** tickets.
- **Ticket Trend chart:** displays monthly ticket volume over a selectable date range for workload and demand analysis.
- **Recent Activity feed:** shows the latest comments, ticket updates, assignments, status changes, and closures.
- Clickable summaries that open the relevant filtered ticket list.

### Who it is for / when it is used

All authenticated users use the Dashboard for an at-a-glance view. Team Leaders and administrators use it for daily prioritisation; management uses it for workload review.

### Why it is needed

It makes urgency, ageing, ownership gaps, workload trends, and recent changes visible without requiring users to inspect every ticket individually.

## Tickets

### What it does

Tickets record and manage CX issues from creation through assignment, collaboration, follow-up, and closure.

### What it contains

- Subject, description, department, priority, status, assignees, involved departments, dates, comments, attachments, and activity history.
- Separate **Issue** and **Resolution** fields. Issue describes the actual problem; Resolution records the final closing note.
- Resolution-driven status: an empty resolution keeps the ticket in the active/in-progress status; a completed resolution moves it to the existing closed/resolved workflow and records the field and status changes.
- Priority levels including low, normal, high, and urgent.
- Statuses: Open, In Progress, Pending, and Closed.
- Single or multiple assignees.
- Public comments and restricted private comments.
- Search, filtering, pagination, and export.
- Ticket age, idle age, SLA state, and follow-up indicators.
- Soft deletion with Super Admin restoration.
- Human comments remain separate from system-generated activity history.

### Who it is for / when it is used

Team Leaders create and assign work. Assignees update progress. Permitted users review, comment on, and follow tickets within their access scope.

### Why it is needed

It provides one accountable record for each issue and prevents requests from being lost in email, chat, spreadsheets, or verbal handovers.

## My Work

### What it does

My Work shows tickets assigned to the signed-in user.

### What it contains

Assigned ticket lists with priority, status, age, idle time, SLA state, department, and follow-up information.

### Who it is for / when it is used

Assignees and Team Leaders use it as their personal work queue during daily operations.

### Why it is needed

It separates a user’s actionable workload from the wider ticket register and makes ownership clear.

## Departments

### What it does

Departments organise ticket ownership, visibility, workload reporting, and leave coordination.

### What it contains

Department-based workload views, department assignment, involved departments, and department-scoped access rules.

### Who it is for / when it is used

Team Leaders, Department Heads, and administrators use it when reviewing responsibility boundaries and workload distribution.

### Why it is needed

It makes cross-department involvement visible while preserving appropriate access boundaries.

## Team Calendar

### What it does

The Team Calendar provides a shared view of events, holidays, approved leave, and attendance coverage.

### What it contains

- Company and public holidays.
- Team events.
- Department Head-approved leave.
- Attendance coverage indicators.
- Detail views for calendar items.

### Who it is for / when it is used

Teams use it for staffing awareness, scheduling, leave planning, and coverage checks.

### Why it is needed

It gives the department one shared view of availability and operational coverage.

## Team Attendance

### What it does

Team Attendance records daily department coverage.

### What it contains

Attendance date, department, coverage status, headcount, and notes.

### Who it is for / when it is used

Team Leaders use it during daily coverage and staffing checks.

### Why it is needed

It helps leaders identify staffing gaps and gives the calendar useful operational context.

## Calendar Admin

### What it does

Calendar Admin manages shared events and holidays displayed on the Team Calendar.

### What it contains

Event and holiday creation, editing, deletion, dates, titles, descriptions, and applicable calendar details.

### Who it is for / when it is used

Super Admins use it to maintain the organisation-wide calendar information.

### Why it is needed

It keeps shared scheduling data accurate and prevents users from relying on outdated holiday or event information.

## Notifications

### What it does

Notifications alert users when tracked activity requires their attention.

### What it contains

Assignment alerts, comment alerts, leave approval updates, follow-up reminders, and other relevant ticket or workflow notifications. Users can mark notifications as read.

### Who it is for / when it is used

All authenticated users use Notifications to monitor changes affecting their work.

### Why it is needed

It reduces missed handoffs and follow-ups by bringing important changes to the user’s attention.

## Roles & Access

### What it does

Roles & Access controls which pages, records, and actions each account can use.

### What it contains

- Role-based permissions for ticket creation, viewing, editing, assignment, exports, administration, reports, and workflow actions.
- Account-level permission overrides where authorised.
- Server-side access checks for protected routes.
- Role boundaries for ticket visibility, including Team Leader assignment scope.

### Who it is for / when it is used

Super Admins use it when configuring access for new staff, changing responsibilities, or reviewing permission boundaries.

### Why it is needed

It protects sensitive information and ensures users can perform only the work appropriate to their role.

## Users

### What it does

Users manages internal accounts and their access settings.

### What it contains

Account name, email, username, role, department, active status, permission settings, account actions, and user password reset controls.

### Who it is for / when it is used

Super Admins use it to create accounts, update staff details, assign roles and departments, activate or deactivate accounts, and reset passwords.

### Why it is needed

It keeps account ownership and access current as staff join, leave, or change responsibilities.

## Registration and Account Approval

### What it does

It allows a new user to request an account publicly while keeping activation under Super Admin control.

### What it contains

- Name, company email, username, requested role, password, and password confirmation.
- Thank-you/pending message after registration.
- Super Admin activation with confirmation.
- Pending and inactive-account messages during login.

### Who it is for / when it is used

New staff use it to request access; Super Admins review and activate approved accounts.

### Why it is needed

It provides a controlled onboarding process without allowing unapproved accounts to enter the system.

## Deleted Tickets

### What it does

Deleted Tickets stores soft-deleted tickets separately from the active workflow.

### What it contains

Deleted ticket records, deletion details, and a restore action.

### Who it is for / when it is used

Super Admins use it to review accidental or inappropriate deletions and restore records when necessary.

### Why it is needed

It provides recoverability and administrative control without exposing deleted records in normal ticket work queues.

## Audit Log

### What it does

Audit Log records sensitive administrative and system actions.

### What it contains

The acting user, action type, affected record or area, timestamp, and available action details.

### Who it is for / when it is used

Super Admins use it for accountability, troubleshooting, access reviews, and operational investigations.

### Why it is needed

It creates a traceable history for changes that affect users, tickets, permissions, and system administration.

## CSV Import

### What it does

CSV Import supports controlled bulk loading of supported operational records.

### What it contains

File upload, validation feedback, supported record mapping, import results, and row-level error information where applicable.

### Who it is for / when it is used

Super Admins use it when onboarding data or transferring supported records without entering each item manually.

### Why it is needed

It reduces repetitive data entry while validating imported data before it becomes part of the system.

## Reports

### What it does

Reports turns ticket and workflow data into operational summaries.

### What it contains

- SLA performance.
- Department workload.
- Resolution and closure activity.
- Leave activity and approval status.
- Date and access-scoped reporting views.

### Who it is for / when it is used

Management, Department Heads, Team Leaders, and authorised administrators use Reports for weekly reviews, workload discussions, and follow-up decisions.

### Why it is needed

It supports decisions with measurable workload, ageing, service-level, and resolution information.

## System Health

### What it does

System Health checks whether key application services and operational dependencies are available.

### What it contains

Database connectivity, SMTP configuration status, writable private storage paths, recent system logs, and operational readiness indicators.

### Who it is for / when it is used

Super Admins use it during troubleshooting, deployment validation, and routine operational checks.

### Why it is needed

It helps identify infrastructure or configuration problems before they prevent users from creating tickets, receiving notifications, or accessing files.

## Public API / Webhook Feed

### What it does

It provides external systems such as Zapier, Make, or internal automations with read-only ticket data through a revocable secret URL.

### What it contains

- Super Admin token generation, rotation, masking, revocation, and history.
- Token-authenticated JSON ticket feed with status, priority, category, assignee, date, and search filters.
- Pagination, basic rate limiting, and separate access logging.
- Issue, resolution, description, and comment-count fields without internal-only comment content.

### Who it is for / when it is used

Authorised integrations use it when they need ticket data without interactive login credentials. Super Admins manage the access URL.

### Why it is needed

It supports controlled automation while allowing the access URL to be invalidated immediately if exposed.

## Roles and typical use

| Role | Typical use |
|---|---|
| Super Admin | Full administration, users, roles and access, deleted tickets, audit, calendar administration, imports, reports, and system health. |
| Management | Reviews permitted operational workload and reports. |
| Department Head | Reviews department work, completes the final leave approval stage, and reviews approved leave. |
| Team Leader | Creates and manages assigned work, reviews team workload, approves Team Member leave, and records attendance. |
| Pod Leader | Uses permitted ticket and team-work functions within the assigned access scope. |
| Subject Matter Expert (SME) | Works on permitted assigned tickets and provides technical or process input. |
| Team Member | Uses permitted ticket functions and submits or tracks leave requests. |
| CNG Admin | Uses restricted ticket viewing and filtering according to assigned permissions. |

## Cross-system controls

- Ticket visibility and actions are permission-controlled on the server.
- CSRF protection is used for state-changing forms.
- Passwords use secure hashing and password recovery uses expiring, single-use tokens.
- Private comments are restricted to authorised assignees and departments.
- Confidential attachments are served through permission-checked download routes.
- Delete and update actions include a confirmation step and completion notification.
- SLA rules identify on-track, watch, overdue, and closed ticket states.
- Soft-deleted tickets remain recoverable to authorised administrators.

## Internal report summary

CITS gives the CX department a central operating record for issues, ownership, urgency, progress, and resolution. Its dashboards and reports support management visibility; its roles, private data controls, and audit history support accountability; and its calendar, attendance, notifications, and leave workflows support day-to-day team coordination.
