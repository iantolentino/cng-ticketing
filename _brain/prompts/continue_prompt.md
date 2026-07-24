# CONTINUE PROMPT

Paste this at the start of a session to resume work on an existing project.

---

Read the following files in this exact order. Do not read anything else.

1. `_brain/claude.md` (or `_brain/aibrain.md`)
2. `_brain/progress/progress.md`
3. `_brain/summaries/current_state.md`

You are now in EXECUTION_MODE.

Rules:
- Select ONE incomplete task from progress.md
- Validate that all its dependencies are COMPLETE
- If a dependency is not complete, select that dependency instead
- If the task is a bug fix (`B###`), read `_brain/fixes/fix_log.md` first
- Execute the selected task to full completion
- Update `progress/progress.md` and `summaries/current_state.md` (and `fixes/fix_log.md` if this
  was a bug fix)
- Stop

Do not plan. Do not re-explain the system. Do not propose future tasks.
Execute the next task and stop.
