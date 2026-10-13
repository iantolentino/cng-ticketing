# Current State

## System State

EXECUTION_MODE

## GitHub Source Backup and Admin Cleanup (2026-10-13)

- Published the current local application source and schema migrations to GitHub main, preserving the October 7 commit through a merge. Configuration, credentials, staff files, private evidence and database contents remain excluded.
- Private Admin scratch archive: `.local/cng-admin-archive-20261013.zip`; 565 files verified by SHA-256. Original scratch files remain because automatic approval review blocked their deletion. Removed the clean October 7 worktree through Git after verifying its history was preserved.
- Backup validation and the two existing stale regression assertions are recorded in `reports/2026-10-13-source-backup.md`. No production deployment was performed.

## Live Deploy Accepted (2026-10-13)

The seven-feature roster/leave/calendar release is live and accepted by the user ("all are fine and tested").

- Database: `migration-live-additive-20261012.sql` was imported by hand in cPanel phpMyAdmin - 76 queries, no errors. Re-proved here on a pristine 73-row import of the production dump: 102 staff, 92 with a department, 76 with a shift, 13 calendar events linked to staff, 0 duplicate names, and a second run is a complete no-op.
- Files: the 13 tracked pages/assets plus the new `app/roster.php`, uploaded as a code-only zip and extracted over the application root. No `.sql` and no documentation went to the server.
- Follow-up: a live 500 on `department-workload.php` was isolated to `pagination_state`/`pagination_nav` missing from the live bootstrap and fixed inside the page (F092), so that route no longer depends on a bootstrap update.
- Rollback source: only the cPanel account backup is certain to hold the previous file versions, because the local `deployment/` artifacts were removed after the upload and nothing in this checkout is a rollback copy. If the pre-upload zip was downloaded with `cng-file-backup.php` before the extract, that zip (13 prior file versions plus a SHA256 manifest) is the faster route - but whether it was kept has not been confirmed.
- Not blocking live, still open from the 2026-10-12 goal: Team Calendar read-only for CNG Admin, team-leader `view_attachments` (migration 028), local SME/Department Head test accounts, the F086 fix_log placeholder, and whether to create login accounts for the new team leaders.

## Current Phase

Deployment preparation and post-deployment stabilization; August 10–14 feature work is recorded, with existing-ticket migration planned for the following week.

## Latest UI Change (2026-09-03)

The Tickets page now places the existing bulk status/priority/soft-delete form beside the ticket filters in a compact, baseline-aligned card. Its endpoint, permissions, CSRF protection, confirmation, and field names are unchanged. Local browser verification passed at 1280x720 and 100% zoom. Upload `index.php`, `assets/css/ui-fixes.css`, and `app/layout.php` to cPanel; no database migration is required.

## Latest UI Cleanup (2026-09-03)

Removed external-source warning banners from Tickets and Departments and removed redundant main-content heading blocks from Calendar Administration, Users, Recruitment, Admin History, and System Health. Shared topbar titles remain; Users/New user and Recruitment/team count actions are preserved. Local browser verification passed at 1280x720 and 100% zoom. No migration is required; upload the seven changed PHP pages to cPanel.

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

## Bulk Actions Toolbar Update (2026-09-03)

- `index.php` now places bulk actions in the ticket filter toolbar via a compact kebab/details trigger above the ticket list.
- `assets/css/ui-fixes.css` supplies the compact horizontal form, desktop spacing reservation, and responsive fallback.
- `app/layout.php` cache-busts the shared CSS/JS asset URLs for deployment.
- Browser verification at the local 1237×912 viewport confirmed no filter overlap, valid POST form action `bulk-tickets.php`, CSRF presence, open/close behavior, and no console errors.
- No database migration/update is required. Current task change was not committed or pushed.

Files for this task's cPanel upload: `index.php`, `assets/css/ui-fixes.css`, and `app/layout.php`.

Last updated: 2026-09-03

## Ticket ID Table Layout (2026-09-03)

- `assets/css/ui-fixes.css` keeps `.ticket-id` as a normal table cell so the 7% ID-column width applies; checkbox and ID link are inline-aligned.
- Ticket IDs remain visible and clickable, with no database or URL changes.
- `app/layout.php` uses a new asset version so the corrected stylesheet is fetched after cPanel upload.
- No database update is required. Upload `assets/css/ui-fixes.css` and `app/layout.php` for this follow-up.

Last updated: 2026-09-03

## Readable Ticket IDs in Bulk Mode (2026-09-03)

- `assets/css/ui-fixes.css` reserves 7% for the ticket ID column and 12% for the title column, leaving room for the optional checkbox and visible numeric ID.
- Ticket ID links keep their existing display labels and hrefs.
- No database update is required. Upload `assets/css/ui-fixes.css` and `app/layout.php` for this follow-up.

Last updated: 2026-09-03

## Ticket Number Visibility (2026-09-03)

- `assets/css/ui-fixes.css` gives `.tickets-screen .ticket-id a` an 18px minimum width so visible labels such as `#13` cannot collapse inside the flex ID cell.
- Ticket numbers remain display-only labels; existing hrefs and ticket lookups are unchanged.
- No database update is required. Upload `assets/css/ui-fixes.css` and `app/layout.php` for this follow-up.

Last updated: 2026-09-03

## Bulk Selection Mode (2026-09-03)

- `index.php` keeps ticket number links visible and leaves row-selection checkboxes in the DOM for bulk mode.
- `assets/js/app.js` toggles `bulk-selection-mode` on the body from the details menu state.
- `assets/css/ui-fixes.css` hides row checkboxes by default and shows them only when bulk mode is active.
- No database update is required. Upload `index.php`, `assets/js/app.js`, `assets/css/ui-fixes.css`, and `app/layout.php`.

