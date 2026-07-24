# CNG / Jamesons MVP Roadmap

| Phase | Tasks | Exit condition |
|---|---|---|
| Foundation | FND-01 schema, setup, session auth, database-driven RBAC | Super Admin setup and permission checks work |
| Interface | UI-01 header, dashboard, design system | Header/table layout is approved |
| Ticketing | TKT-01 creation, list, update, comments, audit | Full ticket lifecycle is usable |
| Administration | ADM-01 users and role permissions | Super Admin can toggle grants |
| Notifications | NTF-01 SMTP workflow alerts | Management receives configured events |
| Verification | VRF-01 local flow, deployment guide | XAMPP and cPanel handoff is documented |

## Current work

- FND-01: complete — schema, setup, session authentication, and RBAC foundation.
- Verified: PHP syntax checks; MariaDB import (6 roles, 11 permissions, 32 grants); real setup run (20 users) then clean database reset.
- Commit/push: `872ec07` — `feat: add schema and RBAC foundation`.
- UI-01: complete — branded header and dashboard preview (`d5b87b4`).
- Current: TKT-01 — ticket creation, list, updates, comments, and activity history.
