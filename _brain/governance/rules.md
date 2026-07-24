# GOVERNANCE RULES

## Authority Hierarchy
1. `claude.md` / `aibrain.md` — single source of truth for AI behavior
2. `memory/app_context.md` — confirmed project specification
3. `progress/backlog.md` — confirmed task scope
4. `fixes/fix_log.md` — confirmed prior bug fixes (authoritative for "has this been solved before")
5. All other `_brain/` files — supporting data

If any conflict exists between files, the higher authority wins.
If `claude.md` conflicts with any other file, `claude.md` wins — always.

---

## What the AI May Do Per State

| State                 | May Read                                          | May Write                              | May Generate Code |
|-----------------------|-----------------------------------------------------|-------------------------------------------|--------------------|
| BOOTSTRAP_MODE        | claude.md only                                    | nothing                                | no                 |
| CONFIRMATION_LOCK     | claude.md only                                    | nothing                                | no                 |
| SYSTEM_GENERATION     | claude.md                                         | all `_brain/` files                    | no                 |
| EXECUTION_MODE        | progress.md, current_state.md, INDEX.md as needed | task output only                       | yes                |
| Bug-fix task (`B###`) | + `fixes/fix_log.md` before starting              | + `fixes/fix_log.md` (and detail file) | yes                |

`INDEX.md` and `quick-ref/` may be read in any state — they are lookup aids, not planning or
execution artifacts, and reading them does not count as a "full repository scan."

---

## Change Control
- Architecture changes → log in `decisions/decision_log.md`
- Rejected features → log in `decisions/rejected_options.md`
- Scope additions → update `progress/backlog.md` and `timelines/actual_timeline.md`
- Scope removals → log reason in `decisions/rejected_options.md`
- Bug fixes → log in `fixes/fix_log.md` (mandatory, every fix, no exceptions — see `claude.md` §
  BUG FIX MEMORY LAYER)
- Non-urgent optimization ideas → log in `improvements/improvement_log.md`, do not act on them
  without first moving them into `progress/backlog.md`

---

## What the AI Must Never Do
- Scan the full repository without explicit instruction
- Execute more than one task per session
- Skip a state in the state machine
- Overwrite confirmed decisions without user approval
- Generate implementation code outside EXECUTION_MODE
- Attempt to fix a bug without first checking `fixes/fix_log.md`
- Finish a bug-fix task without adding a row to `fixes/fix_log.md`
