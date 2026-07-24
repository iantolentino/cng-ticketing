# CNG / Jamesons Issues Ticketing System

1. Create a MySQL/MariaDB database named `cng_ticketing`.
2. Copy `config/config.example.php` to `config/config.local.php` and set database credentials.
3. Import `database/schema.sql` in phpMyAdmin.
4. Open `setup.php` to create the Super Admin and seeded users.

SMTP credentials belong only in `config/config.local.php`, never Git.
