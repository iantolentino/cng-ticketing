# Backlog

> Tasks are ordered by dependency. Execute one task per cycle and verify before moving on.

## Phase 1 - MVP Stabilization

| ID | Task | Priority | Depends On | Status |
|----|------|----------|------------|--------|
| DEP-02 | Apply `migrations/004_multi_assignees_departments_cng_admin.sql`, `migrations/005_future_foundation_alignment.sql`, `migrations/006_calendar_events_and_public_holidays.sql`, `migrations/007_leave_attachments_calendar_details.sql`, `migrations/008_ticket_priority.sql`, `migrations/009_notifications.sql`, and `migrations/010_team_attendance.sql`, upload changed PHP/CSS files, and run live role/filter checks | HIGH | TKT-04, FND-02, FND-03, LVE-01, CAL-01, CAL-03, CAL-04, ATT-02, TKT-07, NTF-02, ATTEND-01, NTF-03 | PENDING |
| QA-02 | Verify multi-assignee create/edit/detail/register/export behavior with real role accounts | HIGH | DEP-02 | COMPLETE LOCALLY |
| QA-03 | Verify Team Leader can only see tickets assigned to them and cannot view unassigned/other-assigned ticket URLs | HIGH | DEP-02 | COMPLETE LOCALLY |
| QA-04 | Verify `cng-admin` can view/filter tickets only and has no create/edit/export/admin actions | HIGH | DEP-02 | COMPLETE LOCALLY |
| QA-05 | Verify dashboard range counts, attachment permissions, leave approvals, and Team Calendar on production | HIGH | DEP-02 | PENDING |
| QA-06 | Verify interactive calendar event/holiday creation and PH/AU/CA holidays on production | HIGH | DEP-02 | PENDING |
| QA-07 | Verify calendar item details and leave attachment download permissions on production | HIGH | DEP-02 | PENDING |

### Production Handoff Checklist (planning only)

These are deployment-phase tasks and are not being executed in the current local-only cycle:

| ID | Task | Status |
|----|------|--------|
| DEP-02A | Back up the production database and record the backup location before changes | PENDING |
| DEP-02B | Apply the required migrations in order, including migrations 011 through 017 where applicable | PENDING |
| DEP-02C | Upload the approved PHP, CSS, asset, storage, and configuration changes | PENDING |
| DEP-02D | Confirm production configuration, SMTP settings, file permissions, and private-storage protection | PENDING |
| DEP-02E | Run production health check and smoke-test login, roles, tickets, calendar, leave, imports, reports, and audit access | PENDING |
| DEP-02F | Run QA-05 through QA-07 with real role accounts and record results | PENDING |
| DEP-02G | Remove one-time deployment scripts after successful use and confirm they are unavailable by URL | PENDING |

## Phase 2 - Foundation Scaffolding

| ID | Task | Priority | Depends On | Status |
|----|------|----------|------------|--------|
| FND-02 | Bring `database/schema.sql` into parity with applied future-foundation migrations: `company_holidays`, `leave_requests`, `team-member`, and `access_leave_request_module` | HIGH | DEP-02 | COMPLETE |
| FND-03 | Align attachment metadata naming with the requested confidential certificate model while preserving existing `ticket_attachments` compatibility | MEDIUM | FND-02 | COMPLETE |
| LVE-01 | Add a protected placeholder Leave Request route visible only to `team-member` users with `access_leave_request_module` | HIGH | FND-02 | COMPLETE |
| CAL-01 | Document Team Calendar data sources: `company_holidays`, approved leave schedules, and future Team Attendance | MEDIUM | FND-02 | COMPLETE |

## Phase 3 - Future Feature Builds

| ID | Task | Priority | Depends On | Status |
|----|------|----------|------------|--------|
| ATT-01 | Build confidential medical certificate upload/download UI with `view_attachments` permission checks | MEDIUM | FND-03 | COMPLETE |
| LVE-02 | Build Team Member self-service leave request submission UI | MEDIUM | LVE-01 | COMPLETE |
| LVE-03 | Build sequential approval workflow: Team Leader approval, then Department Head approval | MEDIUM | LVE-02 | COMPLETE |
| CAL-02 | Build Team Calendar view from holidays, approved leave, and attendance sources | LOW | CAL-01, LVE-03 | COMPLETE |
| CAL-03 | Convert Team Calendar to clickable month grid with custom events, company holidays, and seeded PH/AU/CA 2026 public holidays | HIGH | CAL-02 | COMPLETE |
| TKT-05 | Add ticket aging and SLA warning indicators for old or idle tickets | HIGH | DEP-02 | COMPLETE |
| TKT-06 | Add My Work Queue sidebar page for tickets assigned to the current user | HIGH | TKT-05 | COMPLETE |
| TKT-07 | Add ticket priority field: Low, Normal, High, Urgent | HIGH | TKT-06 | COMPLETE |
| UI-08 | Make dashboard cards clickable drill-down shortcuts into filtered ticket views | MEDIUM | TKT-07 | COMPLETE |
| UI-09 | Add recent activity panel: new comments, recently updated tickets, recently closed tickets | MEDIUM | UI-08 | COMPLETE |
| DEP-03 | Add department workload/logging view focused on Team Leader department tracking | MEDIUM | UI-09 | COMPLETE |
| LVE-04 | Add leave dashboard counts: TL approvals, Department Head approvals, approved leave this week | MEDIUM | LVE-03 | COMPLETE |
| UI-11 | Move ticket dashboard and recent activity into a dedicated Dashboard sidebar page | HIGH | UI-09 | COMPLETE |
| NTF-02 | Add in-app notification center for assignments, comments, approvals, and follow-ups | MEDIUM | UI-09 | COMPLETE |
| ATT-02 | Add simple required supporting attachment for leave requests, accepting screenshot/photo/PDF | MEDIUM | LVE-02, ATT-01 | COMPLETE |
| CAL-04 | Add clickable calendar item details for holidays/events/leave, including leave reason and supporting attachment links | HIGH | CAL-03, ATT-02 | COMPLETE |
| UI-10 | Add ticket trend bar graph with daily/weekly/monthly and 3/6/9/12-month ranges | MEDIUM | UI-07 | COMPLETE |
| ATTEND-01 | Add Team Attendance module and feed attendance records into Team Calendar | LOW | CAL-03 | COMPLETE |
| NTF-03 | Add follow-up button for pending/overdue tickets that emails assignees | HIGH | TKT-05, NTF-02 | COMPLETE |

