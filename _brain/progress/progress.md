# Progress

## Ticket Bulk Actions Toolbar (2026-09-03)

- Moved the existing permission-protected bulk ticket form beside the Tickets filter panel and gave it a compact card layout matching the current ticket controls.
- Preserved the existing POST route, CSRF token, confirmation prompt, field names, permissions, and backend behavior. The controls stack responsively below the filters on smaller screens.
- Local browser verification passed at 1280x720 and 100% zoom; the rendered bulk card is 300px wide and 220px high instead of the prior 953px-wide, 366px-high section after pagination.
- No database migration or live-site upload was performed for this UI-only change.

Last updated: 2026-09-03

## MVP Status

**Deployed and in use** — local and live production checks passed on 2026-07-25.

The live MVP includes authentication, database-driven access control, ticket creation and workflow, comments and audit history, management notifications, ticket export permissions, the Team Calendar placeholder, pagination, and shareable on-screen ticket filters.

## Completed

| AUTH-02 | Pending account registration with Super Admin approval workflow | 2026-08-14 |
| API-01 | Token-authenticated public ticket feed, token rotation, access logging, and structured ticket activity logging | 2026-08-14 |
| TKT-10 | Separate issue/resolution fields, resolution-driven status, and public feed fields | 2026-08-14 |
| DEP-04 | Prepare one-time unused-production cleanup and Super Admin seed script | 2026-08-14 |
| AUTH-03 | Add visible registration link to the login page | 2026-08-14 |

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
| ADM-03 | Super Admin account creation, bulk CSV import, role assignment, and permission management | 2026-08-10 |
| OPS-01 | One-shot account cleanup script with hardcoded Super Admin preservation and self-deletion | 2026-08-10 |
| OPS-02 | One-time deployment password reset script with forced password change and self-deletion | 2026-08-11 |

## Deployment Outcome

- Production site: `https://cng-tickets.stratastaff.com/`
- Database imported and configured in cPanel.
- Local and live ticket-register pagination confirmed working.
- Local and live ticket-list filtering confirmed ready for use.
- Production SMTP credentials remain managed only in the server-local configuration file.

## Production Upload Confirmation and Team Member Account Follow-up (2026-08-31)

- User confirmed the latest cPanel upload was applied and Team Calendar and Team Attendance are working in production.
- Reviewed the supplied `CNG_Staff_List_updated.xlsx`: it contains 70 employee names/team labels and 3 TL rows, but no individual email addresses. Do not fabricate login emails from names; the account setup flow must collect exact emails.
- Next implementation: Super Admin bulk setup for the 70 employee rows with one-time temporary credentials, forced password change, authenticated password-change access, and created-by-only Team Member ticket visibility.
- Completed locally: the Users page now accepts exact employee emails from the 70-row directory, creates Team Member accounts with one-time random temporary passwords, and displays the credentials once to the Super Admin. Existing exact-name accounts are not duplicated.
- Completed locally: Team Members receive view/create ticket access through migration `023_team_member_ticket_access.sql`; shared native ticket queries/details and external-ticket loading enforce created-by-only visibility, while analytics/calendar routes are blocked. Settings links to the authenticated password-change screen.
- Local verification: migration `023` applied successfully; Users browser check showed 70 rows and 68 new-account fields; PHP lint passed; regression harness passed 116 checks with 0 failures. The user clarified on 2026-09-02 that migration `023` and the current synchronized change set have not yet been uploaded/applied in cPanel; earlier production-upload notes are historical and superseded for this deployment set.

## Account Menu and Theme Settings (2026-08-31)

- Completed locally: moved the Settings link out of the sidebar into the upper-right account menu while retaining the visible sidebar account/sign-out footer.
- Completed locally: converted the authenticated password route into the shared Settings page with Light/Dark theme buttons and the existing password-change flow. Theme choice is browser-local and does not require a database update.
- Completed locally: hid the desktop sidebar scrollbar while retaining internal navigation scrolling so the sidebar footer remains accessible at normal zoom.
- Verification complete locally: XAMPP PHP lint passed for `app/layout.php` and `change-password.php`, JavaScript syntax check passed, the regression harness passed 122 checks with 0 failures, and browser interaction checks passed at 1280x720/device scale 1 (100% zoom). The account menu, Settings route, theme switching/persistence, hidden sidebar scrollbar, and in-viewport footer were all confirmed.

## Next Phase

Apply `migrations/004_multi_assignees_departments_cng_admin.sql`, updated `migrations/005_future_foundation_alignment.sql`, `migrations/006_calendar_events_and_public_holidays.sql`, `migrations/007_leave_attachments_calendar_details.sql`, `migrations/008_ticket_priority.sql`, `migrations/009_notifications.sql`, and `migrations/010_team_attendance.sql` before deploying this feature set. Upload the new UI files `dashboard.php`, `notifications.php`, `team-calendar.php`, `team-attendance.php`, `leave-requests.php`, `department-workload.php`, `download-attachment.php`, `download-leave-attachment.php`, `app/notifications.php`, and `storage/private/.htaccess` with the changed PHP/CSS files.

