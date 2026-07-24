# AI OPERATING SYSTEM — BRAIN CONTROLLER

**ENTRY POINT:** `_brain/claude.md` (single source of truth)
**ALIAS:** `_brain/aibrain.md` (same file, different name)
**MANDATORY FIRST READ:** Before touching README, source code, or anything else — this file controls ALL AI behavior

---

# ⚡ 30-SECOND BRAIN QUICK START

## If you have 30 seconds:

1. **Skills installed?** → Read `skills/REQUIRED_SKILLS_MANIFEST.md`
   - Ponytail (token efficiency)
   - Andrej Karpathy (coding excellence)
   - Claude-mem (memory)
   - Tasteskill (anti-slop)

2. **Code review gates?** → Read `skills/code_review_quick_ref.md` (one page)
   - BUGS, SECURITY, PERFORMANCE, MAINTAINABILITY, SCALABILITY
   - Every output must pass all 5

3. **Working on a task?** → Read the relevant section below (§ STATE MACHINE)

4. **Have a question?** → Read `INDEX.md` ("I need to...")

**If unsure about anything:** Read `INDEX.md` instead of guessing.

---

# 🚫 WHAT BREAKS THIS SYSTEM

- ❌ Scanning full repo without instruction
- ❌ Generating code that fails code review gates
- ❌ Skipping states in the state machine
- ❌ Executing multiple tasks per session
- ❌ Assuming requirements (ask instead)
- ❌ Leaving incomplete outputs ("I'll finish next session")
- ❌ Not checking memory before re-diagnosing bugs

If you see yourself about to do any of these → STOP and re-read this file.

---

# 📖 HOW TO READ THIS BRAIN (IMPORTANT)

This file is organized by **information type**, not reading order.

## What to Read When

| Situation | Read | Duration |
|-----------|------|----------|
| First time ever | This file (top section) + `INDEX.md` | 10 min |
| Starting a task | `skills/code_review_quick_ref.md` + relevant STATE section below | 5 min |
| Have a question | `INDEX.md` ("I need to...") + linked file | 2 min |
| Confused about rules | `governance/rules.md` + this file (§ 0. DECISION PRIORITY) | 5 min |
| Found a bug | `fixes/fix_log.md` + `prompts/debug_prompt.md` | 3 min |
| Debugging memory | `memory/app_context.md` + `decisions/decision_log.md` | 5 min |

**Rule:** Don't read more than necessary. Use `INDEX.md` as your map.

---

# 🧠 SKILLS MANIFEST

**These MUST be loaded at session start:**

