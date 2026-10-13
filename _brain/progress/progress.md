# Progress

## Supporting Page Header Cleanup (2026-09-03)

- Removed the transient external-source warning banners from the Tickets and Departments pages while keeping external loading and failure handling intact.
- Removed redundant main-content page heading blocks from Calendar Administration, Users, Recruitment, Admin History, and System Health; the shared top navigation title remains, and Users/New user plus Recruitment/team count actions remain visible.
- Verified all seven pages in the local browser at 1280x720 and 100% zoom; no database migration or live-site upload was performed.

Last updated: 2026-09-03

## Latest Task — Preserve table sizing for ticket IDs (2026-09-03)

- Restored the ticket ID cell to normal table-cell layout and aligned the optional checkbox and visible ID link inline.
- The existing `#13` label and `ticket.php?id=13` href remain unchanged.
- Verified locally in the browser with Bulk actions open; the checkbox and full ticket number render together.
- No database change was made.

Last updated: 2026-09-03

## cPanel Upload Package Prepared (2026-09-03)

- Prepared `C:\xampp\htdocs\cng-ticketing-cpanel-upload-2026-09-03` outside the web root.
- The application package contains 29 current production PHP/CSS/JS files, preserving their relative paths.
- The separate database package contains migrations `023_team_member_ticket_access.sql` and `024_recruitment_directory.sql`; run them in phpMyAdmin only if those production changes are not already present.
- Excluded local configuration, credentials, tests, `_brain` notes, reports, generated dependencies, `database/schema.sql`, and one-time utilities.

Last updated: 2026-09-03

## Latest Task — Keep ticket IDs readable in bulk mode (2026-09-03)

- Increased the ticket-list ID column to 7% and reduced the title column to 12% so the visible numeric label and optional selection checkbox fit together.
- Kept ticket ID values, display formatting, and detail-page URLs unchanged.
- Verified locally in the browser at normal zoom with Bulk actions open; `#13` remained readable and linked to `ticket.php?id=13`.
- No database change was made.

Last updated: 2026-09-03

## Latest Task — Restore visible ticket numbers (2026-09-03)

- Kept the existing clickable ticket-number labels and URLs unchanged.
- Added a compact minimum width to the ID link so labels such as `#13` cannot collapse when the bulk checkbox is present in the same cell.
- Verified locally at normal zoom with Bulk actions closed and after selection mode is opened.
- No database change was made.

Last updated: 2026-09-03

## Latest Task — Bulk selection mode (2026-09-03)

- Kept displayed ticket numbers visible in the ticket ID column.
- Bulk row selection controls now appear only while the Bulk actions menu is open.
- Existing selected-ID sync, endpoint, CSRF, permission, and native/external behavior were preserved.
- Verified the local browser: ticket number visible before/after opening, checkboxes hidden/visible at the correct states, selection controls still work, and no browser errors were reported.
- No database change was made.

Last updated: 2026-09-03

## Ticket Detail Return Action (2026-09-03)

- Added the existing shared arrow SVG treatment to the native and external `Back to tickets` links in `ticket.php`; both links still target `index.php`.
- Browser verification found the return link on local native ticket `id=593` and live external ticket `stratast_escalations:514`; live rendered the external conversation successfully.
- Local external conversation loading is blocked by server-local database connection failures (support code 1044; escalations, Learning, and Requisition code 1045), not by removed conversation code. No database change was made.

Last updated: 2026-09-03

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

## Latest Task — Bulk actions toolbar placement (2026-09-03)

- Moved the existing bulk-ticket POST form into a compact kebab/details control in the ticket filter toolbar, above the ticket list.
- Preserved the existing endpoint, CSRF protection, ticket IDs, action/status/priority fields, and submit behavior; no database changes were made.
- Added responsive horizontal styling and reserved toolbar space so the date filter and bulk-action trigger do not overlap at the local browser viewport.
- Verified with PHP lint, regression tests, `git diff --check`, and the local browser: menu opens/closes, form is valid, and no browser errors were reported.

Last updated: 2026-09-03

## Login Card Cleanup (2026-09-03)