Last updated: 2026-09-03

## cPanel Upload Package (2026-09-03)

- Prepared outside the web root: `C:\xampp\htdocs\cng-ticketing-cpanel-upload-2026-09-03`.
- `cng-ticketing-application-update-2026-09-03.zip` contains the 29 application files needed for the current update.
- `cng-ticketing-migrations-2026-09-03.zip` contains migrations 023 and 024 separately for conditional phpMyAdmin execution.
- The package does not include server-local config, credentials, database/schema.sql, internal notes, tests, reports, or generated dependencies.

Last updated: 2026-09-03

## Login Card Cleanup (2026-09-03)

- `login.php` now keeps the existing two-column auth layout and removes only the right-card eyebrow, portal subtitle, and role footnote requested by the user.
- `assets/css/app.css` now gives `.auth-strata-logo` an explicit 122px width and 28px maximum height; the existing Jamesons logo remains at its current size.
- `login.php` cache-busts the auth stylesheet as `20260903-login-cleanup`.
- Local browser verification passed at 1280x720/100%: Strata Staff 122x28px, Jamesons 145x27px, both card actions and fields present, no login-card target text present.
- `tests/regression.php` now reports 160 passed checks. No database change is required.

Pending product decisions: Team Attendance should be clarified before implementation—whether TL/admins record daily attendance or staff self-check in, whether approved leave should auto-populate the picker or allow manual unplanned leave, and whether the page needs history/export or only daily entry.

Last updated: 2026-09-03

## Ticket Detail Return Action (2026-09-03)

- `ticket.php` now includes a small inline vector arrow in both the native and external `Back to tickets` links; URLs, ticket lookup, POST handlers, comments, and external thread rendering are unchanged.
- Local browser test: native `ticket.php?id=593` shows the return link and native Comments section. The same external URL used by live returns `Ticket not found` locally because all four server-local external connections fail before lookup (1044/1045).
- Live browser test: `ticket.php?external=stratast_escalations:514` shows the return link and conversation messages.
- No database or migration change is required. Regression suite target is now 161 checks after adding the back-icon assertion.

Remaining local parity blocker: install/configure the four external database accounts so the local server can reach the read-only source databases; this requires environment credentials/access, not a PHP UI change.

Last updated: 2026-09-03

## Post-upload UI verification and follow-up fixes (2026-09-03)

- The latest live browser check found that the uploaded baseline is present but not fully synchronized with local: live serves the prior `20260903-dashboard-departments` asset version and its Recruitment sidebar link returns 404 because `recruitment.php` is absent from the live web root. The live health page also still serves the earlier reduced check list.
- Local follow-up changes now make 7D/30D daily and visibly populated, improve the attendance staff picker, convert the Users setup to responsive cards, convert Recruitment to a two-column name-first editor, align Calendar Administration controls, fix health log column widths, and move Settings/Sign out into the clickable upper-right account menu while removing the sidebar footer and caret.
- Local browser smoke testing covered every local sidebar route with no 404, blank page, or PHP error. Live smoke testing covered every existing live route with the single Recruitment 404 noted above. Local light/dark checks passed at normal 100% zoom; live follow-up visual verification is pending the user uploading the new commit.
- `migrations/024_recruitment_directory.sql` remains a database dependency for Recruitment fields; the UI follow-up itself adds no migration.

Last updated: 2026-09-03

## Dashboard and Department External Ticket Fixes (2026-09-03)

- Live browser verification found 580 tickets in the register but 523 in the dashboard; 57 external records use source-specific statuses outside the native four-status set.
- `dashboard.php` now counts those records under `Other`, keeps the total complete, includes mapped departments in recent tickets, and preserves the existing 7D/30D controls. Browser checks confirmed 7D and 30D already returned distinct correct subsets on both local and live before this patch.
- `app/external_tickets.php` maps `stratast_support` to IT Department, `stratast_escalations` to HR Department, `stratast_requisition` to Finance Department, and `stratast_wp346` to L&D.
- `department-workload.php` now merges external tickets into department summaries and the ticket list; external rows link to namespaced read-only detail URLs, while native department logging remains editable as before.
- `ticket.php` now shows a Back to tickets button for native and external detail pages and displays the mapped external department.
- Local database currently has 581 native active tickets; the external source accounts on this PC still deny access, so local browser verification of live external rows remains deployment-dependent.
- No database migration is required for this fix. Upload the changed application files after backing up the live site; do not upload `_brain` files.

Last updated: 2026-09-03

## Login Intro Panel Layout Fix (2026-09-03)

- Local login now disables inherited auth-body centering and explicitly stretches the shell, giving `login.php` a full-height, flush-left blue intro panel instead of an inset centered box; the right-side sign-in card remains intact.
- The change is limited to login markup/CSS and does not require a database migration or cPanel/GitHub update.
- Browser verification passed locally at 1280x720 and 100% zoom.

Last updated: 2026-09-03

## Recruitment Directory (2026-09-03)

- Local recruitment.php now lists all 73 active staff-directory rows and provides inline editing for hired_date, shift_schedule, is_in_training, and position_title.
- Migration 024_recruitment_directory.sql adds those fields and the manage_recruitment permission; migration 025 also grants it to CNG Admin.
- Team Member and CNG Admin are explicitly blocked server-side, including if a permission override is attempted.
- Local migration 024 is applied. Local verification reports 73 staff rows, the expected five granted roles, PHP lint clean, and 129 regression checks passing.
- cPanel must run migration 024 before uploading the new page and related files.

Last updated: 2026-09-03

## UI consistency and dark mode status (2026-08-31)

