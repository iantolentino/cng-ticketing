# Production clean deployment plan

This plan is for the unused first deployment only. It must not be used after real production data exists.

1. Upload and apply migrations 018, 019, and 020 in numeric order.
2. Upload the approved application files and deployment/clean_and_seed_super_admin.php.
3. Visit the cleanup script once.
4. Confirm the script self-deletes.
5. Sign in as superadmin@stratastaff.com and immediately change the temporary password.
6. Remove any remaining deployment-only scripts and confirm no test tickets or QA accounts remain.

The script preserves roles, permissions, departments, and schema. It removes operational/test data, including tickets, comments, activity history, leave requests, attendance, calendar events/holidays, notifications, API tokens, and API access logs.
