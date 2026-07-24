# _brain INDEX

> Lookup table. Find what you need here — do not open `_brain/` folders speculatively.
> `claude.md` (or `aibrain.md`) is still the mandatory first read every session; this index is
> the second stop, used to decide which of the remaining files are actually worth opening.

---

## "I need to..." → Read

| I need to...                                  | Read                              |
|------------------------------------------------|------------------------------------|
| **Know the rules that govern AI behavior**      | **`claude.md` (THIS FILE FIRST)** |
| **Make sure required skills are installed**     | **`skills/REQUIRED_SKILLS_MANIFEST.md`** |
| **Conduct a code review (ONE PAGE)**            | **`skills/code_review_quick_ref.md`** |
| See everything this _brain system can do (one page) | `overview/system_summary.md`   |
| Check if a personal global-brain repo is linked | `memory/global_brain_link.md`      |
| Enable automatic cross-project learning on commit | `templates/scribe/README.md`     |
| Understand what this project is                | `memory/app_context.md`            |
| Understand the architecture                    | `memory/system_architecture.md`    |
| Look up a project-specific term                | `memory/glossary.md`               |
| Know what to work on next                      | `progress/progress.md`             |
| See the full task backlog                      | `progress/backlog.md`              |
| **Check if a bug was already fixed**           | `fixes/fix_log.md`                 |
| Look up a command or code pattern              | `quick-ref/`                       |
| Check a past architecture/stack decision        | `decisions/decision_log.md`        |
| Check what was explicitly rejected              | `decisions/rejected_options.md`    |
| See what's allowed to be read/written per state | `governance/rules.md`              |
| See locked MVP scope                           | `governance/scope.md`              |
| Know when to ask vs. decide                    | `interaction/assumptions.md`       |
| Know the required response format               | `interaction/response_rules.md`    |
| Check auth roles / protected routes             | `security/auth_boundaries.md`      |
| Check secrets handling rules                    | `security/secrets_policy.md`       |
| Check the deploy process                        | `deployment/deployment.md`         |
| Check env vars per environment                  | `deployment/environments.md`       |
| Check tech stack / conventions                  | `skills/skills.md`                 |
| Note a non-urgent optimization idea             | `improvements/improvement_log.md`  |
| Look up a CLI tool used in this project         | `tools/tool_inventory.md`          |
| Check DB backup/restore procedure               | `db_backup/backup_policy.md` (if present) |
| Onboard a new machine or developer              | `guides/new_machine_setup.md`      |
| Update the `_brain` system itself               | `templates/update_rules.md`        |
| Log a completed session for humans              | `summaries/weekly_summary.md`      |
| See the current session snapshot                | `summaries/current_state.md`       |
| **Conduct a code review**                       | `prompts/code_review_prompt.md`    |
| Understand code review rules & baselines        | `governance/code_review_rules.md`  |
| See the code review evaluation checklist        | `skills/code_review_checklist.md`  |

---

## Read Order by Session Type

| Session type       | Read, in order                                                              |
|---------------------|-------------------------------------------------------------------------------|
| New project         | `claude.md` → `prompts/bootstrap_prompt.md`                                  |
| Resume work         | `claude.md` → `progress/progress.md` → `summaries/current_state.md`          |
| Fix a bug           | `claude.md` → `fixes/fix_log.md` → `summaries/current_state.md`              |
| **Code review**     | **`claude.md` → `prompts/code_review_prompt.md`**                            |
| New machine/dev     | `claude.md` → `guides/new_machine_setup.md`                                  |

Never read more than this for a given session type. If you think you need more, that is a sign
the task is ambiguous — ask, per `interaction/assumptions.md`, rather than reading more files.