- Removed the redundant `ACCOUNT ACCESS`, portal subtitle, and role footnote from the login card while preserving the sign-in form, Register action, accessibility labels, and the full-height intro panel.
- Increased the existing Strata Staff logo rendering to 122x28px so its visual height aligns with the existing Jamesons logo without changing either asset.
- Browser verification passed locally at 1280x720, DPR 1 (100% zoom): targeted copy is absent, both logos render, and the form/actions remain available.
- PHP lint and `tests/regression.php` passed with 160 checks; no database or migration change is required.

Last updated: 2026-09-03

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

Added locally on 2026-09-03: recruitment.php displays all active staff-directory rows and allows hire date, shift schedule, training status, and position updates. Migration 024_recruitment_directory.sql adds the fields and manage_recruitment permission. Access is granted to roles with manage_recruitment; migration 025 also grants CNG Admin the Recruitment capability.

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

## Latest Task — Ticket detail SLA spacing (2026-09-03)

- Added a 12px bottom margin to the shared ticket-detail SLA summary so the action buttons have clear separation.
- Updated the shared asset version in `app/layout.php` to ensure the stylesheet refreshes after deployment.
- Verified the local ticket detail page at normal zoom: measured 12px gap, screenshot confirmed the spacing, and no browser errors were reported.
- No database change was made.

Last updated: 2026-09-03

## Latest Task — Usable bulk ticket selection (2026-09-03)

- Added native-ticket row checkboxes and a compact selection count to the existing bulk-actions menu.
- Added Select visible and Clear controls; selected IDs synchronize into the existing `ticket_ids[]` POST fields.
- Kept manual numeric ID entry, the existing `bulk-tickets.php` endpoint, CSRF protection, permissions, and external-ticket read-only behavior unchanged.
- Verified locally at normal zoom: one-row selection, 10-row visible selection, clearing, menu interaction, endpoint/CSRF presence, PHP/JavaScript syntax, 166 regression checks, and no browser errors.
- No database change was made.

Last updated: 2026-09-03

## Latest Task — In-place Mine filter and calendar review (2026-09-03)

- Mine now stays on the Tickets page and applies `scope=mine`; All and Unassigned remain in the same filter control.
- Non-Team-Member users are filtered to tickets assigned directly or through the assignee pivot; Team Members retain their created-by visibility rule.
- External ticket matching and exports preserve the Mine scope where applicable.
- Removed only the My Work sidebar item; `my-work.php` remains available for old bookmarks and direct access.
- Reviewed Calendar Team Calendar versus Calendar Administration: Team Calendar creates entries, while Calendar Administration remains necessary for Super Admin edit/delete controls. No calendar route was removed.
- Local browser navigation and route checks passed; no database change was made.

Last updated: 2026-09-03

## cPanel Upload Confirmation (2026-09-04)

- User confirmed that the current application files were uploaded to cPanel and the live site is working.
- The previous live verification covered login/logout, all 12 sidebar routes, direct application routes, Mine filtering, shortened ticket IDs, departments, ticket detail conversations, settings, calendar, attendance, recruitment, reports, and system health.
- The live shared `app.css`, `ui-fixes.css`, and `app.js` files matched the local working files byte-for-byte during verification.
- No additional database migration is required for the current UI and in-place Mine-filter change.

Last updated: 2026-09-04

## Latest Task — Reports picker and ticket-list detail spacing (2026-09-11)

- Shifted the Reports date-range menu right by 12px on desktop and constrained it to the Reports control on mobile.
- Increased ticket-table row height uniformly and allowed Title, Category, and SLA details to breathe without disrupting the shared table layout.
- Local browser test passed at `http://127.0.0.1/cng-ticketing/`: date selection submitted correctly, export URLs carried the selected range, and the ticket CSV download was triggered. After applying migration 025 locally, CNG Admin also sees both export links and triggers the CSV download.
- No production files were uploaded. cPanel upload set: `assets/css/app.css` and `app/layout.php`.
- `tests/regression.php`: 206 passed, 0 failed.

Last updated: 2026-09-11

## Latest Task — Live Reports export and employee list (2026-09-11)

