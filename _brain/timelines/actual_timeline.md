# Actual Timeline — Technical

## Phase 1 — MVP Build

Completed: 2026-07-25

| Order | Deliverable | Status | Dependency |
|---|---|---|---|
| 1 | MySQL schema, first-run setup, sessions, CSRF, RBAC | Complete | None |
| 2 | Branded dashboard, responsive header, local Strata and Jamesons assets | Complete | 1 |
| 3 | Ticket create, list, edit, workflow, comments, activity, soft delete | Complete | 1, 2 |
| 4 | Roles, permissions, user activation, temporary-password reset | Complete | 1 |
| 5 | PHPMailer SMTP hooks and ignored local configuration | Complete | 1, 3 |
| 6 | XAMPP lifecycle verification and deployment documentation | Complete | 1–5 |

## Phase 2 — Deployment

Status: Pending operator action.

1. Create cPanel database and import `database/schema.sql`.
2. Create `config/config.local.php` from the example and enter database and SMTP values.
3. Run setup once, add Management email addresses, then complete the go-live checks.

## Phase 3 — Future Enhancements

Status: Deferred by scope.

- Confidential medical-certificate uploads with role access.
- Calendar and attendance view.
- Restricted leave self-service with Team Leader then Department Head approval.

## Scaling Checkpoints

| Trigger | Review |
|---|---|
| More than 100 active users or large ticket history | Review ticket-list pagination and reporting indexes. |
| Email volume affects request response time | Move notification delivery to a queue or scheduled job. |
| Attachments or leave module are approved | Add storage, access-control review, and module-specific testing. |

Last updated: 2026-07-25
