# Current State

## System State

EXECUTION_MODE

## Current Phase

Deployment preparation and post-deployment stabilization; August 10–14 feature work is recorded, with existing-ticket migration planned for the following week.

## Last Completed Task

DEP-04 - One-time unused-production cleanup and Super Admin seed script
Completed: 2026-08-14

## Next Task

MIG-003 - Migrate existing tickets after deployment stabilization
Depends on: approved source data and production migration planning.

## Active Blockers

No technical blocker recorded; production evidence and ticket migration remain follow-up work.

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

QA-08 completed locally: added `tests/regression.php`, a dependency-free CLI regression harness covering required routes, migration continuity/markers, CSRF/XSS primitives, Super Admin boundaries, permission gates, and CSRF-protected admin actions. It passes 39 checks; all root and app PHP files also passed syntax lint.

2026-08-05 permission alignment completed locally: migration `017_ticket_creation_role_scope.sql` makes Super Admin and Team Leader the default ticket creators, revokes the default create permission from other roles, keeps Team Members on leave/medical-certificate requests only, and preserves Team Leader assigned-ticket-only visibility. Regression checks now pass 42 checks.

QA operating rule: use Hermes as the default local QA executor to save Codex tokens. Send Hermes `_brain/prompts/qa-test-hermes.md`, then paste its concise result into `_brain/prompts/qa-test-hermes-response.md` for Codex review. Prefer static/CLI validation when browser automation is unavailable.

QA-02 completed locally: Super Admin HTTP flow created ticket `10` with 2 assignees and 2 departments, confirmed detail/register/CSV export display, then edited it down to 1 assignee and 1 department with urgent priority. A small `posted_ids()` compatibility tweak now accepts both normal PHP array posts and explicit `[]` keys.

QA-03 completed locally: Created QA tickets `11` and `12`; `test.teamleader` saw assigned ticket `11` in Tickets and My Work, did not see other-assigned ticket `12`, direct `ticket.php?id=11` returned 200, and direct `ticket.php?id=12` returned 404.

QA-04 completed locally: `test.cngadmin` can load Dashboard, filtered Tickets, and ticket detail with HTTP 200. It does not see Edit/Workflow actions on ticket detail, does not see My Work/Departments/Team Calendar sidebar links, and receives HTTP 403 for create, edit, export, admin, My Work, Department workload, Team Calendar, and Team Attendance routes.

Deployment guide created locally: `_brain/deployment/cpanel_upgrade_local_changes_2026-08-05.md` documents how to upgrade the already deployed cPanel site without re-running setup or overwriting `config/config.local.php`. It instructs backing up production, applying additive migrations `004` through `010`, uploading changed files, setting private storage permissions, and smoke-testing role boundaries.

Upload manifest created locally: `_brain/deployment/cpanel_upload_manifest_2026-08-05.md` lists exact cPanel upload files and explicit do-not-upload local/private artifacts.

Last updated: 2026-08-14

## CITS Day 1 and Day 2 Deployment State (2026-08-20)

- Day 1 CITS hardening is complete and must not be repeated: the Day 1 application/database changes and migration `021_day1_hardening.sql` were committed and pushed.
- Day 2 production changes are all root-level PHP files: `users.php`, `import.php`, and `leave-requests.php`.
- Day 2 local validation is complete: `tests/regression.php` passes 57 checks with 0 failures and PHP syntax lint passes across the application.
- `tests/regression.php`, `_brain/`, fix logs, and DOCX reports are validation/documentation artifacts; they are not production web uploads.
- Day 2 role-based, edge-case, and UI/browser tasks require deployed execution evidence before their QA status can be marked Done.
- When asked what remains for Day 2, start with the three root PHP files above and do not re-open Day 1 unless deployment verification shows it was not applied.

Last updated: 2026-08-20

## CITS Day 7-10 Local Completion State (2026-08-28)

- Day 7 local UI/accessibility hardening is complete: keyboard skip link, main-content landmark, reduced-motion support, and safe sessionStorage handling were added.
- Day 8 local API/export/operations hardening is complete: scalar query validation, distinct API search placeholders, bounded API pagination, calendar-valid export dates, bounded exports, required-table health checks, and extension checks were added.
- Day 9 local data-protection hardening is complete: `storage/private/.htaccess` denies direct web access and the health page verifies the protection marker.
- Day 10 local release validation is complete: the regression harness passes 73 checks with 0 failures and all changed production PHP files pass syntax lint.
- Browser/live execution was not independently completed in this environment because Apache was unavailable at `http://localhost/cng-ticketing/login.php`; live evidence must be recorded after deployment.
- Day 7-10 DOCX reports were updated with distinct daily task packages and local completion wording; they are documentation artifacts, not production uploads.

Last updated: 2026-08-28

## CITS Attendance and Ticket Form Updates (2026-08-29)

