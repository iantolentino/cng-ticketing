# GLOBAL BRAIN LINK

> Optional. Points this project at a personal "global brain" repo — a separate
> repo holding cross-project skills, bugs, projects, and references.
> Shared by every project you link to it.
>
> This file is project data — the installer/updater never touches it once you set it.

---

## Path
none

<!-- Replace "none" with the local absolute path to your cloned global-brain
     repo, e.g.:
       C:/Users/ian/idt-global-brain   or   /home/ian/idt-global-brain
     Leave as "none" if you don't use a global brain repo.
     The scribe's install-scribe.sh helper sets this for you automatically. -->

## Repo
none

<!-- Replace "none" with the HTTPS or SSH URL of your global-brain repo, e.g.:
       https://github.com/iantolentino/idt-global-brain.git
     Used by the scribe to auto-clone if the Path folder does not yet exist.
     Leave as "none" to skip auto-clone. -->

## Rule
If Path is not "none":
- Read `<path>/GLOBAL.md` and `<path>/preferences.md` once per session,
  right after this project's own `claude.md`, before BOOTSTRAP_MODE spec
  collection or EXECUTION_MODE task selection — whichever comes first.
- Load knowledge in this order (all lazy, never scan folders):
    1. `<path>/summaries/skills-summary.json`  — only when choosing an approach
    2. `<path>/summaries/bugs-summary.json`    — only when fixing a bug
    3. `<path>/summaries/projects-summary.json`— only for retrospectives or
                                                  "have I built this before?" checks
    4. `<path>/summaries/references-summary.json` — only when citing a standard/spec
- If a summary entry has a `"note"` path, follow it only when the one-line
  summary is insufficient.
- Local `_brain/` files always win over anything in the global repo on conflict.
- Identity context lives in a separate private ITSB repo. Load it only when the
  task explicitly requires personal identity. Never auto-load.

## Scribe (automatic ledger updates)
The scribe (`_brain/templates/scribe/scribe.py`) appends knowledge to the
global brain after commits.  Enable it once for this repo:
```
bash _brain/templates/scribe/install-scribe.sh
```
Disable without touching the hook:
```
git config brain.sync false
```
Log: `.git/scribe.log`
