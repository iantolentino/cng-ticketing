# Local source backup — 2026-10-13

Preserves the current XAMPP application source, including the accepted October 13 roster, leave, calendar and department workload release, alongside earlier local ticket visibility, report and attachment changes. This is a source backup, not a new production deployment.

The October 7 GitHub launch fixes and production registration variant remain in repository history. Local configuration, credentials, database contents, staff spreadsheets, attachments, private QA evidence and temporary deployment packages are excluded.

Validation: changed PHP application and test files pass syntax checks; CSV behavior (21), workload (7), attachment validation (13), and requestor checks (7) pass. The existing regression suite reports 265 passes and two stale source assertions: Recruitment now enforces Super Admin access directly, and the navigation label is Team Attendance. These failures are retained as reported, without altering accepted application behavior during backup.

The pending calendar-role and other follow-ups listed in `_brain/summaries/current_state.md` remain open. This backup does not claim they are resolved. Production database and file backups remain separate from GitHub source history.

October 7 scratch scripts and private QA files under `C:/Users/Admin/cng-*` are consolidated into `.local/cng-admin-archive-20261013.zip`. All 565 archived files were verified against the originals with SHA-256. The archive is intentionally excluded from Git. Automatic approval review blocked deletion of the scratch originals, so those remain for manual cleanup. The clean `cng-github-20261007` worktree was removed through Git after its commit was confirmed as an ancestor of the published main branch.
