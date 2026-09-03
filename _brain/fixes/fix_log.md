# FIX LOG

> Read this file FIRST before debugging anything. It is the entire memory of every bug this
> repo has already solved. Most entries should need nothing more than this table.

---

## Format

```
| ID   | Title                        | Category  | Root Cause (1 line)          | Detail File          | Date       | Status |
|------|------------------------------|-----------|-------------------------------|-----------------------|------------|--------|
| F001 | [Short bug description]     | WEB       | [One-line cause]              | inline / F001-slug.md | YYYY-MM-DD | FIXED  |
```

Categories: `WEB` | `BACKEND` | `DB` | `AUTH` | `BUILD` | `DEPLOY` | `AUTOMATION` | `CLI` | `INFRA` | `OTHER`

Status: `FIXED` | `WORKAROUND` (not a real fix, revisit) | `SUPERSEDED` (see linked replacement)

---

## Log

| ID | Title | Category | Root Cause (1 line) | Detail File | Date | Status |
|----|-------|----------|----------------------|-------------|------|--------|
| F001 | Sidebar active state stayed on Tickets | WEB | Tickets used a hardcoded `active` class instead of the current route. | inline | 2026-07-25 | FIXED |
| F002 | Ticket detail action-button spacing | WEB | Detail actions lacked a shared size rule and form-action spacing. | inline | 2026-07-25 | FIXED |
| F003 | Login page loaded without styling | WEB | `login.php` omitted the shared stylesheet and font references. | inline | 2026-07-25 | FIXED |
| F004 | Ticket actions had no user feedback | WEB | Action forms redirected without processing states or success confirmation. | inline | 2026-07-25 | FIXED |
| F005 | Username lookup depended on database collation | AUTH | Login normalized input but compared against the stored value without explicit case normalization. | inline | 2026-07-25 | FIXED |
| F006 | Tickets sidebar bypassed pagination | WEB | Pagination was added to a duplicate route while the sidebar still targeted the unpaginated canonical page. | inline | 2026-07-25 | FIXED |
| F007 | Sidebar could show more than one active item | WEB | Navigation state used separate grouped flags instead of one route-selected active item. | inline | 2026-07-27 | FIXED |
| F008 | Local pages showed session permission warnings | INFRA | PHP tried to store sessions in unwritable `C:\xampp\tmp`; app now uses ignored `.local/sessions`. | inline | 2026-08-04 | FIXED |
| F009 | Login landed on password setup for ready users | AUTH | `current_user()` cached the anonymous lookup before `login.php` set the session user id. | inline | 2026-08-04 | FIXED |
| F010 | Local setup could not create accounts | INFRA | MariaDB was stopped because the sandbox could not access its XAMPP data files; starting it outside the workspace sandbox restored database access. | inline | 2026-08-10 | FIXED |
| F011 | Day 1 admin authorization hardening | AUTH | Role permission mutation and user access updates accepted client-supplied IDs without a Super Admin boundary or safety validation. | inline | 2026-08-18 | FIXED |
| F012 | Day 1 ticket workflow status enforcement | BACKEND | Ticket update routes derived status from resolution or ignored the submitted status, allowing unintended transitions and inconsistent UI behavior. | inline | 2026-08-18 | FIXED |
| F013 | Day 1 API token and attachment hardening | SECURITY | API tokens were stored/scanned as plaintext and attachment metadata failure could leave orphaned files. | inline | 2026-08-18 | FIXED |
| F014 | Day 2 account and import validation hardening | AUTH | User administration did not protect the last active Super Admin and CSV preview could fail on malformed row widths. | inline | 2026-08-18 | FIXED |
| F015 | Day 3 leave attachment and date validation hardening | BACKEND | Leave dates were regex-only and failed attachment metadata could leave an orphaned uploaded file. | inline | 2026-08-18 | FIXED |
| F016 | Day 5 authentication input hardening | AUTH | Array-valued credential fields could trigger PHP type errors, and login did not explicitly reject non-approved account states. | inline | 2026-08-25 | FIXED |
| F017 | Day 7 shared accessibility and feedback hardening | UI/ACCESSIBILITY | The shared layout had no keyboard skip link, no explicit main-content landmark, and could fail when sessionStorage was unavailable. | inline | 2026-08-28 | FIXED |
| F018 | Day 8 API and export boundary hardening | SECURITY/PERFORMANCE | API and export query parameters were not consistently scalar-validated, API search reused one named placeholder, and exports had no row bound. | inline | 2026-08-28 | FIXED |
| F019 | Day 9 private storage and health-readiness hardening | SECURITY/OPERATIONS | Private attachments had no tracked web-server deny rule and the health page did not verify required tables or private-storage protection. | inline | 2026-08-28 | FIXED |
| F020 | Ticket submitter and category-based routing | TICKETS | Issue escalator was manually editable and category changes did not constrain the current department, involved departments, or assignee options. | inline | 2026-08-29 | FIXED |
| F021 | Attendance corrections and staff leave directory | ATTENDANCE | Attendance had no edit flow, used coverage statuses instead of the requested leave statuses, and had no persistent multi-staff leave selection. | inline | 2026-08-29 | FIXED |
| F022 | External conversation HTML displayed literally | WEB/SECURITY | The external thread renderer escaped all source HTML, showing tags instead of readable formatting. | inline | 2026-08-29 | FIXED |
| F023 | Frontend refresh broke login syntax | BUILD/AUTH | A malformed merge artifact was inserted into the refreshed `login.php`, causing a PHP parse error; the login UI was rebuilt around the approved-account authentication logic. | inline | 2026-08-29 | FIXED |
| F024 | Account settings menu and sidebar scrollbar | UI/ACCESSIBILITY | Settings was exposed as a sidebar item and the navigation scrollbar consumed scarce sidebar height, leaving account controls difficult to reach at normal zoom. | inline | 2026-08-31 | FIXED |
| F025 | Site-wide layout inconsistency and incomplete dark mode | UI/ACCESSIBILITY | Supporting screens used older page-specific layouts and hardcoded light surfaces/text, while production served a stale shared stylesheet that omitted newer component rules. | inline | 2026-08-31 | FIXED |
| F026 | Responsive overflow and Roles & Access presentation | WEB/ACCESSIBILITY | Mobile layouts inherited a desktop sidebar offset and expanding grid tracks, while the refreshed Roles & Access markup lacked its component styling. | inline | 2026-09-02 | FIXED |
| F027 | Dashboard and ticket list count mismatch | WEB | Dashboard KPIs defaulted to a seven-day creation window while Tickets listed all active records, and the range control had no all-ticket state. | inline | 2026-09-02 | FIXED |
| F028 | Compact admin tables clipped on desktop | UI/ACCESSIBILITY | The shared ticket-table minimum width was applied to two-to-five-column admin tables, hiding status, action, and detail columns behind an unnecessary desktop scrollbar. | inline | 2026-09-02 | FIXED |
| F029 | Deleted-ticket action label wrapped | UI/ACCESSIBILITY | The compact fixed-width table allowed the Restore button label to wrap after desktop table fitting was enabled. | inline | 2026-09-02 | FIXED |
| F030 | Notification bell opened a modal instead of the inbox | WEB | The header intercepted the notifications link with modal-only JavaScript, and new tickets were not surfaced as in-app alerts for management or Super Admin users. | inline | 2026-09-02 | FIXED |
| F031 | Facebook-style notification bell popover | WEB/ACCESSIBILITY | The bell opened a full page after the modal removal, so users could not preview ticket notifications without leaving the current screen. | inline | 2026-09-02 | FIXED |
| F032 | Roles & Access user controls were stacked under the wrong headings | UI/ACCESSIBILITY | The Users table had three headers for four logical control groups, placing status and save controls inside the Role cell and making rows appear duplicated or misaligned. | inline | 2026-09-02 | FIXED |
| F033 | Live external totals and department workload were incomplete | WEB | The dashboard counted only four recognized external statuses (523 of 580), external records had no department mapping, and Departments queried only the empty native-ticket store. | inline | 2026-09-03 | FIXED |
| F034 | Login intro panel was inset instead of full-width | WEB | The auth body retained grid centering, so the auto-sized auth shell stayed inset at wide viewports. | inline | 2026-09-03 | FIXED |
| F035 | Supporting screens and short dashboard trends remained difficult to use | WEB/UI | Short ranges kept monthly chart buckets, the live Recruitment link lacked its page file, and supporting screens retained compressed controls and account actions. | inline | 2026-09-03 | FIXED |
| F036 | Bulk actions panel was oversized and isolated | UI | The bulk form rendered after pagination as an unstyled full-width section instead of sharing the compact ticket-filter toolbar layout. | inline | 2026-09-03 | FIXED |

---

## Usage Rule

- Skim the table only. Open a detail file ONLY if its title matches the current problem.
- If no match exists, proceed with normal debugging, then add a new row here before stopping.
- Keep "Root Cause" to one line — that line is what future AI sessions scan for a match.