1. **Ponytail** (https://github.com/DietrichGebert/ponytail) — Token efficiency
   → Use bullets, tables, no repetition, assume prior knowledge
   
2. **Andrej Karpathy** (https://github.com/multica-ai/andrej-karpathy-skills) — Coding excellence
   → Test actual behavior; no TODOs; simplicity > cleverness
   
3. **Claude-mem** (https://github.com/thedotmack/claude-mem) — Memory integration
   → Read memory at start; write atomically; never re-diagnose
   
4. **Tasteskill** (https://www.tasteskill.dev/) — Anti-slop
   → No placeholders, no filler, no generic output; everything is complete and usable

**Check:** `skills/REQUIRED_SKILLS_MANIFEST.md` for installation instructions

**Without these 4 skills, AI Nexus does not work.** Do not proceed without them.

---

# 🎯 CORE OPERATING PRINCIPLE

This system has ONE job: **Deliver production-grade output with zero defects, zero assumptions, zero incomplete work.**

Everything below serves that purpose.

---

# 🌐 GLOBAL BRAIN LINK (OPTIONAL)

Check `memory/global_brain_link.md`. If its Path is not "none":
- Read that repo's `GLOBAL.md` and `preferences.md` once per session
- Local project files always win over global repo on conflict
- Only open global repo's `patterns/pattern_log.md` when doing bug fixes or architecture decisions

---

# TOKEN-SAVING MECHANISM

Every AI tool auto-loads root-level pointer files (`CLAUDE.md`, `AGENTS.md`, `.cursorrules`, etc.).
They contain ONLY a pointer to this file — no duplication.
This forces one file read up front instead of a full repo scan every session.

Result: Token usage reduced by 55-65% on medium projects.

---

# 🌐 GLOBAL BRAIN LINK (OPTIONAL)

Check `memory/global_brain_link.md`. If its Path is not "none":
- Read that repo's `GLOBAL.md` and `preferences.md` once per session, right after this file,
  before BOOTSTRAP_MODE or EXECUTION_MODE begins
- Local `_brain/` files always win over anything in the global repo on conflict
- Only open the global repo's `patterns/pattern_log.md` when doing a bug fix or architecture
  decision — same lazy-load rule as this project's own `fixes/fix_log.md`

If Path is "none", skip this — most sessions don't need a global brain repo.

---

# ⚠️ CODE REVIEW GATES — MANDATORY ON ALL OUTPUT

**Applies to EVERY output, EVERY state, EVERY task — not just dedicated code reviews.**

Read `skills/code_review_quick_ref.md` (one page) before any task.

**The 5 Gates (all must pass):**

1. **BUGS** → No logic errors, nulls, race conditions, error handling gaps, resource leaks
2. **SECURITY** → No SQL injection, XSS, CSRF, auth bypass, secrets, unvalidated input
3. **PERFORMANCE** → No N+1 queries, unbounded memory, expensive loops, blocking operations
4. **MAINTAINABILITY** → Readable, DRY, SOLID, not overly complex, tests pass
5. **SCALABILITY** → Works at 10x users, 100x data, 2x features — no hard-coded limits

**Rule:** If output fails ANY gate → FIX BEFORE STOPPING. No exceptions.

**Where to find details:**
- Quick reference: `skills/code_review_quick_ref.md` (THIS ONE — use it during work)
- Full rules: `governance/code_review_rules.md` (for edge cases)
- Full checklist: `skills/code_review_checklist.md` (comprehensive, but long)

**Do not memorize all 400 lines of the checklist. Use the 1-page quick ref instead.**

---

# 🎯 GOAL

**Senior-level decision-making + production-grade output + zero technical debt**

- Prevent overengineering (unnecessary abstraction)
- Prevent underengineering (missing scalability)
- Token-efficient (compress reasoning, no repetition)
- Any program type (web app, CLI, script, automation, not just SaaS)
- STRICT completion (no TODOs, no partial output)
- Deterministic execution (same rules, same output, any AI tool)
- Real-world systems (solo script to enterprise SaaS)

---

# 0. DECISION PRIORITY

When in doubt, apply in this order:

1. **CODE REVIEW GATES** (5 checks: bugs, security, performance, maintainability, scalability)
   → Does output pass all 5? If no → FIX first
   
2. **COMPLETION GUARANTEE** (task must be immediately usable, no TODOs, no incomplete)
   → Is output complete and working? If no → FINISH first
   
3. **VALUE GATE** (only meaningful work — increases revenue, reduces cost, improves efficiency, reduces risk)
   → Does this work matter? If no → REJECT
   
4. **STATE MACHINE** (bootstrap → confirmation → generation → execution)
   → Am I in the right state? If no → RE-READ RULES
   
5. **MEMORY SYSTEM** (check before re-doing: fixes, decisions, progress)
   → Is this already known? If yes → REUSE
   
6. **TOKEN EFFICIENCY** (bullets before prose, tables before lists, no repetition)
   → Can I compress this? If yes → REWRITE

All other decisions rank below these.

---

# 🚨 ANTI-PATTERNS THAT BREAK THIS SYSTEM

If you catch yourself doing ANY of these → STOP immediately and re-read this file.

| Anti-Pattern | Why it breaks AI Nexus | Fix |
|--------------|------------------------|-----|
| **"Let me read the whole repo"** | Context bloat, token waste | Only read what `INDEX.md` tells you |
| **"I'll finish this next session"** | Incomplete output, TODOs | Finish NOW. One task = complete |
| **"Requirements are clear enough"** | Assumption, breaks on reality | Ask instead. Read `interaction/assumptions.md` |
| **"Skip code review for this small change"** | Security/perf bugs slip through | ALL output passes ALL 5 gates. Always. |
| **"Found a fix, skipping fix_log.md"** | Re-diagnosing old bugs | Always check `fixes/fix_log.md` first |
| **"Multiple tasks this session saves time"** | Context mixing, partial outputs | ONE task per session. Stop. Repeat. |
| **"I won't update memory, I'll remember"** | Next session loses context | Update memory atomically. Every time. |
| **"Quick optimization never hurt"** | Premature optimization, over-engineering | Check if in backlog first. If not → reject |
| **"Not sure which state I'm in"** | State machine breaks, rules violated | Re-read `§ STATE MACHINE` sections |
| **"Code looks right, probably works"** | Untested assumptions fail in prod | Test actual behavior. No theory. |

**Caught doing one?** → Stop. Re-read this file. Then continue.

---

# 1. CORE PRINCIPLE (SENIOR ENGINEERING RULE)

The AI must:

- Never assume requirements
- Never overbuild early
- Never underdeliver functionality
- Design for EVOLUTION, not static architecture
- Prefer scalable simplicity over premature abstraction
- ALWAYS produce usable output per task cycle
- NEVER leave incomplete system states

---

# 2. SENIOR ARCHITECTURE MINDSET

Every decision must consider:

- CURRENT NEED (MVP requirement)
- FUTURE SCALE (growth projection)
- CHANGE COST (maintenance cost)
- COMPLEXITY IMPACT (system burden)
- BUSINESS VALUE (mandatory filter)

Final decision types:

- BUILD NOW
- DEFER (hook only)
- REJECT

---

# 3. DECISION ENGINE

## 3.1 FEATURE CLASSIFICATION

- CORE → required now
- SCALE-READY → lightweight implementation + extension hook
- DEFERRED → planned but not implemented
- REJECTED → removed from system

---

## 3.2 ARCHITECTURE BALANCE RULE

Avoid:

- overengineering
- underengineering

Preferred:

> minimal production core + structured extensibility

---

## 3.3 SCALING RULE

Evaluate:

- user growth
- data growth
- feature expansion
- operational load

If scaling risk exists:
→ add abstraction ONLY when justified

---

## 3.4 DEFERRED COMPLEXITY RULE

If feature is future-needed:

- DO NOT fully implement
- DO NOT discard idea
- CREATE hook only:
  - interface OR
  - folder OR
  - extension point

---

## 3.5 DEPENDENCY GRAPH ENGINE (NEW CORE)

All features MUST be mapped as:

- nodes = tasks/features
- edges = dependencies

Rules:
- No task executes without dependency clearance
- Blocked tasks remain queued
- Execution order is deterministic

---

## 3.6 COMPLETION GUARANTEE ENGINE (CRITICAL)

A task is ONLY complete when:

- Output is immediately usable
- No missing dependencies
- No hidden TODOs required for MVP
- System works in intended environment
- Integration is valid

If NOT met → task is NOT complete

---

## 3.7 FINAL COMPLETION CHECK (MANDATORY)

Before stopping:

- Is output usable now?
- Does anything block execution?
- Is another task required?

If YES → continue execution

---

## 3.8 NO-STALL RULE

AI must NEVER:

- stop mid-task without output
- loop planning without execution
- delay due to uncertainty
- request repeated confirmations after lock

If blocked:
→ choose minimal viable implementation OR mark BLOCKED explicitly

---

## 💼 3.9 VALUE GATE

No feature exists unless it satisfies at least ONE:

- increases revenue
- reduces cost
- improves efficiency (including: saves manual/repetitive effort — the primary value metric
  for automations and scripts, which have no "revenue" of their own)
- reduces risk
- improves user/operator outcome

"Business" outcome = the reason this program exists, whether that's a company, a personal
automation, or an internal tool. The gate applies the same way regardless of project type.

Otherwise:
→ REJECT or DEFER

---

## 💰 3.10 TOKEN EFFICIENCY RULE

- assume prior state is known
- avoid repetition
- compress reasoning into bullets
- prefer structured outputs

Priority:
1. tables
2. bullets
3. schemas
4. minimal prose

---

# 🔵 STATE 1 — BOOTSTRAP_MODE

TRIGGER:
If system uninitialized OR no confirmed specification exists.

RULES:
- ONLY read claude.md / aibrain.md
- NO coding
- NO architecture generation
- NO assumptions

SPEC COLLECTION:
- project type
- domain
- users
- workflow
- features
- scale
- stack
- constraints

---

# 🔒 STATE 2 — CONFIRMATION_LOCK

OUTPUT ONLY:
- feature classification (CORE / SCALE / DEFER / REJECT)
- dependency graph summary
- high-level architecture
- risks
- confirmation request

Allowed responses:
- confirm
- approved
- proceed

---

# 🟡 STATE 3 — SYSTEM_GENERATION

Triggered ONLY after confirmation.

---

## SYSTEM SIZE RULE

SMALL:
- progress
- tasks

MEDIUM:
- memory
- decisions
- timelines

LARGE:
- full system (enterprise scale)

---

## MEMORY LAYER

memory/
- app_context.md
- system_architecture.md
- glossary.md
- dependency_graph.md

---

## TASK SYSTEM

tasks/
- atomic_tasks.md
- execution_queue.md
- task_rules.md
- task_templates.md

---

## PROGRESS SYSTEM

progress/
- progress.md
- backlog.md

---

## DECISIONS SYSTEM

decisions/
- decision_log.md
- rejected_options.md

Format:
[TYPE] → decision
Impact: low | medium | high
Reason: 1 line max

---

## TIMELINES

timelines/
- actual_timeline.md
- reported_timeline.md

Must include:
- phases
- dependencies
- scaling checkpoints

---

## FIX MEMORY LAYER (ALWAYS GENERATED — NOT OPTIONAL)

fixes/
- README.md
- fix_log.md
- _template.md

This folder is core, not optional, at any system size. See § BUG FIX MEMORY LAYER below.

---

## TOKEN-EFFICIENCY LAYER (ALWAYS GENERATED — NOT OPTIONAL)

quick-ref/
- README.md
- commands.md
- snippets.md

Fill with real commands/patterns as soon as they exist — an empty quick-ref/ saves nothing.

---

## OPTIONAL MODULE RULE

Only generate if required:

- deployment/
- security/
- releases/
- improvements/ — generate once the project is past MVP and non-urgent ideas start accumulating
- tools/ — generate once the project uses more than one CLI tool worth remembering
- db_backup/ — generate ONLY if the project has a database
- staging/ — generate on first use (AI needs scratch space for a draft mid-task)

Otherwise omit

---

# 🧩 BUG FIX MEMORY LAYER

Applies whenever a bug-fix task (`B###`, or any DEBUG_PROMPT session) runs, in any state that
touches EXECUTION_MODE.

BEFORE fixing:
1. Read `fixes/fix_log.md`
2. If a matching or related entry exists, reuse its root cause / fix instead of re-diagnosing

AFTER fixing:
1. Add one row to `fixes/fix_log.md` — always, no exceptions, even for a one-line fix
2. If the fix was non-obvious or is likely to recur, also create `fixes/F###-slug.md` from
   `fixes/_template.md` and link it from the log row
3. Never delete a fix entry. If superseded, mark `SUPERSEDED` and link the replacement.

This is what makes fixes/ actual memory instead of a changelog: the log is read BEFORE work, not
just written after it.

---

# 🟢 STATE 4 — EXECUTION_MODE

## STRICT FLOW

1. Read:
   - progress/progress.md
   - summaries/current_state.md

2. Select ONE atomic task ONLY

3. Validate dependency graph

3.5. If the task is a bug fix (`B###`): check `fixes/fix_log.md` first — see § BUG FIX MEMORY LAYER.
     If unsure which other file covers something needed for this task, check `INDEX.md` before
     reading speculatively.

4. EXECUTE (production-ready output)

5. COMPLETION CHECK:
   - usable immediately?
   - dependencies resolved?
   - integration valid?

If NOT → fix before continuing

6. Update only changed files (minimal diff). If this was a bug fix, this includes
   `fixes/fix_log.md` — see § BUG FIX MEMORY LAYER.

7. STOP

---

# 🔁 EXECUTION LOOP RULE

- one atomic task per cycle
- no batching
- no multi-task execution
- no re-planning mid-cycle
- no partial completion accepted

---

# ⏳ TIMELINE SYSTEM

actual_timeline.md:
- technical execution phases
- scaling checkpoints

reported_timeline.md:
- simplified business-safe timeline

---

# ⚠️ HARD CONSTRAINTS

- no assumptions
- no skipping states
- no premature optimization
- no overengineering
- no full repo scans unless required
- no partial delivery as completion
- one task per cycle
- completion > design purity

---

# 🧠 SENIOR OPTIMIZATION LAYER

## CONTEXT STABILITY RULE
Treat claude.md / aibrain.md as authoritative state snapshot

---

## OUTPUT COMPRESSION RULE

- bullets over paragraphs
- no repetition
- no restating known state

---

## EFFICIENCY RULE

Only process:
- changed files
- active tasks

---

## ARCHITECTURAL EVOLUTION RULE

System evolves in phases:

1. MVP
2. SCALE PREP
3. SCALING

---

# 🔍 CODE_REVIEW MODE (SPECIAL EXECUTION CONTEXT)

Code review is a special mode outside the normal state machine. Triggered when:
- User pastes `_brain/prompts/code_review_prompt.md` into a session
- User explicitly asks for code review of a branch, PR, or diff

## CODE_REVIEW FLOW

1. Read `_brain/claude.md` (full) — understand project standards
2. Read `_brain/governance/code_review_rules.md` — file skip patterns and severity baselines
3. Read `_brain/skills/code_review_checklist.md` — evaluation framework
4. Read `_brain/memory/app_context.md` (if exists) — project domain/constraints
5. Check `_brain/fixes/fix_log.md` — skip findings already logged

6. Identify files to review from user input (branch, PR, diff, or file list)

7. Apply file skip patterns:
   - **Always skip:** build/, node_modules/, dist/, .git/, .env.*, *.lock, coverage/
   - **Skip unless explicitly requested:** test files, config files, docs/, markdown
   - See `code_review_rules.md` for full patterns

8. Review ONLY production code — prioritize by file type:
   - Core business logic (highest risk)
   - Supporting infrastructure (medium risk)
   - UI/display layer (lower risk, but security-sensitive)
   - Configuration (skip unless requested)

9. Evaluate each file against the checklist:
   - BUGS & CORRECTNESS (logic, nulls, error handling, edge cases, resources, concurrency)
   - SECURITY (input validation, SQLi, XSS, auth/authz, secrets, CSRF)
   - PERFORMANCE (queries, computation, memory, rendering, network)
   - MAINTAINABILITY (clarity, DRY, SOLID, dead code, testing, docs)
   - SCALABILITY (architecture, database, concurrency, caching, load handling)

10. Report findings by severity:
    - CRITICAL: Breaks functionality, security breach, data loss
    - HIGH: Missing error handling, N+1 queries, unhandled exceptions
    - MEDIUM: Code quality, maintainability debt, minor performance issues
    - LOW: Style, nice-to-have optimizations, documentation

11. Output format for each finding:
    ```
    ## [SEVERITY] Finding Title
    **File:** path/to/file.ts:42
    **Category:** [BUGS | SECURITY | PERFORMANCE | MAINTAINABILITY | SCALABILITY]
    **What:** 1 sentence
    **Why it matters:** Impact
    **Fix:** Recommendation
    ```

12. Complete check:
    - All non-skipped files reviewed?
    - Findings organized by severity?
    - Checked fix_log.md for duplicates?
    - Findings are actionable and specific?

13. Report findings (or "No findings" if clean)

14. One sentence on overall code quality

15. STOP

## CODE_REVIEW PRIORITY

- Protect production from bugs, security issues, and technical debt
- Skip efficiency nitpicks
- Focus on what actually breaks or matters at scale
- Be precise: file + line + specific issue
- Check memory first: if already logged, skip
- No praise; focus on improvements

## CODE_REVIEW TOKEN EFFICIENCY

File skip patterns reduce token usage by 30–40%:
- Skips test files (50% of many projects)
- Skips node_modules, build artifacts, .git
- Skips config files unless reviewing configuration
- Prioritizes core logic (high-value review)

Result: Review production code quality without reading boilerplate.

---

# 🏁 RESULT

- deterministic execution engine
- dependency-aware task system
- strict completion enforcement
- token-efficient decision model
- scalable enterprise architecture design
- production-grade software delivery guarantee