Last updated: 2026-08-05

## Post-upload UI verification and follow-up fixes (2026-09-03)

- Rechecked the authenticated live site and local site in the browser. All visible sidebar routes loaded on local; live also loaded every existing route except `recruitment.php`, which returned a real 404 even though its sidebar link is present.
- Confirmed live is serving the previously uploaded cache-busted assets (`20260903-dashboard-departments`), not this follow-up pass. Live 7D/30D still rendered only one monthly `Aug 2026` chart bucket, the account menu still had only Settings, the sidebar footer was still present, and health still served the older reduced check set.
- Fixed locally: 7D and 30D force daily chart buckets; attendance staff selection has a labeled, styled checkbox picker; Users setup is a responsive two-column card grid; Recruitment is a two-column name-first editor with an edit icon and preserved update POST flow; Calendar Administration uses shared control sizing; health log columns no longer overlap; and Settings/Sign out now live in the upper-right account menu with no sidebar footer or caret.
- Browser verification passed locally at the available normal 100% viewport in light and dark themes. The local Recruitment editor opens its fields on employee-name click, and local sidebar smoke testing found no 404, blank page, or PHP error. Live requires the new changed files to be uploaded before these follow-up fixes can be rechecked there.
- No database schema change was added by this follow-up. Migration `024_recruitment_directory.sql` remains required for the Recruitment data fields if not already applied in production.

Last updated: 2026-09-03

## Dashboard and Department External Ticket Fixes

Completed locally on 2026-09-03 after checking live and local independently. Live contains 580 merged external tickets but the dashboard counted 523 because 57 source tickets use statuses outside the native four-status set; the dashboard now includes them under Other. External records now map support to IT Department, escalations to HR Department, requisition to Finance Department, and the training desk source to L&D. Departments now merges external records and keeps external rows read-only, while native department logging remains unchanged. Ticket detail pages now show Back to tickets. Local 7D and 30D dashboard controls were browser-tested and were already returning correct subsets; no separate range calculation change was necessary.

The current task requires no database migration. cPanel must upload the changed application files and retain the existing server-local external connection configuration.

Last updated: 2026-09-03

## Login Intro Panel Layout Fix (2026-09-03)

- Local login now disables inherited auth-body centering and explicitly stretches the shell, so the blue intro section is flush with the left/top/bottom viewport edges and fills the full-height left grid column; the sign-in card remains centered on the right.
- Existing mobile login stacking and spacing rules remain unchanged.
- Browser verification passed locally at 1280x720 and 100% zoom; no database migration, live deployment, or GitHub update was performed.

Last updated: 2026-09-03

## Recruitment Directory

Added locally on 2026-09-03: recruitment.php displays all active staff-directory rows and allows hire date, shift schedule, training status, and position updates. Migration 024_recruitment_directory.sql adds the fields and manage_recruitment permission. Access is granted to Super Admin, Management, Team Leader, Department Head, and Pod Leader; Team Member and CNG Admin are explicitly denied.

Last updated: 2026-09-03

## Site-wide UI consistency and dark theme pass

- Audited all authenticated local application screens at 1280x720 and 100% browser zoom in light and dark themes, using Dashboard and Tickets as the light-mode reference.
- Standardized supporting page headings, Users and Calendar Administration forms, Reports filters, attendance actions, and Create/Edit Ticket form layout without changing Dashboard or Tickets light-mode styling.
- Completed dark-theme coverage for the sidebar, controls, selects, cards, tables, calendars, ticket SLA states, notifications, account screens, and supporting text contrast.
- Independently audited the deployed live site before upload, then rechecked it after the user uploaded the files. The live site now serves the cache-busted current baseline stylesheet; the prior giant unsized SVGs and missing component layouts are gone.
- Local verification passed: 100% zoom visual checks in both themes, PHP lint, `git diff --check`, and 122 regression checks with 0 failures.
- Post-upload live verification passed across every available sidebar route in both light and dark themes at normal zoom. Dashboard has one expected large chart SVG; no other route has an oversized SVG.

Last updated: 2026-08-31

## Local, GitHub, and Live Sync Check (2026-09-01)

- The synchronized application commit is now `01cdb2d8b24f5bde557f1a53532bc04f3d267245`, and both GitHub `origin/main` and `origin/codex/add-presenter-guide` point to it.
- All selected application changes are committed and pushed. Only intentionally excluded untracked diagnostics, reset/seed utilities, generated reports, and local artifacts remain outside GitHub.
- The live site serves the cache-busted `app.css` and `ui-fixes.css` uploaded on 2026-08-31; browser checks confirmed the current Tickets UI and shortened `#number` labels.
- Live page behavior matches the committed/uploaded application UI for the checked surface. cPanel is still a manual deployment and was not changed by the GitHub push.

Last updated: 2026-09-01

## Live Deployment Health Divergence (2026-09-01)