- Authenticated live verification confirmed Super Admin CSV export triggers successfully with the selected dates.
- Reproduced the incomplete employee list: Reports showed only Jim Lansangan while live Tickets contained external requesters.
- Updated Reports to merge native and external employee names; updated regression coverage and deployment handoff.

Last updated: 2026-09-11

## Latest Task — Reports all-employees filter (2026-09-11)

- Added All employees to the Reports employee multi-select.
- Added exclusive selection behavior for all employees versus individual employees.
- Local browser verification passed for default, individual, and all-employees export states.
- Updated the JavaScript asset cache version and regression checks.

Last updated: 2026-09-11

## CNG Admin Recruitment and Ticket Export Permissions (2026-09-07)

- Removed the hard-coded CNG Admin denial from `recruitment.php`; access now follows `manage_recruitment`.
- Added permission-gated CSV and Excel export links to the Tickets page.
- Added `migrations/025_cng_admin_recruitment_export_permissions.sql` for deployed databases and aligned `database/schema.sql` for fresh installs.
- Local browser verification showed Recruitment and both export links; the export endpoint started a CSV download.
- PHP lint and the regression suite pass 199/199, including Super Admin On/Off toggle coverage. No live production data was changed.

Upload `recruitment.php`, `index.php`, and `migrations/025_cng_admin_recruitment_export_permissions.sql`; run the migration in phpMyAdmin before testing the CNG Admin account.

Last updated: 2026-09-07

## Production post-upload acceptance (2026-09-04)

- Confirmed the final cPanel upload on `https://cng-tickets.stratastaff.com/` using live browser sessions for Super Admin and all seven public roles.
- Re-activated the existing temporary QA accounts, tested them, and deactivated all seven again after completion.
- Completed 152 route checks across eight roles. Every account logged in and out successfully; no PHP fatal error, SQLSTATE error, unexpectedly blank authorized page, or page-level horizontal overflow was found at normal zoom.
- Verified live interactions without submitting production business data: Bulk actions opens with 10 row checkboxes while ticket IDs remain visible; Recruitment keeps one of 73 employee cards open; Attendance exposes working Edit mode; Team Leader ticket creation refreshes Performance subcategories and shows the full ticket list; Team Member calendar is read-only and opens Leave Requests with the selected date; Reports/import/export controls follow permissions; Light/Dark theme switching works without overflow.
- Confirmed Team Leader sees 10 current external ticket rows and Recruitment. Team Member sees Dashboard, Tickets, Team Calendar, and Leave Requests, while external ticket detail, Recruitment, Attendance, Reports, and administration workspaces remain denied.
- Production acceptance passed. No additional application file, database migration, or phpMyAdmin update is required.

Last updated: 2026-09-04

## Full live role audit and final permission alignment (2026-09-04)

- Created and approved one temporary live account for each public role: CNG Admin, Department Head, Management, Pod Leader, Subject Matter Expert, Team Leader, and Team Member. Super Admin was tested with the existing account.
- Tested login/logout, visible navigation, 19 protected routes, ticket list/detail access, creation access, Departments, Team Calendar, Team Attendance, Leave Requests, Recruitment, Notifications, Settings, and administration routes for all eight roles at normal zoom.
- No tested route produced a PHP fatal error, SQLSTATE error, blank accessible page, or page-level horizontal overflow.
- Confirmed and retained the pending Team Leader full-ticket visibility fix. Recruitment is already populated and correctly allowed for Team Leader, Department Head, Pod Leader, Management, and Super Admin while denied to Team Member and CNG Admin.
- Fixed Team Member access to a read-only Team Calendar. Team Member date links now open Leave Requests with that date selected; event/holiday creation remains unavailable and POST updates return 403.
- Added Reports navigation for every non-Team-Member role already authorized to view the page. CSV import and export actions are now rendered and accepted only when the role has the matching capability.
- All seven temporary live QA accounts were deactivated after testing. No production ticket, attendance, leave, recruitment, or employee record was created or changed.
- Verification: PHP lint passed for all eight pending application PHP files; regression suite passes 191/191; whitespace validation passes.
- No database migration or phpMyAdmin update is required. Live post-fix verification remains pending the cPanel file upload.

Last updated: 2026-09-04

