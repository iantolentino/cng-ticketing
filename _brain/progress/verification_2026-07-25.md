# MVP Verification — 2026-07-25

## Passed locally in XAMPP

- Setup page is locked after initialization and anonymous users are redirected away from protected pages.
- Super Admin login works.
- Category/subcategory data is present in the creation form.
- A ticket can be created, closed, reopened, commented on, edited, and soft-deleted.
- Saved ticket subject and subcategory values were confirmed after editing.
- Soft-deleted tickets are hidden from the dashboard and unavailable by direct ticket URL.
- Super Admin can see ticket edit/delete controls and user password-reset links.
- Seeded role grants confirm Team Leaders do not have ticket creation and Pod Leaders do.
- PHP syntax validation passed for the affected application pages.

## Deferred to Go-Live

- SMTP delivery: enter SMTP settings and Management email addresses in `config/config.local.php`, then run the email test in the deployment runbook.
- Role-level browser tests for the seeded Team Leader and Pod Leader accounts: complete after their temporary credentials are distributed or reset by the Super Admin.

Local test records were soft-deleted after verification.
