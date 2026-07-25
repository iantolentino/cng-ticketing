# CNG / Jamesons Deployment Runbook

## Before upload

- [ ] Confirm `main` is pushed to GitHub.
- [ ] Export a backup of the production database if one already exists.
- [ ] Obtain the cPanel MySQL database name, user, password, and host.
- [ ] Obtain SMTP host, port, encryption, username, password, sender email, and sender name.
- [ ] Keep SMTP credentials in a password manager; enter them directly into `config/config.local.php` on the target server. Never commit or paste them into Git, `_brain`, support tickets, or chat logs.
- [ ] Obtain Management email addresses and add them to user accounts after setup.
- [x] Jamesons logo is already vendored at `assets/jamesons-logo.svg`.

## cPanel deployment

1. Upload the repository contents to the intended document-root folder.
2. Create the MySQL database and a database user in cPanel; grant all database privileges.
3. Import `database/schema.sql` in phpMyAdmin.
4. Copy `config/config.example.php` to `config/config.local.php`.
5. Fill the database values in `config/config.local.php`.
6. **Reminder: fill all SMTP values in `config/config.local.php` before enabling live notifications.**
7. Ensure `config/config.local.php` is not publicly downloadable; keep it outside version control.
8. Open `setup.php`, create the Super Admin, save the temporary passwords, then confirm setup redirects to login.

## Go-live verification

- [ ] Sign in as Super Admin.
- [ ] Confirm Team Leader cannot create a ticket and Pod Leader can.
- [ ] Change a role permission in `admin.php`; confirm it applies immediately.
- [ ] Create, assign, update, comment on, close, and reopen a ticket.
- [ ] Confirm audit activity appears and soft-deleted tickets are hidden.
- [ ] Add Management emails, configure SMTP, create a ticket, and confirm notification delivery.
- [ ] Remove any temporary test accounts or tickets.

## Rollback

1. Put the previous application files back in place.
2. Restore the database backup if the schema/data needs to be reverted.
3. Record the incident and corrective action in `_brain/fixes/fix_log.md`.