## Team Leader ticket and Recruitment visibility (2026-09-04)

- Reproduced on live with the temporary Team Leader tester: Tickets loaded but returned zero rows because Team Leaders were always restricted to their own assignments.
- Updated shared native and external ticket visibility so Team Leaders see the complete register by default, while Mine remains assignment-based and Team Members remain restricted to tickets they created.
- Team Leaders can now open any ticket visible in the full register; the same corrected scope applies to dashboard, departments, ticket exports, and external ticket detail.
- Live Recruitment already displayed the populated employee directory for the tested Team Leader, so no Recruitment code or permission change was needed.
- The temporary live Team Leader tester was deactivated after verification; no live ticket was created or modified.
- Verification passed: PHP lint, whitespace validation, and 182 regression checks.
- No database or migration update is required. Upload app/tickets.php and app/external_tickets.php; live post-upload verification remains pending.

Last updated: 2026-09-04

## Latest Task — Ticket status and priority overflow (2026-09-04)

- Reproduced the overlap in both live and local Tickets pages: long external statuses such as `Approved ( For Release )` and native `In Progress` extended past the fixed Status cell into Priority.
- Added a display-only overflow rule to keep Status and Priority pills inside their table cells, truncating only when the label is wider than the available column.
- Advanced the shared asset version in `app/layout.php` so cPanel browsers load the updated CSS.
- Local browser re-check at normal zoom found no visible Status/Priority boundary violations across all visible ticket rows.
- No database or ticket data change was made. Upload `app/layout.php` and `assets/css/ui-fixes.css`; the previously unuploaded `assets/js/app.js` remains in the full pending upload set.

Live still needs the two updated files uploaded before the production fix can be verified there.

Last updated: 2026-09-04

## Latest Task - Philippine ticket timestamp display (2026-09-04)

- Checked the live Tickets list and external ticket detail in the browser. The browser runs at UTC+8, while live external data included raw values such as `2026-09-04 02:26:53`, confirming an eight-hour display difference.
- Added the shared `app_datetime_display()` formatter in `app/bootstrap.php`, converting external UTC timestamps to `Asia/Manila` only when rendered.
- Applied the formatter to ticket-list Created/Last update values, dashboard recent-ticket times, ticket detail Created/Date closed values, external conversation dates, native comments/activity/attachment dates, and notification preview/list times.
- Stored ticket values, SLA calculations, filters, database queries, writes, and schema were not changed.
- CLI verification renders `2026-09-04 02:26:53` as `Sep 4, 2026 · 10:26 AM`; all changed PHP files pass lint and `git diff --check` passes.
- User confirmed no files created today have been uploaded yet. Live remains pending this upload and a post-upload browser re-check.
- No database migration or phpMyAdmin update is required.

## Latest Create Ticket TL validation fix (2026-09-04)

- Reproduced on live using a temporary Team Leader account: the TL could open Create Ticket, but after selecting Performance the native subcategory options contained Capacity and Technical while the visible custom control still showed "Select category first" until reopened.
- Fixed create-ticket.php and edit-ticket.php so dependent custom controls refresh immediately after category changes, and the department visible label refreshes after automatic defaults.
- Create Ticket now preserves entered values after a validation error instead of clearing the form, making the failure recoverable and diagnosable.
- Regression suite now passes 177 checks; changed create/edit PHP files pass lint and whitespace validation passes.
- No database, schema, ticket, or live production ticket was changed. The temporary live TL test account was created and remains available for follow-up validation.
- New cPanel upload set for this fix: create-ticket.php and edit-ticket.php. No migration or phpMyAdmin update is required.

Last updated: 2026-09-04

## Latest Task — Bulk, attendance, users, and recruitment usability (2026-09-04)