- Local authenticated pages now follow the Dashboard/Tickets spacing, control sizing, card, table, and typography patterns. Dashboard and Tickets light mode were retained as the reference.
- Dark mode now themes the full shared shell and all audited components, including sidebar, cards, tables, calendars, custom selects, notification surfaces, ticket forms/detail, and account screens.
- Local browser verification was completed at 1280x720, device pixel ratio 1 (100% zoom), in both light and dark mode.
- The live site was independently audited before and after upload. After upload, it serves the current cache-busted baseline and the prior giant unsized SVG/missing-component-layout issues are gone.
- Required production upload for this pass: `app/layout.php`, `assets/css/app.css`, and `assets/css/ui-fixes.css`. No database migration is required.
- Automated verification: PHP lint passed and `tests/regression.php` reports 122 passed, 0 failed.

Last updated: 2026-08-31

## Local, GitHub, and Live Sync Check (2026-09-01)

- After `git fetch origin --prune`, the synchronized application commit is `01cdb2d8b24f5bde557f1a53532bc04f3d267245`; local `HEAD`, GitHub `origin/main`, and `origin/codex/add-presenter-guide` all point to it.
- All selected application changes are committed and pushed. Intentionally excluded untracked diagnostics, reset/seed utilities, generated reports, and local artifacts remain outside GitHub.
- Live currently loads the cache-busted `app.css` and `ui-fixes.css` links and the Tickets page shows the uploaded shortened ticket labels. This confirms the deployed UI surface; cPanel remains a manual deployment target.
- Live `health.php` currently reports `Database Healthy` and `SMTP Configured`, but also reports both private attachment directories as missing/not writable. It does not show the required-table/private-storage checks present in the committed repository `health.php`, so full live code parity is not confirmed.
- The live database connection being healthy does not prove that all repository migrations, including `023_team_member_ticket_access.sql`, have been applied. The user clarified on 2026-09-02 that the current migration/change set has not yet been uploaded/applied in cPanel.

Last updated: 2026-09-01

## Live UI Upload Verification (2026-08-31)

- The user uploaded the UI pass to cPanel. Live now serves the cache-busted stylesheet `app.css?v=20260831-ui-consistency-dark`.
- Browser verification at normal 100% zoom/device pixel ratio 1 passed for 18 authenticated live routes in both themes, including Team Calendar, Team Attendance, Reports, Create Ticket, account screens, and ticket detail.
- All available live sidebar routes loaded successfully. Dashboard's single large SVG is the expected chart; no other page retained the previously observed oversized icon/SVG problem.
- No database migration is required for this UI consistency/dark-mode upload.

Last updated: 2026-08-31

## Production Upload Confirmation and Team Member Account Follow-up (2026-08-31)

- The user confirmed that the latest cPanel upload was applied and that Team Calendar and Team Attendance are now working in production.
- The supplied `CNG_Staff_List_updated.xlsx` contains 70 employee names and team labels plus 3 TL rows; it does not contain individual email addresses. The local `staff_directory` stores only the allowed email domain for those rows, so individual login emails must be entered from the real staff email list rather than inferred.
- The next account-management task is to expose the 70 employee directory rows in the Super Admin Users page, accept exact emails, generate one-time temporary passwords, require a password change at first login, and scope Team Member ticket access to tickets they created.
- Local implementation is complete for this task: `users.php` now provides the directory setup form, temporary credentials are kept in the Super Admin session and displayed once, Settings links to password change, migration `023_team_member_ticket_access.sql` grants Team Member ticket/create access, and Team Member native/external ticket visibility is restricted to tickets they created.
- Migration `023` was applied to the local database. Local browser verification showed 70 directory rows, 68 editable rows because 2 exact-name accounts already exist, and a working Settings password form. PHP lint passed and `tests/regression.php` passed 116 checks with 0 failures.
- The user clarified on 2026-09-02 that migration `023` and the current synchronized change set have not yet been uploaded/applied in the cPanel production database. Earlier production-upload notes are historical and superseded for this deployment set.

Last updated: 2026-08-31

## Account Menu and Theme Settings (2026-08-31)

- Settings is now accessed from the authenticated user's upper-right account button; it is no longer rendered as a sidebar item. The existing sidebar account/sign-out footer remains available.
- `change-password.php` now uses the shared Settings page and includes browser-local Light/Dark theme controls alongside the password-change form. Theme selection is persisted in local browser storage; no database migration is required.
- The sidebar navigation keeps internal scrolling available but hides the scrollbar at normal desktop widths, so the footer is not pushed out of view by the visible scrollbar.
- The account menu supports keyboard focus, Escape-to-close, and outside-click closing. Local verification passed at 1280x720/device scale 1 (100% zoom): XAMPP PHP lint, JavaScript syntax check, browser interaction checks, and the regression harness (122 checks, 0 failures).

Last updated: 2026-08-31

## UI Login Repair (2026-08-29)

- The refreshed local `login.php` had a PHP parse error caused by a literal merge artifact in the file.
- Rebuilt `login.php` with the refreshed UI markup while preserving the backup authentication behavior: username/email lookup, CSRF, approval-state checks, active-account checks, session regeneration, and password-change redirect.
- The separate James-push UI repository has clean syntax but does not contain the external-ticket integration file; it is not safe to deploy as a whole over the functional CNG Ticketing System.
- Local PHP syntax and regression testing must be rerun before CPanel upload.

Last updated: 2026-08-29

## Local UI Sync (2026-08-29)

- Copied the UI files from `origin/main` commit `ee1eae9` into the local working tree.
- Verified all tracked UI files match GitHub; `assets/js/app.js` also matches the remote blob exactly.
- No external-ticket integration, database, SQL, API, upload, or server-local configuration files were changed.
- Local working files are ready for the user to upload to the matching CPanel paths.

Last updated: 2026-08-29

## Frontend UI Commit Review (2026-08-29)

