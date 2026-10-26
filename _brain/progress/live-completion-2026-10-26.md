# Live completion record — October 26, 2026

## Application updates verified live

The user confirmed the requested updates were uploaded and passed live checks across the Super Admin, Team Leader, CNG Admin, and Management roles:

- Team Leader and Super Admin leave approval, calendar synchronization, and leave removal controls.
- Philippines-time date display.
- Staff Directory navigation/page label and an Inactive employment-status option.
- CNG Admin and Management access rules, including disabled-login status display.
- Departments involved filter aligned with Tickets department choices.
- Public leave request staff selection from the roster.

## Accounts and communication

- Created five Jamesons CNG Admin accounts; the user individually verified each login.
- The user reports sharing the account details directly through Teams and posting a feature announcement there.
- No credentials or individual account details are included in this record.

## QA cleanup

After the user confirmed permanent cleanup, the fixed-scope QA helper completed successfully:

- Removed 3 QA accounts, 2 QA staff rows, 1 QA ticket, 9 QA leave requests, and 4 QA calendar events.
- Removed the QA department, QA holiday, and related notifications.
- Attachment cleanup reported zero failures.
- Preserved audit history.
- Verified QA staff and accounts no longer appeared in Staff Directory and Users.
- The user confirmed the temporary live `qa-fixtures.php` helper was deleted.

The cleanup was limited to the marked QA fixture run. It made no live configuration or schema changes and did not target real-user records.