- Restarted the local XAMPP MariaDB process after confirming the application was failing with a refused TCP connection; mysqladmin ping now reports mysqld is alive.
- Kept ticket numbers visible and changed bulk-mode native checkboxes to inline display beside the numeric ID. External rows now show a disabled read-only checkbox indicator and an explanation because the external adapters and bulk endpoint do not support external writes.
- Styled attendance Edit links as compact secondary controls and made the staff-on-leave selection summary update immediately as checkboxes change. Existing attendance save/update backend and tables were preserved.
- Fixed the two-column Users card grid so every card field, including Account, uses the full card width.
- Added single-open accordion behavior to Recruitment cards so editing one employee closes any other expanded card.
- Updated the shared asset query version in app/layout.php to force cPanel browsers to load the new CSS and JavaScript.
- Verification passed: PHP lint for changed PHP files, JavaScript syntax, 174 regression checks, whitespace check, local browser interaction checks at normal zoom, and local light/dark theme checks.
- No database migration or data update was made. cPanel upload set: app/layout.php, assets/css/ui-fixes.css, and assets/js/app.js.

Last updated: 2026-09-04

## Ticket visibility and reserved numbers (2026-09-21)

- Added ticket_visibility.php with 53 configurable native/external IDs and Super Admin exemption; removing an ID restores visibility without deleting records.
- Integrated native scope, external SQL adapters, direct ticket/attachment guards, API feed, report employee filters, My Work counts, notifications and recipient-aware ticket emails.
- Native creation uses an InnoDB allocator row held through explicit-ID insertion; concurrent allocator and empty-table tests pass. Development seed writer updated locally and excluded from deployment.
- Added Super Admin read-only ticket_visibility_check.php with upload hash checks; package: deployment/ticket-visibility-cpanel-20260921.zip.
- Verification: 209 regression checks; isolated DB tests cover filters, restoration, notifications, external adapters and allocation contention; actual native pages verified. External production sources unavailable locally and require cPanel checker validation. No production deployment or ticket data mutation performed.
- V2 correction: first 29 IDs remain fully hidden; second 25 (including added 511) remain listed/counted but deny non-admin detail, comments, attachment and detail-API access. Both sets reserve IDs. Supersedes prior package with deployment/ticket-visibility-cpanel-20260921-v2.zip. Regression 209/209 and visibility DB tests pass.

- V3 correction: moved 511 from LOCKED to HIDDEN: 30 hidden, 24 listed-but-locked. CNG Admin list/count and opening rules verified in tests. Updated helper alone suffices over V2; full deployment/ticket-visibility-cpanel-20260921-v3.zip also supplied.

- V4 requested 15-ticket acceptance scenario passed 45 assertions against actual PHP pages in isolated local app/schema: 5 hidden, 10 listed/countable without links, direct access 404, Super Admin all 15. Disabled navigation in index/dashboard/my-work/departments without CSS/JS changes. Real ID config unchanged; disposable DB removed. Regressions 209/209; visibility/concurrency tests pass. Package deployment/ticket-visibility-cpanel-20260921-v4.zip supersedes V3; upload whole archive. Evidence .local/ticket-visibility-15-results.json.

- Updated the visibility configuration so the 21 requested IDs (`1063, 1061, 1047, 1014, 987, 982, 959, 950, 932, 925, 885, 867, 834, 818, 808, 789, 788, 752, 720, 718, 555, 511`) are fully hidden; `92, 91, 90` remain listed but locked. Updated `ticket_visibility.php` and the latest deployment snapshot, and created `deployment/ticket-visibility-cpanel-20260922-v5.zip`.
- PHP lint, the configuration membership check, and `tests/regression.php` pass (209/209). A read-only CNG Admin live check reached the dashboard and direct access to ticket `1063` returned 404; no live upload was performed.

## Admin guide presentation (2026-09-22)
- Created output/pptx/cng-admin-guide-20260922/CNG-Admin-User-Guide.pptx and matching Reading-Script.txt: 20 slides mapped to the Word guide pages, 19 annotated screenshots, editable instructions and speaker notes. Verified source text matches, rendered all slides in PowerPoint, and checked text overflow (none).


## Sheena CNG external-ticket cutoff (2026-10-07)

- Updated `app/tl-filter.php` to include Sheena Magdaraog's tickets only from 2026-07-11 through 2026-10-05 inclusive; later or undated tickets from her are excluded.
- Upload `app/tl-filter.php` to the matching cPanel path. No database change or account deactivation is required.
- PHP syntax lint passes; production behavior remains pending upload.


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