- GitHub `origin/main` advanced to `ee1eae9` (`feat: refresh ticketing frontend UI`) after local branch `codex/add-presenter-guide` at `2ef7254`.
- The commit changes 30 files: 14 PHP pages, shared layout, CSS, JavaScript, and preview HTML files.
- It is primarily a presentation refresh, but it is not strictly UI-only: PHP templates add/change form controls, role-management controls, department selectors, attendance edit presentation, calendar controls, and ticket row interactions.
- `team-attendance.php` includes server-side attendance edit/update code in the remote diff and therefore requires functional regression testing before deployment.
- No files were changed locally during this review. Existing working-tree changes remain untouched.
- Required deployment scope for the UI refresh is the exact file list from `ee1eae9`; do not upload preview HTML files unless they are intentionally used as standalone design previews.

Last updated: 2026-08-29

## External Source Display Labels (2026-08-29)

- External source display labels are now `Escalations`, `IT Department`, `Learning`, and `Requisition`; their existing URLs, credentials, filters, and table mappings are unchanged.
- Local ignored `config/config.external.php` and the credential-free example were synchronized with the new labels.
- Created temporary `update-external-source-labels.php` for cPanel: it requires a logged-in Super Admin, CSRF confirmation, validates a temporary config before replacement, and self-deletes after success.
- The temporary updater is intentionally uncommitted. PHP lint, its built-in self-test, and all 108 regression checks passed.

Last updated: 2026-08-29

## External Source Links (2026-08-29)

- External source badges in the ticket register and external ticket detail now link to their corresponding systems.
- HR Escalation Desk uses `https://escalations.stratastaffglobal.com/`.
- Strata Support Desk uses `http://support.stratastaffglobal.com/`.
- Training Desk uses `https://learning.stratastaffglobal.com/`.
- Requisition Desk uses `https://requisition.stratastaffglobal.com/`.
- URLs are validated to allow only HTTP/HTTPS schemes; credentials remain in the ignored server-local configuration.
- PHP lint and `tests/regression.php` passed with 108 checks and 0 failures.

Last updated: 2026-08-29

## External Conversation Readability Fix (2026-08-29)

- External ticket conversations now pass through `external_thread_body_html()` before rendering.
- Safe formatting such as paragraphs, bold text, lists, blockquotes, code, and approved HTTP/HTTPS/mail links is preserved; scripts, styles, unsafe elements, event attributes, and unsafe URL schemes are removed.
- The external detail view uses a dedicated `thread-body` class with spacing and wrapping rules for readable ticket content.
- Regression and safety checks pass: `tests/regression.php` reports 103 passed and 0 failed; changed PHP files pass syntax lint.

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

## Ticket Detail SLA Spacing (2026-09-03)

- `assets/css/app.css` now gives `.sla-summary` a 12px bottom margin before `.page-actions`.
- `app/layout.php` uses the `20260903-ticket-sla` asset version for cache refresh.
- Local browser verification at 1237×912 confirmed the measured 12px gap and no browser errors.
- No database update is required. Upload `assets/css/app.css` and `app/layout.php` for this fix.

Last updated: 2026-09-03

## Usable Bulk Ticket Selection (2026-09-03)

- `index.php` shows checkboxes for selectable native tickets and adds Select visible/Clear controls to the existing bulk menu.
- `assets/js/app.js` keeps the selection count current and synchronizes selected row IDs into the existing `ticket_ids[]` form inputs.
- `assets/css/ui-fixes.css` styles the selection controls and checkbox alignment; `app/layout.php` uses the `20260903-bulk-selection` cache version.
- External tickets remain read-only and are not selectable for native bulk updates.
- No database update is required. Upload `index.php`, `assets/js/app.js`, `assets/css/ui-fixes.css`, and `app/layout.php` for this update.

Last updated: 2026-09-03

## In-place Mine Filter and Calendar Decision (2026-09-03)

- `index.php` uses `scope=mine` to filter the current Tickets page; the Mine control no longer redirects to `my-work.php`.
- `app/tickets.php` applies assigned-ticket filtering for Mine while preserving Team Member created-by scope.
- `app/external_tickets.php` and `export-tickets.php` preserve the Mine filter for external rows and exports.
- `app/layout.php` no longer renders My Work in the sidebar, but `my-work.php` remains available for compatibility.
- Calendar Administration remains in the Super Admin navigation because it uniquely edits and deletes entries; Team Calendar provides creation only.
- No database update is required for this change.

Last updated: 2026-09-03

## cPanel Upload Confirmation (2026-09-04)

- User confirmed the latest application upload is complete in cPanel and the live site is working.
- Live verification covered authentication, sidebar/direct routes, ticket filters and IDs, departments, external ticket conversations, settings, calendar, attendance, recruitment, reports, and health.
- Live shared assets matched the local versions byte-for-byte; no missing upload was identified.
- The current UI/Mine-filter change set does not require a database update.

Last updated: 2026-09-04

## Team Attendance browser verification (2026-09-14)

- Created a temporary local-only Super Admin test account and authenticated at `http://127.0.0.1/cng-ticketing/team-attendance.php`.
- Browser verification passed for staff search, preserving selected staff while searching, date navigation, saving attendance, success feedback, summary counts, daily table, and recent attendance logs.
- Temporary attendance data and test account were deleted and verified absent. No live data was changed.
- Duplicate-save interaction timed out in the browser harness and remains unconfirmed; PHP lint and the 209-check regression suite still pass.

Last updated: 2026-09-14

## Latest Usability Fixes (2026-09-04)

