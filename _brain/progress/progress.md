# Progress

## MVP Status

**Deployed and in use** — local and live production checks passed on 2026-07-25.

The live MVP includes authentication, database-driven access control, ticket creation and workflow, comments and audit history, management notifications, ticket export permissions, the Team Calendar placeholder, pagination, and shareable on-screen ticket filters.

## Completed

| ID | Task | Date |
|---|---|---|
| FND-01 | Schema, setup, authentication, and RBAC | 2026-07-25 |
| UI-01 | Branded dashboard and local dual-logo header | 2026-07-25 |
| TKT-01 | Ticket creation, editing, workflow, comments, history, and soft deletion | 2026-07-25 |
| ADM-01 | Roles, access management, user activation, and password resets | 2026-07-25 |
| NTF-01 | SMTP hooks and protected local configuration | 2026-07-25 |
| UI-02 | Permission-aware ticket and user-management action links | 2026-07-25 |
| VRF-01 | Local MVP verification and deployment handoff | 2026-07-25 |
| UI-03 | Sidebar layout and ticket-page density polish | 2026-07-25 |
| F001 | Dynamic sidebar active state | 2026-07-25 |
| F002 | Ticket detail action-button sizing and spacing | 2026-07-25 |
| QA-01 | Role-based local test accounts and authentication checks | 2026-07-25 |
| MIG-002 | Leave and calendar foundation migration | 2026-07-25 |
| F003 | Shared styling for login page | 2026-07-25 |
| EXP-01 | Permission-gated CSV and Excel ticket exports | 2026-07-25 |
| UI-04 | Dual-logo login header | 2026-07-25 |
| UI-05 | Product naming and login copy refinement | 2026-07-25 |
| UI-06 | Login eyebrow removal | 2026-07-25 |
| F004 | Ticket action feedback and delete confirmation | 2026-07-25 |
| F005 | Case-normalized username lookup | 2026-07-25 |
| TKT-02 | Permission-protected paginated ticket register | 2026-07-25 |
| F006 | Merge pagination into canonical Tickets page | 2026-07-25 |
| TKT-03 | Shareable on-screen ticket filters | 2026-07-25 |
| F007 | Sidebar active-state consistency | 2026-07-27 |
| TKT-04 | Multi-assignees, involved departments, Team Leader scoping, CNG Admin role, and clearer filters | 2026-08-04 |
| FND-02 | Future foundation schema parity for leave, calendar, Team Member, and permissions | 2026-08-04 |
| FND-03 | Medical certificate attachment metadata alignment | 2026-08-04 |
| LVE-01 | Team Member-only Leave Request placeholder route | 2026-08-04 |
| CAL-01 | Team Calendar source planning note and disabled nav state | 2026-08-04 |
| F009 | Fresh post-login permission landing | 2026-08-04 |
| UI-07 | Ticket dashboard metrics with date-range selector | 2026-08-05 |
| ATT-01 | Confidential ticket attachment UI with permission-gated upload/download | 2026-08-05 |
| LVE-02 | Leave request self-service and sequential approval UI | 2026-08-05 |
| CAL-02 | Team Calendar UI for holidays and approved leave | 2026-08-05 |
| CAL-03 | Interactive Team Calendar grid, custom events, company holidays, and seeded PH/AU/CA public holidays | 2026-08-05 |
| CAL-04 | Calendar item details for holidays/events/leave with leave reason and attachment downloads | 2026-08-05 |
| ATT-02 | Required leave request supporting screenshot/photo/PDF upload | 2026-08-05 |
| TKT-05 | Ticket aging and SLA warning indicators | 2026-08-05 |
| TKT-06 | My Work assigned-ticket queue | 2026-08-05 |
| TKT-07 | Ticket priority field and urgent dashboard count | 2026-08-05 |
| UI-08 | Clickable dashboard drill-down cards | 2026-08-05 |
| UI-09 | Dashboard recent activity panel | 2026-08-05 |
| DEP-03 | Department workload and Team Leader department logging view | 2026-08-05 |
| LVE-04 | Leave dashboard approval and weekly approved counts | 2026-08-05 |
| UI-11 | Dedicated Dashboard page for ticket dashboard and recent activity | 2026-08-05 |
| NTF-02 | In-app notification center for assignments, comments, and approvals | 2026-08-05 |
| UI-10 | Ticket trend bar graph with daily, weekly, and monthly views | 2026-08-05 |
| ATTEND-01 | Team Attendance module feeding Team Calendar | 2026-08-05 |
| NTF-03 | Follow-up button for pending/overdue tickets that emails assignees | 2026-08-05 |
| QA-02 | Local multi-assignee create/edit/detail/register/export verification | 2026-08-05 |
| QA-03 | Local Team Leader assigned-ticket-only visibility verification | 2026-08-05 |
| QA-04 | Local CNG Admin view/filter-only access verification | 2026-08-05 |

## Deployment Outcome

- Production site: `https://cng-tickets.stratastaff.com/`
- Database imported and configured in cPanel.
- Local and live ticket-register pagination confirmed working.
- Local and live ticket-list filtering confirmed ready for use.
- Production SMTP credentials remain managed only in the server-local configuration file.

## Next Phase

Apply `migrations/004_multi_assignees_departments_cng_admin.sql`, updated `migrations/005_future_foundation_alignment.sql`, `migrations/006_calendar_events_and_public_holidays.sql`, `migrations/007_leave_attachments_calendar_details.sql`, `migrations/008_ticket_priority.sql`, `migrations/009_notifications.sql`, and `migrations/010_team_attendance.sql` before deploying this feature set. Upload the new UI files `dashboard.php`, `notifications.php`, `team-calendar.php`, `team-attendance.php`, `leave-requests.php`, `department-workload.php`, `download-attachment.php`, `download-leave-attachment.php`, `app/notifications.php`, and `storage/private/.htaccess` with the changed PHP/CSS files.

Last updated: 2026-08-05
