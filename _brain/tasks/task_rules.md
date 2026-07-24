# TASK RULES

## Core Execution Rules
- One task per AI session — no exceptions
- No task may begin until all dependencies are COMPLETE
- A task is only COMPLETE when its output is immediately usable
- Partial completion is not accepted — finish or mark BLOCKED
- No mid-task re-planning or scope expansion

## Completion Criteria
Before marking any task COMPLETE, verify all of the following:

- [ ] Output is usable right now without further changes
- [ ] No missing dependencies remain
- [ ] No hidden TODOs required before the MVP can use this output
- [ ] Integration with the rest of the system is valid
- [ ] `progress/progress.md` has been updated

If any item is unchecked → the task is NOT complete.

## Blocked Task Protocol
If a task cannot be completed:

1. Mark it BLOCKED in `progress/progress.md`
2. Record the blocker reason
3. Identify the dependency or input needed to unblock it
4. Stop — do not attempt another task in the same session

## Task State Transitions
```
PENDING → IN_PROGRESS → COMPLETE
PENDING → IN_PROGRESS → BLOCKED
BLOCKED → IN_PROGRESS → COMPLETE  (after blocker resolved)
PENDING → REJECTED               (by explicit decision)
```

## What the AI Must Never Do During Execution
- Refactor unrelated files
- Add features not in the task definition
- Split one task into multiple outputs
- Ask for confirmation on decisions already made in CONFIRMATION_LOCK