The local XAMPP MariaDB process was restarted after a refused connection blocked browser testing; no application data or schema was changed. Bulk selection now keeps native ticket IDs visible with an inline checkbox, while external read-only rows receive a disabled indicator and an explanation. Attendance Edit controls are now usable buttons and the staff-picker count updates while editing. Users staff cards now keep all fields full width, and Recruitment cards behave as a single-open accordion. The shared asset version was advanced to force production cache refresh. Verified locally with browser interactions, light/dark checks, PHP/JavaScript lint, 174 regression checks, and no migration is required. Upload app/layout.php, assets/css/ui-fixes.css, and assets/js/app.js to cPanel.

## Ticket status and priority overflow (2026-09-04)

Live and local inspection showed long Status pills extending into the adjacent Priority cell because the Tickets table uses fixed desktop columns while pill overflow remained visible. `assets/css/ui-fixes.css` now clips and ellipsizes Status and Priority pills within their own cells. `app/layout.php` uses the `20260904-status-pill-overflow` asset version. Local normal-zoom browser verification found no visible boundary violations across all visible rows. Live still needs `app/layout.php` and `assets/css/ui-fixes.css` uploaded before production verification. No database change is required.

## Philippine ticket timestamp display (2026-09-04)

Live inspection showed external ticket times eight hours behind the browser's UTC+8 clock. `app/bootstrap.php` now provides `app_datetime_display()`, which converts UTC source strings to `Asia/Manila` for display only. Ticket list, dashboard recent-ticket, ticket detail/conversation/activity/comment/attachment, and notification timestamps use the formatter. No database values, queries, SLA calculations, filters, writes, or schema were changed. The sample live value `2026-09-04 02:26:53` will display as `Sep 4, 2026 · 10:26 AM` after upload. No migration is required; production re-check is pending cPanel upload.

## Create Ticket Team Leader validation fix (2026-09-04)

Live testing with a temporary Team Leader account confirmed the role can log in and open `create-ticket.php`. Selecting Performance rebuilt the native subcategory options (Capacity and Technical), but the visible custom subcategory control remained stale until it was reopened. `create-ticket.php` and `edit-ticket.php` now dispatch the existing custom-control refresh event after dependent options change, refresh the visible department label after category defaults, and preserve submitted form values after validation errors. Regression checks pass 177/177; no database or schema update was made and no live ticket was submitted. Upload only those two PHP files for this fix; the temporary TL test account remains active for follow-up verification.

## Team Leader full ticket visibility (2026-09-04)

Live testing confirmed a Team Leader could open Tickets but saw zero rows because `ticket_scope_sql()` and the external adapter automatically limited Team Leaders to assigned tickets. `app/tickets.php` now returns the full native ticket register for Team Leaders unless Mine is selected, and only Team Members retain mandatory created-by detail restrictions. `app/external_tickets.php` now exposes all external tickets to Team Leaders while preserving Mine filtering and Team Member exclusion. Recruitment was separately verified live as populated and accessible for the Team Leader, so it required no change. The temporary live TL tester was deactivated after verification and no live ticket was created or modified. Regression checks pass 182/182. No migration is required; upload `app/tickets.php` and `app/external_tickets.php`, then re-test live.

## Full live role audit (2026-09-04)

Eight roles were audited against 19 routes using real live sessions. Seven temporary public-role accounts were registered, approved by Super Admin, tested, and then deactivated. No production business record was modified. The audit found no fatal PHP/SQL errors or page-level overflow at normal zoom. It confirmed the pending Team Leader ticket visibility fix and found two additional inconsistencies: Team Members were blocked from the intended read-only Team Calendar, and Reports was directly authorized for non-Team-Member roles but absent from their sidebar while exposing controls some roles could not use. Local fixes now provide Team Members a read-only calendar with date-to-leave-request links, keep calendar writes denied, add Reports navigation to already-authorized roles, and capability-gate CSV export/import controls. PHP lint and 191 regression checks pass. No migration is required. Live verification of these fixes must occur after cPanel upload.

## Production accepted after final upload (2026-09-04)

The final upload is confirmed working on production. Eight roles passed login/logout and 19-route checks with no fatal PHP/SQL errors or horizontal overflow. Live interaction checks passed for bulk selection and visible IDs, attendance editing, single-open Recruitment cards, Team Leader full-ticket and create-form behavior, Team Member read-only calendar/date-prefilled leave requests, permission-aware Reports controls, and light/dark themes. All seven temporary QA accounts are inactive again. No further file upload or database update is pending from this acceptance pass.

## CNG Admin Recruitment and Ticket Export Fix (2026-09-07)

- `recruitment.php` no longer hard-blocks CNG Admin; the `manage_recruitment` permission is authoritative.
- `reports.php` now shows permission-gated report CSV, ticket CSV, and ticket Excel links using the selected reporting date range, department, and one-or-more-employee filters; the Tickets page no longer renders export controls.
- `migrations/025_cng_admin_recruitment_export_permissions.sql` creates/grants `manage_recruitment` and `export_tickets` to `cng-admin` for existing deployments; `database/schema.sql` matches for fresh installs.
- Local browser verification passed for Recruitment rendering, both export links, and a CSV download. PHP lint and `tests/regression.php` pass 199/199, including Super Admin On/Off toggle coverage.
- Live verification confirmed the existing export endpoint starts a CSV download and the live page currently has no export links until this upload is applied. CNG Admin credentials were not used in the live check.
- Production handoff: upload `recruitment.php`, `index.php`, and `migrations/025_cng_admin_recruitment_export_permissions.sql`; run migration 025 in phpMyAdmin, then log out/in as CNG Admin and verify Recruitment plus CSV/Excel exports.

Last updated: 2026-09-07

## Live permission toggle verification (2026-09-08)

