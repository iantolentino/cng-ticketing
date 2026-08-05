# Backlog

> Tasks are ordered by dependency. Execute one task per cycle and verify before moving on.

## Phase 1 - MVP Stabilization

| ID | Task | Priority | Depends On | Status |
|----|------|----------|------------|--------|
| DEP-02 | Apply `migrations/004_multi_assignees_departments_cng_admin.sql`, `migrations/005_future_foundation_alignment.sql`, `migrations/006_calendar_events_and_public_holidays.sql`, `migrations/007_leave_attachments_calendar_details.sql`, `migrations/008_ticket_priority.sql`, `migrations/009_notifications.sql`, and `migrations/010_team_attendance.sql`, upload changed PHP/CSS files, and run live role/filter checks | HIGH | TKT-04, FND-02, FND-03, LVE-01, CAL-01, CAL-03, CAL-04, ATT-02, TKT-07, NTF-02, ATTEND-01 | PENDING |
| QA-02 | Verify multi-assignee create/edit/detail/register/export behavior with real role accounts | HIGH | DEP-02 | PENDING |
| QA-03 | Verify Team Leader can only see tickets assigned to them and cannot view unassigned/other-assigned ticket URLs | HIGH | DEP-02 | PENDING |
| QA-04 | Verify `cng-admin` can view/filter tickets only and has no create/edit/export/admin actions | HIGH | DEP-02 | PENDING |
| QA-05 | Verify dashboard range counts, attachment permissions, leave approvals, and Team Calendar on production | HIGH | DEP-02 | PENDING |
| QA-06 | Verify interactive calendar event/holiday creation and PH/AU/CA holidays on production | HIGH | DEP-02 | PENDING |
| QA-07 | Verify calendar item details and leave attachment download permissions on production | HIGH | DEP-02 | PENDING |

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

## Task Status Key

| Status | Meaning |
|--------|---------|
| PENDING | Not started |
| IN_PROGRESS | Currently executing |
| COMPLETE | Done and usable |
| BLOCKED | Waiting on dependency |
| REJECTED | Will not implement; see decisions |
