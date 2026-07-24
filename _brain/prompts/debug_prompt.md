# DEBUG PROMPT

Paste this when something is broken, blocked, or behaving unexpectedly.

---

Read the following files in this exact order:

1. `_brain/claude.md` (or `_brain/aibrain.md`)
2. `_brain/fixes/fix_log.md` — check for a matching prior fix before doing anything else
3. `_brain/progress/progress.md`
4. `_brain/summaries/current_state.md`

Then fill in the following before sending:

```
PROBLEM:   [Describe what is broken, blocked, or wrong]
EXPECTED:  [What should happen]
ACTUAL:    [What is happening instead]
FILES:     [List the files or modules involved]
```

Rules:
- Do NOT rewrite working code to fix unrelated code
- Do NOT refactor files that are not causing the problem
- Do NOT introduce new features as part of the fix
- Fix ONLY what is broken
- Confirm the fix resolves the problem before stopping
- Update `progress/progress.md` if the fix changes task status
- Add a row to `fixes/fix_log.md` — always, even for a trivial fix — so no future session
  re-diagnoses this same bug. Create a `fixes/F###-slug.md` detail file if the root cause was
  non-obvious or is likely to recur.