- Verified on `https://cng-tickets.stratastaff.com/` that one permission can be toggled Off and back On for all eight roles, with the saved state returning correctly after reload: Super Admin, Management, Team Leader, Pod Leader, Subject Matter Expert, Department Head, Team Member, and CNG Admin.
- The final live 8-role/15-permission effective matrix matched the pre-test matrix with zero mismatches; no account, ticket, leave, recruitment, or other business record was created or changed.
- Super Admin `Manage roles and access` was never disabled because doing so would lock the active verification session out of the access panel. The exhaustive every-cell live toggle pass was not completed because the browser confirmation interrupted repeated submissions; local `tests/regression.php` passes 206/206, including On/Off persistence coverage.
- Current live configuration has Super Admin `Access leave request module` Off, so `leave-requests.php` returns 403 for Super Admin. This is intentional: Super Admin is monitoring only and does not need Leave Requests access.
- The user created `storage/private/ticket-attachments` and `storage/private/leave-attachments` with 0755 permissions. Live System Health now reports both `Writable`, `.local/sessions` `Writable`, and private storage web protection `Configured`; no application file upload is required for this issue.

Last updated: 2026-09-08

## Reports date picker and ticket-list readability (2026-09-11)

- Reports date picker now opens 12px to the right of its trigger on desktop and remains contained on mobile.
- Ticket list rows use slightly taller, uniform vertical spacing; Title can show up to two lines, and Category/SLA cells wrap cleanly while staying vertically aligned.
- Local verification at `http://127.0.0.1/cng-ticketing/` passed: selecting 2026-09-05 through 2026-09-09 preserved those dates in the Reports URL and ticket CSV export link; the CSV download triggered successfully. After applying migration 025 locally, CNG Admin also sees both export links and successfully triggers the CSV download.
- Local `test.superadmin` was reset to the documented local-test password because its database hash was stale. The password remains only in `.local/hermes_test_credentials.md` and was not added to project notes or production.
- PHP lint passed for `app/layout.php`, `reports.php`, and `export-tickets.php`; regression suite passes 206/206.
- cPanel upload is still pending for this task. Upload `assets/css/app.css` and `app/layout.php`; ticket export permission availability on live still requires migration 025 to be applied where it has not already been deployed.

Last updated: 2026-09-11

## Live Reports employee list and export verification (2026-09-11)

- Authenticated live testing confirmed Super Admin ticket CSV export works and includes the selected date range.
- Live Reports previously showed only Jim Lansangan because the employee selector read only native tickets; the live Tickets register also contains external requesters.
- Reports now merges native and external ticket employee names before rendering the employee filter. Upload reports.php; also upload assets/js/app.js and app/layout.php so the All employees interaction and cache version are live.

Last updated: 2026-09-11

## Reports all-employees filter (2026-09-11)

- Reports now shows an All employees option selected by default when no employee filter is active.
- Selecting an individual employee clears All employees; selecting All employees clears individual selections.
- Local browser verification passed for both states and confirmed the export URL omits employee filtering for the all-employees state.
- PHP and JavaScript syntax checks pass; regression suite now includes the new filter behavior checks.

Last updated: 2026-09-11

## Team Attendance usability (2026-09-14)

- Expanded the attendance form and staff list, added name/team search, selected-name feedback, Clear selection, native date inputs, and Previous/Today/Next navigation.
- Corrected the annual-leave-based Present teams metric to Staff on leave and clarified Leave type labeling.
- Failed submissions preserve date, department, leave type, headcount, notes, staff selections, and edit identity. Duplicate department/date creates are rejected instead of silently replacing records; use Edit.
- Changed application files: team-attendance.php, assets/js/app.js, assets/css/ui-fixes.css, app/layout.php. No migration required.
- Verification: PHP lint and JavaScript syntax pass; existing regression suite 209/209; isolated execution checks pass for staff search/selection recovery and PHP failed-create/edit form recovery; local login HTTP 200.
- Authenticated browser save/edit, database duplicate behavior, visual/mobile/keyboard checks and Team Calendar integration remain for the user's requested Codex login test. See output/team-attendance-test-handoff.md.