## Seven-feature roster/leave/calendar release prepared (2026-10-12)
- Built against a local copy of the production database (MariaDB 10.4.32 on 127.0.0.1:3307) served by PHP 8.2.28 on http://127.0.0.1:8080, with PHP and MySQL both pinned to UTC to match live.
- Features: Dashboard date range replaces the preset windows (F074); Tickets requestor filter (F075); Departments category/subcategory/status filters (F076); Team Attendance rebuilt around a per-staff roster with editable shifts and shared leave (F077); Team Calendar date-click card with holiday/event/leave modals and per-entry Edit (F078); Leave Requests file-on-behalf plus department filter, with Department Head approval creating the shared leave entry (F079); employment status (resigned / pulled out / for replacement) and CNG Admin Team Calendar access (F080).
- New files: app/roster.php, migrations/026_roster_leave_and_staff_status.sql. Deployment bundle: deployment/roster-leave-calendar-20261012/ and deployment/roster-leave-calendar-cpanel-20261012.zip (17 entries, forward-slash paths, includes UPLOAD-README.txt with apply, verify and rollback steps).
- Evidence: 232 regression checks pass; every feature exercised over HTTP with real form posts (shift and leave edits, add-edit-delete for holiday/event/leave, department filter, file-on-behalf, Department Head approval materialising the calendar entry, read-only Team Member calendar). Guards confirmed for duplicate leave, reversed dates, invalid type/staff/department, resigned staff, holiday collisions and cross-type deletion.
- Live was probed read-only and matches this checkout's pre-change base for every packaged file (cng-admin nav, legacy calendar/attendance/leave markup, ticket date-range field, absence of the new filters); live assets diff only by these additions.
- All local test data reverted: 0 leave_requests, 0 attachments, 13 backfilled linked leaves, no staff status or department changes, user 21 role restored. Timezone filtering caveat and the pending department/TL staff list recorded in the bundle README.
- Not yet applied to live; awaiting the user's review of the local site.

