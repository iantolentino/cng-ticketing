# Current State

## System State

EXECUTION_MODE

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
- Migration 024_recruitment_directory.sql adds those fields and the manage_recruitment permission, granted to Super Admin, Management, Team Leader, Department Head, and Pod Leader.
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