## Recruitment neighboring card height (2026-09-14)
- Added align-items:start to the Recruitment card grid so a closed card does not stretch to match an expanded sibling. Existing single-open JavaScript remains unchanged.
- Updated the ui-fixes.css cache version in app/layout.php.
- Upload assets/css/ui-fixes.css and app/layout.php. No database changes. Authenticated visual confirmation remains pending.
## Reports and CSV import usability (2026-09-14)
- Replaced the enhanced employee multi-select with searchable checkboxes backed by the same employees[] select; kept All employees and filter/export parameter behavior.
- Added report coverage guidance, leave-filter scope text, empty-result messages, CSV templates, format instructions, sample-row preview, selected import type, discard, and explicit import feedback.
- Added app/report_csv.php for bounded validation. Reject unsupported ticket imports, blank/header-only files, malformed rows, over-1000-row files and invalid dates. Support UTF-8 BOM. All validation errors must be resolved before import.
- Failed uploads clear previous previews; a per-preview token rejects stale submissions. Successful imports clear the preview and redirect with a success notice; database failures roll back.
- The shared submit handler now skips confirmation and generic success flags for CSV validation/discard, while confirming actual imports. Other forms retain existing behavior.
- SHA-256 comparison confirms date markup, date JavaScript, report queries, Excel export link, and export-tickets.php unchanged.
- PHP/JS syntax passes; existing regression suite 209/209 and new CSV behavior suite 21/21 pass (import database tests use a test double).
- Browser checks of rendered PHP fixtures passed employee search/selection/All employees, form values, preview, empty states, light/dark styling, 390px layout and confirmation behavior with no script exceptions.
- Real local test.superadmin login rejected documented credentials. No account reset or real import performed. Authenticated server verification remains pending.
- Upload reports.php, app/report_csv.php (new), assets/js/app.js, assets/css/ui-fixes.css, app/layout.php. No migration. See output/reports-import-test-handoff.md.
## Live Reports toolbar alignment (2026-09-14)
- Authenticated live testing reproduced misplaced toolbar controls, not a server error. Employee filter submission, Excel download (HTTP 200 and XLSX ZIP signature) and department template download passed.
- Separated filter and download rows, added a responsive filter grid, and made employee options a positioned dropdown with outside-click closing. Empty states are centered.
- Previewed local CSS/JS on the real authenticated live DOM: opening Employees leaves the metrics position unchanged; 390px viewport has no page overflow; no script exceptions.
- Upload assets/css/ui-fixes.css, assets/js/app.js and app/layout.php. Date-picker behavior and Excel export implementation unchanged. No production import or business record mutation performed.
## Reports control consistency (2026-09-14)
- Employees now uses the shared ui-select-trigger style and right-side SVG chevron while preserving search, checkboxes, and employees[] submission.
- Added Select Date Range above the unchanged calendar control and updated its accessible label.
- Kept useful summaries, workload/leave tables and import preview; collapsed the detailed CSV format guide by default.
- Upload reports.php, assets/js/app.js, assets/css/ui-fixes.css and app/layout.php. No database changes.
- PHP/JS syntax, 209 regression checks and 21 CSV behavior checks pass.
## Export-first Reports simplification (2026-09-14)
- Unified date/department/employee label and trigger layout; browser measurements confirm identical top coordinates and 44px trigger heights.
- Main card now focuses on filtering and ticket Excel/CSV downloads. Report summary CSV, metrics and workload/leave tables live in a collapsed summary section.
- CSV import is a separate collapsed section, automatically open when preview/error/success feedback exists. Backend import/export logic remains unchanged.
- Desktop and 390px fixture browser checks pass; both optional sections collapse, import remains accessible and there are no script errors.
- Upload reports.php, assets/css/ui-fixes.css and app/layout.php. No migration.
## Connected workload reports and simplified metrics (2026-09-14)
- Reports now merges native workload aggregates with authorized normalized external tickets using the export external filter function, including date, department mapping and employees.
- Removed Created/Closed/Overdue/Closure Rate cards and their unused KPI queries/CSV metric block. Workload summary CSV includes connected sources.
- Missing external sources produce an incomplete-data notice in the screen and summary CSV.
- Renamed Leave activity to Leave request activity and clarified it uses submission date, excludes attendance logs and does not use employee/department filters. Calendar displays approved leave and separately logged attendance.
- Clarified CSV import as optional bulk entry for users/departments/holidays, unrelated to ticket downloads.
- PHP lint, 209 regressions, 21 CSV checks and 7 workload checks pass. Live post-upload verification remains pending.
- Latest application change: reports.php. Pending earlier UI files may also need upload: assets/css/ui-fixes.css, assets/js/app.js, app/layout.php. Ticket Excel endpoint and date picker behavior unchanged.
## Live upload mismatch identified (2026-09-14)
- Authenticated live Reports response contains export-first markup, connected workload, and no metric cards. Live app.js matches local exactly.
- Cache-bypassed live ui-fixes.css is an earlier copy (51,657 characters vs local 53,539), ending before export-first alignment styles.
- Bumped only CSS version in app/layout.php to 20260914-export-first-r2 and packaged exact current CSS/layout at output/reports-layout-upload-20260914-r2.zip with matching relative paths.
- Re-upload those two files; application/date/export logic unchanged.
## Create-ticket attachment (2026-09-15)
- Added an optional PDF/JPG/PNG attachment to create-ticket.php for roles with upload_attachments; existing view/download permissions apply.
- New app/ticket_attachment.php validates upload status, permission, actual size and matching extension/MIME; saves metadata inside the ticket transaction and tracks files for rollback cleanup.
- Upload create-ticket.php and app/ticket_attachment.php. Existing attachment table/storage are reused; no migration.
- PHP lint and 209 regression checks pass; tests/ticket_creation_attachment.php passes 13 validation checks. Authenticated browser upload/download and database rollback verification remain untested.

## Ticket requestor correction (2026-09-15)
- Native Requestor now comes from users.full_name joined through tickets.created_by in the register, details and CSV/Excel exports; search includes creator name. Employee remains a separate affected-employee field.
- External tickets retain their source requester. No migration or data rewrite.
- Upload index.php, ticket.php, export-tickets.php and app/tickets.php together, plus the earlier attachment files if deploying both changes.
- Four PHP lint checks, 209 regression checks and 7 requestor behavior checks pass. Authenticated browser/database verification remains pending.

## Sheena CNG ticket cutoff (2026-10-07)

`app/tl-filter.php` now excludes external tickets requested by Sheena Magdaraog after her last CNG Team Leader day, 2026-10-05. Upload that single file to cPanel to activate the cutoff; no database update is required. PHP syntax lint passes locally.


## Department workload alignment and live account audit (2026-10-07)
- Used the live site as the layout and permission reference; added scoped department CSS to assets/css/ui-fixes.css and bumped its local cache version in app/layout.php.
- Nine cards use three desktop columns, two tablet columns and one mobile column, wrapped department names and aligned involved/current/overdue columns.
- Injected the CSS into the authenticated live browser without publishing. All 16 light/dark checks at 320, 390, 600, 768, 1024, 1280, 1440 and 1920px passed: nine cards, equal heights, no clipped card content or page overflow.
- Read-only audit: CNG Admin and POD Leader have create_tickets disabled; POD Leader also lacks can_export_tickets. Super Admin can access all audited modules. Write actions were not submitted; no tickets created, edited or deleted. Login updates last_login_at.
- Live-based CSS upload package and report: C:/Users/Admin/cng-live-review/. Production remains unchanged. Package contains live ui-fixes.css plus the scoped override; do not upload the entire local layout or unrelated pending changes.

