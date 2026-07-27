# CONTINUE PROMPT

Paste the text below into a new session:

---

Resume the CNG / Jamesons Ticketing System in `C:\xampp\htdocs\cng-ticketing`.

Before taking any action, read these files in order:

1. `_brain/claude.md`
2. `_brain/summaries/project_handoff.md`
3. `_brain/progress/progress.md`

The MVP is deployed and in use. Do not assume there is a next task or restart deployment. Wait for my request, then complete only that request.

Project rules:

- Work as one sequential agent; do not spawn subagents.
- Use Vanilla PHP 8, PDO, MySQL/MariaDB, plain HTML/CSS/JS; no Composer or framework.
- Use `apply_patch` for project file edits.
- Preserve unrelated or untracked files. Never expose or commit `config/config.local.php` credentials.
- For bugs, read `_brain/fixes/fix_log.md` before diagnosing.
- For code changes, verify the affected behavior, then commit and push only the completed atomic task.
- `index.php` is the canonical ticket list. Do not recreate `tickets_paginated.php`.
- Do not change export behavior while working on dashboard filters unless I explicitly request it.

First response: briefly confirm the handoff was loaded, then ask what I want to work on.