## Already Present

| Item | Location | Status |
|------|----------|--------|
| Confidential attachment metadata hook | `database/schema.sql` table `ticket_attachments` | PRESENT |
| Attachment visibility permission | `view_attachments` permission | PRESENT |
| Team Calendar disabled nav item | `app/layout.php` | PRESENT |
| Calendar/leave additive migration | `migrations/002_leave_and_calendar_foundation.sql` | PRESENT |
| Team Member leave permission scope | `access_leave_request_module` in migration 002 | PRESENT |
| Team Member module boundary | `leave-requests.php` and permission-based nav | PRESENT |

## Phase 4 - Post-MVP Hardening and Operations

> Approved scope from the 2026-08-05 product review. Execute one task per cycle.

| ID | Task | Priority | Depends On | Status |
|----|------|----------|------------|--------|
| AUTH-01 | Add secure password recovery with expiring, single-use email tokens and password reset audit entries | HIGH | DEP-02 | COMPLETE LOCALLY |
| ADM-02 | Create a dedicated Super Admin-only Users sidebar module with user create/read/update/deactivate/delete controls, email fields, department assignment, role selection, temporary-password handling, and permission override management | HIGH | AUTH-01 | COMPLETE LOCALLY |
| TKT-08 | Add Super Admin-only restore for soft-deleted tickets, with confirmation and audit history | HIGH | ADM-02 | COMPLETE LOCALLY |
| AUD-01 | Add a Super Admin-only immutable administrative audit log for user, role, permission, restore, leave, attachment, and calendar administration actions | HIGH | ADM-02, TKT-08 | COMPLETE LOCALLY |
| SLA-01 | Replace hard-coded aging thresholds with configurable SLA rules by priority, including due dates, business-day handling, and escalation notifications | HIGH | TKT-07, NTF-03 | COMPLETE LOCALLY |
| SRCH-01 | Add indexed ticket search and scalable register pagination/filtering for ticket number, subject, employee, description, comments, assignee, department, status, and priority | HIGH | DEP-02 | COMPLETE LOCALLY |
| BULK-01 | Add optional permission-gated bulk ticket actions for assignment, status, priority, archive/delete, and export selection; Super Admin controls access | MEDIUM | ADM-02, SRCH-01 | COMPLETE LOCALLY |
| TKT-09 | Add private ticket comments/internal notes with visibility limited by configured department and/or assignee access | HIGH | ADM-02, AUD-01 | COMPLETE LOCALLY |
| CAL-05 | Add controlled calendar event/holiday edit and delete actions, with Super Admin/authorized-role access and audit history | MEDIUM | AUD-01 | COMPLETE LOCALLY |
| IMP-01 | Add permission-gated CSV import for users, departments, tickets, and holidays with validation preview and error report | MEDIUM | ADM-02, SRCH-01 | COMPLETE LOCALLY |
| RPT-01 | Add operational reports for first response, resolution time, SLA compliance, reopen rate, workload by assignee/department, leave approvals, and trend exports | MEDIUM | SLA-01, SRCH-01 | COMPLETE LOCALLY |
| OPS-01 | Add production observability: structured application logs, safe error reporting, health check, failed-email visibility, and database backup verification checklist | HIGH | DEP-02 | COMPLETE LOCALLY |
| QA-08 | Build repeatable automated regression tests for authentication, permissions, direct URLs, CSRF, ticket visibility, attachments, leave transitions, and migrations | HIGH | ADM-02, TKT-09, SLA-01 | COMPLETE LOCALLY |

## Explicitly Deferred or Rejected

| Item | Decision | Reason |
|------|----------|--------|
| MFA, CAPTCHA, and account lockout | DEFERRED | Not needed for the current operating model; revisit after password recovery and production security review |
| Ticket templates and recurring tickets | REJECTED | Not needed now |
| Requester/customer portal and requester user accounts | REJECTED | Not needed now |
| Leave balances, leave types, and payroll-style leave policy | REJECTED | Too complex for the current workflow |
| Attachment virus scanning, preview, quotas, and retention | REJECTED | Not needed now |
| API, webhooks, Slack/Teams, and calendar integrations | DEFERRED | Integration path is not yet defined |

## Task Status Key

| Status | Meaning |
|--------|---------|
| PENDING | Not started |
| IN_PROGRESS | Currently executing |
| COMPLETE | Done and usable |
| BLOCKED | Waiting on dependency |
| REJECTED | Will not implement; see decisions |
