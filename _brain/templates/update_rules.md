# UPDATE RULES — UPGRADING AN EXISTING _brain

> Applies when `install.sh` / `install.ps1` / `setup.bat` is run in a project that already has a
> `_brain/` folder with real project data in it. A blind re-clone would destroy that data — never
> do that.

---

## Files Safe to Overwrite (framework files — no project data)
These are identical across every AI Nexus install and contain no project-specific content:

- `claude.md`, `aibrain.md`
- `prompts/*`
- `governance/*`
- `interaction/*`
- `tasks/task_rules.md`, `tasks/task_templates.md`
- `templates/*`
- `INDEX.md`
- `overview/system_summary.md`
- `fixes/README.md`, `fixes/_template.md`
- `quick-ref/README.md`
- All folder-level `README.md` files

## Files That Must NEVER Be Overwritten (project data)
These hold this specific project's real state — overwriting them destroys work:

- `memory/*`
- `progress/*`
- `decisions/*`
- `timelines/*`
- `summaries/*`
- `security/*`
- `deployment/*`
- `releases/*`
- `skills/*`
- `fixes/fix_log.md` and any `fixes/F###-*.md` files
- `quick-ref/commands.md`, `quick-ref/snippets.md`
- `improvements/improvement_log.md`
- `tools/tool_inventory.md`
- `db_backup/backup_policy.md`

## Update Procedure
1. Diff incoming framework files against existing ones
2. Overwrite ONLY the "safe to overwrite" list above
3. For any new folder introduced by a newer AI Nexus version (e.g. a future module) that doesn't
   exist yet in this project: create it fresh, do not attempt to merge
4. Never touch anything in the "never overwrite" list
5. Log the update in `releases/changelog.md` under `[Unreleased] → Changed`

## Scribe folder (part of the framework — always overwrite)
- `templates/scribe/scribe.py`
- `templates/scribe/SCRIBE.md`
- `templates/scribe/hooks/post-commit`
- `templates/scribe/hooks/pre-push`
- `templates/scribe/install-scribe.sh`
- `templates/scribe/README.md`

## Scribe folder (project data — never touch)
- `templates/scribe/` does NOT contain project data;
  project-specific state lives in `_brain/memory/global_brain_link.md`
  (preserved as project data per the Memory section above).
