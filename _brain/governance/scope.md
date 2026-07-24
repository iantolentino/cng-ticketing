# SCOPE DEFINITION

> Written and locked during CONFIRMATION_LOCK.
> Scope changes must go through the change control process in `governance/rules.md`.

---

## In Scope — MVP
Features confirmed and approved:

- [ ] [Feature 1]
- [ ] [Feature 2]
- [ ] [Feature 3]

---

## Deferred — Phase 2 (Scale Prep)
Planned but not built in MVP:

- [ ] [Feature A] — hook created in MVP, full build deferred
- [ ] [Feature B]

---

## Explicitly Rejected
Will not be built — reasons logged in `decisions/rejected_options.md`:

- [Feature X]
- [Feature Y]

---

## Scope Change Protocol
If the user requests a scope change during EXECUTION_MODE:

1. Stop the current task
2. Log the change request in `decisions/decision_log.md`
3. Update `progress/backlog.md` with new or removed tasks
4. Update `timelines/actual_timeline.md`
5. Resume from the next appropriate task

No scope change takes effect until it is written to this file and backlog.md.
