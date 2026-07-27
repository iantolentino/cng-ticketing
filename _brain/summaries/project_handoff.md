# CNG / Jamesons Ticketing System - Project Handoff

## Current Status

- MVP is deployed and in use at `https://cng-tickets.stratastaff.com/`.
- Local and live production flows have been confirmed working by the user.
- There is no active implementation task. Continue only when the user provides a new request or feedback.
- Current Git branch: `main`; verify the current commit with `git log -1 --oneline` before making changes.

## Product and Stack

- Standalone CNG / Jamesons ticketing system for Strata Staff Global.
- Vanilla PHP 8, PDO, MySQL/MariaDB, session authentication, plain HTML/CSS/JS.
- Deployment target: XAMPP locally and cPanel in production.
- Database-driven RBAC; authorization must use permissions, not hardcoded role names.

## Core MVP Delivered

- First-run setup and Super Admin account creation.
- Users, roles, permissions, per-role access management, password resets, and forced password changes.
- Ticket creation, assignment, status workflow, comments, activity history, soft deletion, and SMTP notification hooks.
- Dashboard with dense ticket table, ticket detail/update views, CSV/XLSX export permission, pagination, and shareable on-screen filters.
- Team Calendar placeholder plus leave/calendar database foundation migration.
- Branded dual-logo sidebar/login, responsive layout, and consistent active navigation.

## Canonical Routes and Important Rules

- `index.php` is the only canonical Tickets page. It includes the 15-row pagination and GET filters.
- Do not recreate `tickets_paginated.php`; it was deliberately removed after pagination was merged into `index.php`.
- Ticket filters are on-screen only. Do not alter `export-tickets.php` when changing filters unless the user explicitly asks.
- `app/layout.php` owns the sidebar and active-navigation logic.
- Keep SMTP/database credentials only in ignored `config/config.local.php`; never commit or expose them.
- The status palette is defined at the top of `assets/css/app.css`.

## Known Completed Fixes

- F001: dynamic sidebar active state.
- F002: ticket detail action-button sizing and spacing.
- F003: shared login styling.
- F004: action feedback and delete confirmation.
- F005: case-normalized username lookup.
- F006: merged pagination into canonical Tickets route.
- F007: one active sidebar item per route.

## Current Follow-up

- Normal operational use and user feedback are the next phase.
- A daily update report was created at `reports/daily_update_2026-07-27.docx`.
- The report email has not been sent because no recipient address was supplied.

## Working Rules

- Read `_brain/claude.md` first for every repository task.
- Work sequentially; do not spawn subagents.
- Use `apply_patch` for source and Markdown edits.
- Preserve unrelated/untracked files and do not commit secrets.
- Verify changes proportionally, then commit and push completed atomic work.
