# Current State

## System State

EXECUTION_MODE

## Current Phase

Ticket exports complete — deployment paused by user request

## Last Completed Task

EXP-01 — Permission-gated CSV and Excel ticket exports
Completed: 2026-07-25

## Next Task

DEP-01b — Upload, configure, import schema, apply additive migrations, and run live checks
Depends on: user approval of layout, then cPanel upload

## Active Blockers

Waiting for the user to upload the approved build in cPanel.

## Session Notes

CSV and native XLSX exports use the same active-ticket query as the dashboard. `export_tickets` is granted only to Super Admin by migration `003`; Roles & Access can toggle it for any role. CSV values are spreadsheet-formula-safe. SMTP credentials must be entered directly into ignored `config/config.local.php`.

Last updated: 2026-07-25
