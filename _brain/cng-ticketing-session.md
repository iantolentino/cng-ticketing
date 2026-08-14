# CNG Ticketing Session Handoff

Updated: 2026-08-14

## Current State

- Local project: `C:\xampp\htdocs\cng-ticketing`
- Production target: `https://cng-tickets.stratastaff.com/`
- Branch: `codex/add-presenter-guide`
- Latest auth UI commit: `6ed17c4 fix: align authentication action buttons`
- Local regression suite previously passed 45 checks with no failures.

## Completed August 10–14

- Public registration with password confirmation, pending status, and Super Admin approval.
- Pending/inactive login messaging, registration link, favicon, and aligned authentication buttons.
- Restored branded login layout; registration layout is centered separately.
- Public token feed with filters, pagination, rate limiting, access logs, and token rotation.
- Issue/resolution fields with resolution-driven status and public feed exposure.
- One-time unused-production cleanup and Super Admin seed script preparation.

## Next Week

- Migrate existing tickets after deployment stabilization and source-data approval.

## Deployment Rules

- Preserve live `config/config.local.php`; do not replace production database or SMTP credentials during upload.
- Never commit secrets, local test accounts, test data, or deployment-only cleanup scripts.
- Do not claim live deployment or migration completion without direct production evidence.
