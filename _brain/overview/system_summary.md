# AI NEXUS — SYSTEM SUMMARY

> Single-page answer to "what does this `_brain` system actually do." This file is
> framework-maintained — it describes AI Nexus's built-in capabilities and gets refreshed by the
> installer/updater, the same as `claude.md` and `INDEX.md`.
>
> If you add your OWN custom folders or capabilities to `_brain/` in a specific project, document
> those in `memory/system_architecture.md` instead — that file is project data and is never
> touched by an update, this one is.

---

## What This Is

A state-driven memory and governance system that lets any AI tool pick up a project without
losing context, without re-diagnosing solved bugs, and without needing a paid "AI that
remembers you" subscription — the memory lives in your own repo as plain markdown.

## The State Machine

| State | Name | What Happens |
|---|---|---|
| 1 | BOOTSTRAP_MODE | Collects project specs. No files written, no code. |
| 2 | CONFIRMATION_LOCK | Presents a plan, waits for approval. |
| 3 | SYSTEM_GENERATION | Writes the full `_brain/` structure. No code yet. |
| 4 | EXECUTION_MODE | Executes one task at a time, updates memory, stops. |

## Core Capabilities

| Category | Folders | What it buys you |
|---|---|---|
| Memory & continuity | `memory/`, `progress/`, `summaries/`, `decisions/`, `timelines/` | Project context, task state, and past decisions survive across sessions and across AI tools |
| Bug fix memory | `fixes/` | `fix_log.md` is checked before every debug session and updated after — no bug gets re-diagnosed twice |
| Code review | `prompts/code_review_prompt.md`, `governance/code_review_rules.md`, `skills/code_review_checklist.md` | Senior engineer code review with file-skip patterns (30-40% token savings), severity baselines, and systematic evaluation across bugs, security, performance, maintainability, and scalability |
| Token efficiency | `INDEX.md`, `quick-ref/` | One lookup file tells the AI exactly which small file to open instead of scanning the repo |
| Governance & guardrails | `governance/`, `interaction/`, `tasks/` | Hard rules on what the AI may read/write/assume per state, and when it must ask instead of guess |
| Optional modules | `security/`, `deployment/`, `releases/`, `db_backup/`, `improvements/`, `tools/` | Generated only when the project actually needs them (see `claude.md` § OPTIONAL MODULE RULE) |
| Cross-tool enforcement | Root-level `CLAUDE.md`, `AGENTS.md`, `.cursorrules`, `.windsurfrules`, `.github/copilot-instructions.md` | Whatever AI tool is used, it auto-loads a pointer telling it to read `claude.md` first |
| Terminal installers | `install.sh`, `install.ps1`, `setup.bat` (repo root) | One command installs `_brain/` fresh, or updates framework files without touching project data |

## What This Is NOT

- Not autonomous learning — nothing here retrains a model or adjusts weights. Memory is
  accumulated markdown, read and written because `claude.md` tells the AI to, not because the
  system detects relevance on its own.
- Not cross-project by default — each project gets its own `_brain/`. Nothing here carries
  knowledge from one repo to the next unless you build a separate global layer for that.
- Not automatic retrieval — no embeddings, no vector search. `INDEX.md` is a manually curated
  lookup table, not semantic search.

---

Last updated: 2026-07-02 — update this whenever a new folder or capability is added to `_brain/`.
