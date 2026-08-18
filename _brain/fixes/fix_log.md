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

---

## Usage Rule

- Skim the table only. Open a detail file ONLY if its title matches the current problem.
- If no match exists, proceed with normal debugging, then add a new row here before stopping.
- Keep "Root Cause" to one line — that line is what future AI sessions scan for a match.
