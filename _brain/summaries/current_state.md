# Current State

## System State

EXECUTION_MODE

## Current Phase

Ticket exports complete — deployment paused by user request

## Last Completed Task

F005 — Case-normalized username lookup
Completed: 2026-07-25

## Next Task

DEP-01b — Upload, configure, import schema, apply additive migrations, and run live checks
Depends on: user approval of layout, then cPanel upload

## Active Blockers

Waiting for the user to upload the verified archive and enter production database/SMTP values in cPanel.

## Session Notes

F006 completed: `index.php` is now the only ticket register and contains the pagination query and navigation. The duplicate `tickets_paginated.php` route was removed.

Login now uses an explicit case-normalized username comparison; setup and seeded usernames already store lowercase. No email lookup is used for authentication or authorization. SMTP credentials must be entered directly into ignored `config/config.local.php`.

Last updated: 2026-07-25