## Live workflow acceptance and launch fixes (2026-10-07)
- User clarified live writes are authorized on clearly labelled QA records, with removal afterward; existing TL records must remain untouched.
- Created/soft-deleted native QA #6/#7/#8; own users #45/#46/#47, attendance #3, events #2/#3 and QA holidays removed through normal app actions. Audit/soft-delete traces and private attachment retention preserved.
- Confirmed live failures: valid full edit HTTP 400; restore HTTP 500; native department involvement rows missing. Fixed locally in edit-ticket.php, restore-ticket.php and department-workload.php; live deployment pending.
- Actual isolated database behavior: 11/11; regression: 209/209; PHP lint passes. Live writes also verified assignment, comments, state changes, bulk, attachment restrictions, notifications, export filtering, CSV import, calendar, attendance, account lifecycle and settings.
- Hold general-use announcement until the three fixes are uploaded and live edit/routing/delete/restore/delete recheck passes. Report and four-file upload ZIP: C:/Users/Admin/cng-workflow-qa/WORKFLOW-SUMMARY.md and launch-fixes-upload-20261007.zip.

## Live post-upload acceptance passed (2026-10-07)
- User confirmed cPanel upload of edit-ticket.php, restore-ticket.php, department-workload.php and assets/css/ui-fixes.css.
- Authenticated live test: Super Admin/POD full edits persist; own native #9 appears and routes through Departments UI; delete/restore preserves edits/comment/status/attachment. All three prior blockers resolved live.
- 85 functional checks, 39 route checks and 16 individual responsive light/dark cases pass; no browser exceptions. Existing role restrictions remain intact. CSS matches package after newline normalization.
- Own QA #9 deleted again; all three accounts have no active marker row and direct ticket access 404; attachment access 404. Recovery/audit traces retained by normal soft-delete policy. Existing TL data/accounts untouched.
- General use of tested ticketing workflows can be announced. Report: C:/Users/Admin/cng-postupload-qa/POST-UPLOAD-ACCEPTANCE.md. Announcement draft: GENERAL-USE-EMAIL.txt in the same directory. Leave approvals, valid Recruitment writes, email inbox delivery, backup recovery and load remain outside tested scope.

## Registration validation feedback (2026-10-07)
- michael@stratastaff.com is valid and not registered on live; exact submitted username was not provided, so the specific rejection remains unconfirmed.
- Live valid registration/pending-login/duplicate behavior passed; own QA #48 deleted. Browser accepted invalid username spaces/@ because the unescaped hyphen pattern was ignored; fixed escaping and explicit server feedback.
- register.php now preserves non-password inputs and shows specific field/duplicate errors, with safe insert failure handling. Existing local three-domain policy retained; production one-file package preserves the live @stratastaff.com-only policy.
- Isolated DB checks 29/29; browser12/12; regressions209/209; PHP lint passes. Upload pending: C:/Users/Admin/cng-register-qa/register.php or registration-fix-upload-20261007.zip.

## Pending activation restricted to Super Admin (2026-10-07)
- Management Users list now excludes pending/rejected registrations and hides Pending activation; approved accounts remain accessible under existing permissions.
- users.php and admin.php enforce Super Admin approval access and deny mutation of unapproved users by other roles; reset-user-password.php denies direct unapproved-user access. Covers save-with-active bypass and overridden manage_roles permission.
- Validation: 17/17 isolated actual PHP/database behavior checks, 209/209 regressions and all three PHP lints passed. Scratch database removed; existing live data untouched.
- Deployment pending: C:/Users/Admin/cng-pending-access/pending-activation-super-admin-upload-20261007.zip contains users.php, admin.php and reset-user-password.php only. Replace in live document root; no database changes. Separate registration upload remains separate.

## Live Management/Michael launch recheck passed (2026-10-07)
- User supplied Michael login for ongoing live tests, then confirmed uploading all three pending-activation files. No credentials saved in scripts or reports.
- Live four-account verification: final 114 workflow/module checks plus 27 registration/approval checks all passed; 16 light/dark responsive cases pass; no browser exceptions. Management pending queue/details hidden and direct activate/save/delete/override/password-reset requests denied; Super Admin approval works.
- Michael can edit/comment/change status/export/download permitted QA attachments and access approved users. Ticket creation/deletion/recovery and Super Admin routes remain denied under existing live permissions.
- Own QA tickets #10/#11 soft-deleted and attachment access denied. Own users #50/#51 removed; original records and real-account credentials/permissions untouched. Initial selector/role expectation errors corrected and final suites rerun successfully.
- Tested ticketing workflows ready for announcement. Evidence/report: C:/Users/Admin/cng-final-live-qa/FINAL-LIVE-ACCEPTANCE.md, verified/results.json and pending-results.json. Separate registration feedback upload remains outside this three-file verification; valid registration/approval works live.

## GitHub publication of today's fixes (2026-10-07)
- Published commit 08bb4e0 to iantolentino/cng-ticketing main; 19 files contain today's isolated code/CSS/test updates, registration production variant, sanitized summaries and announcement draft.
- Used C:/Users/Admin/cng-github-20261007 worktree to preserve older unrelated changes in the original local app. Main in the original checkout was not reset/merged over its dirty files; GitHub main is updated.
- Public update excludes credentials, real staff files, database contents, private evidence, screenshots, upload archives and temporary test databases. Root registration preserves repository's existing three domains; deployment/20261007-launch/register.php preserves live-only domain.
- Isolated publication checkout passes 174 regression checks, 10 PHP syntax checks and whitespace validation. Today's broader local acceptance remains 209 regression and 141 final live workflow/access checks plus 16 layout cases.
- GitHub work summary: https://github.com/iantolentino/cng-ticketing/blob/main/reports/2026-10-07-work-summary.md. Registration feedback live upload remains unverified; other tested launch/access changes are live.