- Issue escalator is now captured from the signed-in submitter on ticket creation and is read-only during edits.
- Category mappings now drive the current department, involved-department options, and assignee options in create/edit ticket forms, with matching server-side department validation.
- Migration `022_staff_leave_attendance_updates.sql` adds the 73-row staff directory from `CNG_Staff_List_updated.xlsx`, records the three TL and 70 employee email domains, and adds the attendance-to-staff leave relationship.
- Registration accepts the existing `@stratastaff.com` domain plus `@stratastaffglobal.com` for TLs and `@jamesons.com.au` for Jamesons staff; the three matching local TL accounts were populated only when their email was previously blank.
- Team Attendance now supports the requested Annual, Sick, Emergency, Half-day, Birthday, Bereavement, Paternity, Maternity, and Undertime statuses, editing existing records, and multiple staff-on-leave checkboxes.
- Team Calendar includes the new attendance labels and staff-on-leave names in attendance details.
- Local migration application and smoke test passed; cPanel must apply migration `022` before uploading the dependent PHP files.

Last updated: 2026-08-29

## External Ticket Diagnostic Result (2026-08-29)

- Production diagnostic completed as Super Admin on the deployed CITS site.
- Live runtime is PHP `8.2.33` with PDO MySQL enabled; the external adapter file and server-local external configuration were found.
- Native database connection succeeded but reported `0` active native tickets.
- Four external source entries were found, but `0` were enabled, so `0` external tickets were fetched.
- Root cause of the empty ticket list is deployment configuration/data availability, not a source adapter runtime failure: enable the four intended external sources and verify the production database contains native tickets.
- Diagnostic script was designed to self-delete; confirm it is no longer accessible after troubleshooting.

Last updated: 2026-08-29

## External Ticket Live Troubleshooting (2026-08-29)

- User reports that the ticket list is not showing external tickets after the cPanel upload.
- The live URL responds with the CITS login page, so the domain/server is reachable; authenticated production behavior remains unverified.
- Created a temporary root-level `external-ticket-diagnostics.php` for one-run Super Admin checks of native DB access, external configuration, each source adapter, merged counts, and failure isolation.
- The diagnostic is intentionally not committed and is designed to delete itself after execution; any failed checks must be reported back without exposing credentials or ticket contents.

Last updated: 2026-08-29

## External Ticket Diagnostic Follow-up (2026-08-29)

- User reports that all four external sources were changed to enabled and credentials/database names were entered, but tickets still do not appear.
- Updated the temporary diagnostic to show a password-redacted exception reason per source, allowing connection, schema/table, and configuration-shape failures to be distinguished safely.
- Awaiting the second diagnostic result; no application code change is justified until the source-specific failure is identified.

Last updated: 2026-08-29

## External Ticket Diagnostic Configuration Finding (2026-08-29)

- Second production diagnostic confirmed all four external sources are enabled, but each fails before connection with `InvalidArgumentException: External connection is incomplete.`
- The production `config/config.external.php` must provide non-empty `host`, `database`, and `username` inside each source’s nested `connection` array; the integrated code does not use the proof-of-concept flat `dbname`, `user`, and `pass` keys.
- No application code or database migration change is indicated until the four server-local connection blocks are corrected.

Last updated: 2026-08-29

## External Sources Production Upload Confirmation (2026-08-29)

- User confirmed that all required external-ticket integration files were uploaded to cPanel.
- User updated the server-local external configuration, including the customer/name filter list used for CNG/Jamesons ticket matching.
- Credentials and other server-local configuration values are intentionally not recorded in the project brain or repository.
- This is a user-reported production upload confirmation; independent live verification was not performed in this session.

Last updated: 2026-08-29

## External CNG Ticket Sources (2026-08-29)

- Added `app/external_tickets.php` with four read-only list/thread adapters based on the supplied proof-of-concept schemas.
- Added optional ignored `config/config.external.php` loading and credential-free template `config/config.external.example.php`; supplied password-like values were not copied.
- The Issues register and ticket exports merge normalized external records with native tickets, preserve filtering/sorting/pagination, and namespace external links to prevent native ID collisions.
- External detail pages display the source and escaped conversation thread, expose unavailable SLA/Idle/department/subcategory fields as unavailable, and reject POST writes.
- Team Leaders see only external tickets assigned to their matching full name; one source failure is logged and skipped without hiding other records.
- Verification passed: PHP lint, `tests/regression.php` (99 checks), diff whitespace check, and normalization/filter/failure-isolation smoke test.

Last updated: 2026-08-29

## External Ticket Export Verification (2026-08-29)

- Reviewed `C:\Users\STRATA-IAN\Downloads\cng-jamesons-tickets.xlsx`, the supplied export of the merged ticket list.
- The workbook contains 580 ticket rows and the expected 11 system columns: Status, Priority, Subject, Departments, Category, Subcategory, Date Created, Date Updated, Date Closed, Assignees, and Employee.
- Ticket data is populated through 2026-08-28. The export contains 555 nonblank Date Closed values and 25 blank Date Closed values; the most common requester/employee names include Leonard Sunga, Sheena Magdaraog, and Trisha Balingit.
- The export does not include the external source badge/source key, so it verifies that ticket data is present in the merged export but cannot independently attribute rows to each of the four external databases.
- Local `config/config.external.php` was corrected to the required nested connection format using the user-provided proof-of-concept values and `localhost`; it remains Git-ignored and was not committed.
- Local connectivity smoke testing reached MySQL but all four source accounts were denied on this PC (`1044`/`1045`), so local source counts could not be confirmed. This does not invalidate the supplied live export; verify the same configuration through the deployed cPanel environment.
- No application-code or SQL change was needed from the workbook review. Existing regression coverage and read-only adapter safeguards remain applicable.

Last updated: 2026-08-29