- Live route/design checks still load successfully, but live `health.php` does not expose the same required-table and private-storage checks present in the committed repository version.
- Live reports `Database Healthy` and `SMTP Configured`, but both private attachment directories are missing or not writable. This is a production file/permission/configuration discrepancy, not proof of schema parity.
- The user clarified on 2026-09-02 that production migration `023_team_member_ticket_access.sql` has not yet been uploaded/applied in cPanel for the current synchronized change set. Earlier production-upload notes are historical and superseded for this deployment set.

Last updated: 2026-09-01

## Live UI Upload Verification (2026-08-31)

- Confirmed the live site serves `assets/css/app.css?v=20260831-ui-consistency-dark` after the user's cPanel upload.
- Rechecked 18 authenticated live routes in light and dark themes at 100% zoom/device pixel ratio 1; all rendered their main content with no 404, blank page, PHP error, or light-surface leftovers.
- Rechecked every available live sidebar item; all routes loaded successfully. The Sign out link was not counted as an application page and the authenticated session was restored after testing.

Last updated: 2026-08-31

## Latest UI Safety and Layout Update

Added consistent confirmation prompts and completion notifications for operational POST actions across the shared ticketing pages. Added spacing below the ticket detail WATCH banner so the Edit details and Delete ticket actions are visually separated. PHP syntax validation and whitespace checks passed.

Last updated: 2026-08-11

## Production Database Configuration

Added `config/config.production.example.php` as a deployment template for live database and SMTP credentials. Local test tickets were not deleted because `seed_test_tickets.php` and local data are excluded from deployment; production will read the separate server-local `config/config.local.php` and use the designated live database.

Last updated: 2026-08-12

## Production Handoff Planning

No deployment actions were performed. Added explicit planning tasks `DEP-02A` through `DEP-02G` to `backlog.md` for database backup, ordered migrations, approved file upload, production configuration, health/smoke testing, QA-05 through QA-07 execution, and removal verification for one-time deployment scripts.

Last updated: 2026-08-11

## CAL-05 / IMP-01 / RPT-01 / OPS-01 / QA-08 Verification

Verified that calendar administration, validated CSV import, operational reports, production observability/health checks, and automated regression coverage are implemented locally. The regression harness passed 42 checks with no failures; targeted PHP syntax checks also passed. These items remain deployment-dependent where noted in the execution queue.

Last updated: 2026-08-11

## TKT-09 Verification

Verified that ticket comments already support shared, assignee-only, and department-only visibility in `ticket.php`. Comment retrieval applies server-side visibility rules based on the author, assigned users, and involved departments; the UI labels restricted comments accordingly. No duplicate implementation was added.

Last updated: 2026-08-11

## SRCH-01 / BULK-01 Verification

Verified that the ticket register already supports keyword and multi-field filtering, shareable filter URLs, pagination, and dashboard views. Verified that bulk status, priority, and soft-delete actions are protected by the `bulk_ticket_actions` permission, CSRF validation, transaction handling, activity/audit logging, and the shared confirmation/success feedback pattern. Migration `014_bulk_ticket_actions_permission.sql` is required where the permission is not yet installed.

Last updated: 2026-08-11

## SLA-01 Verification

Verified that configurable SLA rules are already implemented through the `sla_rules` table and Super Admin controls in `admin.php`. Ticket and dashboard views apply priority-based open and idle thresholds for overdue and watch states, with administrative audit entries for rule changes. No duplicate implementation was added.

Last updated: 2026-08-11

## TKT-08 / AUD-01 Verification

Verified that Super Admin deleted-ticket restoration is already implemented through `deleted-tickets.php` and `restore-ticket.php`, including ticket activity and administrative audit entries. Verified that `app/audit.php`, `audit-log.php`, and the `admin_audit_log` schema support the administrative audit log. No duplicate implementation was added.

Last updated: 2026-08-11

## ADM-02 Verification

Verified that the dedicated Super Admin user-management and access controls are already present in `users.php` and `admin.php`, including account creation/editing, role and department assignment, activation, password reset access, deletion, role permissions, and per-user permission overrides. No duplicate implementation was added.

Last updated: 2026-08-11

## Latest Task

`AUTH-01` completed locally: password recovery now uses a one-hour, hashed, single-use token, generic account-enumeration-safe responses, SMTP delivery, password confirmation, and inactive-account protection. Production requires migration `011_password_reset_tokens.sql` and configured SMTP.

## Approved Next Product Phase

The next product phase is documented in `backlog.md` under **Phase 4 - Post-MVP Hardening and Operations**.

Priority order:

1. `AUTH-01` - password recovery.
2. `ADM-02` - dedicated Super Admin Users module and permission management.
3. `TKT-08` and `AUD-01` - Super Admin restore and administrative audit log.
4. `SLA-01` - configurable SLA rules and escalation.
5. `SRCH-01` and `BULK-01` - scalable search, pagination, and optional bulk actions.
6. `TKT-09` - private department/assignee comments.
7. `CAL-05`, `IMP-01`, `RPT-01`, `OPS-01`, and `QA-08`.

Pagination already exists in the current ticket register. `SRCH-01` therefore focuses on indexed search and ensuring pagination remains performant as data grows.

Last updated: 2026-08-05