## External ticket sources mirrored locally and roster imported (2026-10-12)
- Local only showed the 2 native tickets; the missing rows were the four external sources, which the app reads as MySQL databases on the production host's localhost. All 620 tickets (Strata Support 450, HR Escalation 104, Requisition 48, Training 18) plus customers, statuses, priorities, categories and 620 conversation threads were taken from the ticket fetch test program and loaded into four local databases, then config.external.php was repointed at 127.0.0.1:3307 (original kept as config.external.php.bak-live-20261012).
- Local now matches live row for row: Departments 41 rows / 38 external (identical rows including #7706 IT and #1091 Finance), Tickets 10 of 10 rows byte-identical, Dashboard 4 links. External ticket detail pages show their conversations.
- CNG Roster.xlsx applied to staff_directory: 73 -> 102 staff. Hired dates, Mon-Fri shift patterns with start/end times from Day Off + Standard TIME, departments from the workbook Role (Strata Care folded into Strata Customer Care per the user), and 24 exit records (12 resigned, 11 pulled out, 1 for replacement). Team Leader / SME / CC Team Leader rows keep a position title and no department, as chosen; team leader assignment is deferred to a separate pass.
- Reconciliation rule from the user: the workbook is the source of truth. Divina Rabogo renamed to Divina Rabago; Rai Daniel Oliva marked for_replacement.
- deployment/staff-roster-20261012.sql emitted for live: keyed on the unique staff name, departments resolved by code, idempotent. Verified on a fresh import of the production dump by running migration 026 then the script twice - 73 -> 102 staff, still 102 on the second run, and 0 rows differing from the local database.
- F081/F082 logged. The on-behalf leave picker now offers only Active / For replacement staff. 232 regression checks pass; all main pages render without errors.
- Test fixtures are local only: the four external databases and config.external.php must never be deployed, because live already holds the real sources.

## Live-safe additive migration built and proved (2026-10-12)
- Requirement from the user: the cPanel migration must only add what is missing and must never change anything already on live. Translated into three provable guarantees - additive (no row deleted, no stored value replaced), idempotent (a second run is a complete no-op), and safe on a partially-migrated database (every DDL step guarded).
- Deliverable: deployment/migration-live-additive-20261012.sql, one ordered hand-runnable file (732 lines) using guarded DDL through INFORMATION_SCHEMA + SET @sql + PREPARE/EXECUTE/DEALLOCATE because MariaDB 10.4 has no ADD COLUMN IF NOT EXISTS, INSERT IGNORE for the 29 new staff, and NULL-only fills. Contains zero DELETE, TRUNCATE, DROP, REPLACE or ON DUPLICATE KEY UPDATE. Companion deployment/DEPLOY-CHECKLIST-20261012.md explains the backup, the exact run order, the self-check queries and the four optional corrections, in plain language.
- Proof method: two scratch databases both imported from the production dump (cng_scratch_baseline untouched, cng_scratch_verify migrated), per-row MD5 over CONCAT_WS of every dump-era column, compared positionally. Result: pass 1 exit 0, pass 2 exit 0, 0 differing snapshot lines between the two passes, 28 of 29 tables byte-identical, staff_directory kept all 73 original rows, 646 -> 675 rows total, and 0 genuine overwrites.
- Two real defects were found by that proof, not by inspection. staff_directory.updated_at is ON UPDATE CURRENT_TIMESTAMP, so the first fills silently rewrote the timestamp on 71 rows; fixed by pinning sd.updated_at = sd.updated_at in all three fills, which dropped the diff to the 7 intended position_title values. And the pre-existing deployment/staff-roster-20261012.sql ended in ON DUPLICATE KEY UPDATE, which on live's UNIQUE full_name rewrote 72 of 73 existing rows - 29 team_labels ('Strata Care' -> 'Customer Care', 'R&M Lead' -> 'RM Team Leader', 'R&M' -> 'RM'), 68 hired_dates and 8 position_titles - and renamed id 39 'Divina Rabogo' to 'Divina Rabago'. It is now INSERT IGNORE with the rename commented out and Divina removed from the candidate list; re-verified at 0 rows touched and +29 staff. The copy inside deployment/roster-leave-calendar-20261012/ was fixed too, and the checklist marks the older packages and the .zip as do-not-run.
- Disclosed rather than hidden, each offered as a commented OPTIONAL statement at the end of the file: the event_type='leave' flip (it would overwrite event_type on 13 existing rows), employment_status for the six staff who have left (the column is NOT NULL DEFAULT 'active', so it is never blank and the NULL-only fill cannot reach it), Divina's shift and the spelling decision, and the fact that 8 nickname leave entries (ids 4, 5, 6, 18, 10, 15, 19, 20) are deliberately left unlinked rather than guessed onto the wrong person. team_label, hired_date and saved shift patterns of existing staff are not rewritten, so live team_label drift is expected and documented.
- Harness traps recorded because they produced false answers first: MariaDB 10.4 ignores a NOT EXISTS / anti-join guard against the insert target inside INSERT ... SELECT in every shape tested (derived table, view, per-branch FROM DUAL) while the same subquery answers correctly as a standalone SELECT; a one-row mysql -N -B result is a bare string, so $x[$i] indexes characters; comparing a stored hash against a differently-built hash falsely reported staff_directory as 73 OVERWRITTEN; and multi-line longtext splits output lines, which inflated tickets to 15 rows.
- Local only: every destructive step ran in cng_scratch_baseline, cng_scratch_verify, cng_scratch_roster or cng_oldroster. No remote host was contacted, nothing was uploaded, and no application .php, assets/ or config/ file was modified. F088/F089/F090 logged. 267 regression checks pass.

## Session cut by power interruption - recovered (2026-10-13)
- The 2026-10-12 session (`session-cd4752e5`, cwd `C:\xampp\htdocs\cng-ticketing`, owned by the STRATA-IAN DSH store) died mid-turn at 15:41:28 after the power cut. Its log was decoded from the multi-frame zstd store and the open work recovered.
- Resume file: `_brain/progress/resume-2026-10-12-power-interruption.md` - timeline, the 13-item goal, the 7 open todos with their on-disk verification, the user's locked decisions, the local environment restart commands and the stale deployment bundle.
- Still open at the cut: Team Calendar read-only for cng-admin (not done), team-leader `view_attachments` + migration 028 (not created), local SME/Department Head test accounts (unverified), F085/F086 fix_log placeholders (line 125 still a placeholder), progress/current_state update, cPanel bundle rebuild (zip predates `team-attendance.php` and `assets/css/ui-fixes.css`) and the final regression/role/modal sweep.
## Live deploy - database import finished and re-proved (2026-10-13)
- The user ran `deployment/migration-live-additive-20261012.sql` in cPanel phpMyAdmin against the live database and reported "76 queries executed" with no error. 76 is consistent with the file: a byte-level parser finds 75 top-level statements (phpMyAdmin counts one more), and the result tables the user saw at the end are the file's own trailing self-check SELECTs.
- Re-proved the same file here, byte-safe, on a pristine 73-row import of the production dump (`cng_scratch_baseline`, dumped to a file and reloaded from the file, then the migration fed from the file): 102 staff, 92 with a department, 76 with a shift, 13 calendar events linked to staff, 0 duplicate names, 0 errors - and byte-identical numbers on a second consecutive run. This matches `DEPLOY-CHECKLIST-20261012.md` exactly.
- F091 logged: an earlier reading of 91 with_department / 75 with_shift was a harness artifact, not a migration defect. Piping the .sql into mysql.exe from PowerShell re-encoded the `Caissa Kiana Peña` literal to Latin-1, so her row never matched; the file's UTF-8 is correct and a byte-safe replay fills her. Do not report counts measured through a PowerShell text pipeline.
- New tool for the file half of the deploy: `deployment/cng-file-backup.php`. One token-gated file that downloads a zip of the 13 files the upload replaces, plus a MANIFEST.txt of their SHA256; `save`, `list` and `restore` modes are available, restore verifies every hash before it writes, and nothing is ever deleted or moved. Verified here over real HTTP: 200 with `application/zip` and the expected filename, 403 for a wrong token, 503 while the token is still the placeholder, 15 zip entries with forward-slash paths, 14 of 14 files hash-identical after extraction, and the no-ext-zip fallback writer produces a zip that .NET opens and extracts correctly.
- Order for the live deploy: database import (done) -> upload `cng-file-backup.php`, set its token, download the backup zip -> upload and extract `ui-staff-20261013-files.zip` -> delete the backup script -> Ctrl+F5. The 13 tracked files REPLACE live's current copies; the .sql and all documentation stay on the PC, because anything .sql or .txt in the web root is publicly fetchable.
## Department Workload Live 500 Follow-up (2026-10-13)

- cPanel diagnostic isolated the failure to pagination_state/pagination_nav missing from the live bootstrap; database connection and both workload queries passed.
- Updated department-workload.php to explicitly require app/pagination.php, allowing the route to work with the existing live bootstrap. Upload only the replacement page to the application root.
- Diagnostic script should be removed from the live application root after use.

## Live deploy accepted by the user (2026-10-13)
- The user confirmed the release is finished on live: "all are fine and tested". That covers the database import, the upload of the 13 tracked files plus the new `app/roster.php`, and the F092 `department-workload.php` fix.
- What live holds now: the additive roster/leave/calendar schema (102 staff, 92 with a department, 76 with a shift, 13 linked calendar events), the seven-feature UI, and a `department-workload.php` that requires `app/pagination.php` itself so it no longer depends on a bootstrap update.
- The `deployment/` folder was emptied after the upload, so this checkout no longer contains the bundle, the migration copy, the checklist or the backup tool. Nothing here is a rollback copy: rollback depends on either the zip downloaded from `cng-file-backup.php` before the extract (not confirmed whether it was kept) or the cPanel account backup.
- Recorded at the top of `summaries/current_state.md` as "Live Deploy Accepted (2026-10-13)" so the next session starts from the deployed reality instead of the August/September text further down that file.
- Still open from the 2026-10-12 goal, none of it blocking live: Team Calendar read-only for CNG Admin (`app/roster.php:49` still returns true for every role except `team-member`), team-leader `view_attachments` and migration 028, local SME/Department Head test accounts, the F086 fix_log placeholder, and whether login accounts should be created for the four new team leaders.

