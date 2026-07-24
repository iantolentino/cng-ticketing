# TASK TEMPLATES

Copy the appropriate template when adding tasks to `progress/backlog.md`.

---

## Standard Feature Task

```
ID: T[###]
Title: [Short action-oriented description — e.g. "Build user login API endpoint"]
Priority: HIGH | MEDIUM | LOW
Phase: MVP | SCALE | FUTURE
Depends On: [T### list, or "none"]
Status: PENDING

Description:
[What needs to be built and why it is needed now]

Acceptance Criteria:
- [ ] Criterion 1 — specific and verifiable
- [ ] Criterion 2 — specific and verifiable

Output:
[Exact file(s) or system state that results from completing this task]
```

---

## Bug Fix Task

```
ID: B[###]
Title: Fix — [Short description of the bug]
Depends On: [T### or "none"]
Status: PENDING

Problem:
[What is broken — observable behavior]

Root Cause:
[Why it is broken — identified source]

Fix:
[Exact change that resolves it]

Verification:
- [ ] The original issue no longer occurs
- [ ] No regression introduced in related areas
```

---

## Research / Decision Task

```
ID: R[###]
Title: Decide — [What needs a decision]
Depends On: [T### or "none"]
Status: PENDING

Question:
[The specific question that must be answered before work can continue]

Options:
- Option A: [description + tradeoff]
- Option B: [description + tradeoff]

Output:
[Log accepted decision in decisions/decision_log.md]
[Log rejected option in decisions/rejected_options.md]
```
