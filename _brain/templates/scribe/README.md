# Scribe — automatic learning into the global brain

Every commit (or push) in a linked project is summarized by Claude and
**appended** to your `idt-global-brain` ledger: new skills, bug fixes with
their transferable insight, project milestones, references. Claude acts as a
scribe — it classifies and appends; `scribe.py` owns every file path, enforces
`scribe.policy.json`, and never lets the model rewrite or delete anything.

## Enable for a project (once per repo)

```bash
bash _brain/templates/scribe/install-scribe.sh            # runs after every commit
bash _brain/templates/scribe/install-scribe.sh pre-push   # or: one batch per push (fewer runs)
```

The installer sets `git config brain.sync true`, writes a tiny trampoline hook
into `.git/hooks/` (the real hook lives here and updates with the framework),
and links the brain clone via `_brain/memory/global_brain_link.md`.

## Transport (pick one)

- **Anthropic API** — `export ANTHROPIC_API_KEY=sk-ant-...` in your shell
  profile. Default model is `claude-haiku-4-5-20251001` (override with
  `SCRIBE_MODEL`). Each run sends roughly 2–6k tokens in and a few hundred
  out — on Haiku pricing that is a fraction of a cent per commit.
- **Claude Code CLI** — if `claude` is on PATH and no API key is set, the
  scribe runs `claude -p --output-format json` instead, which uses your
  logged-in Claude Code plan. Handy if you have a Pro/Max subscription and
  no Platform key.

## What keeps it cheap and safe

- Sends only: commit messages, diffstat, a filtered diff capped at 16k chars
  (`SCRIBE_DIFF_LIMIT`), and a compact id list of existing knowledge.
  Lockfiles, binaries, `node_modules`, `vendor`, and most of `_brain/` are
  excluded; `_brain/fixes` and `_brain/decisions` are included as signal.
- Most commits come back `NO_CHANGE` — that is by design.
- Append-only + policy-gated: paths are computed by the script, checked
  against `scribe.policy.json`; new-skill confidence is capped at 0.5;
  existing skills can only be *reinforced* (last_used + evidence).
- Idempotent per commit via `state/sync-state.json`; refuses to run inside
  the brain or ITSB repos (no loops).
- Fail-soft: any error is logged to `.git/scribe.log` and git continues.

## Useful commands

```bash
# See exactly what would be sent, spend nothing:
python _brain/templates/scribe/scribe.py --range HEAD~1..HEAD --dry-run

# Backfill a repo's recent history in one run:
python _brain/templates/scribe/scribe.py --range HEAD~30..HEAD --verbose

# Pause / resume for this repo:
git config brain.sync false
git config brain.sync true

# Skip just one commit:
SCRIBE_SKIP=1 git commit -m "wip"
```

## Rules of the system

- `idt-global-brain` — scribe-assisted, append-only.
- `ai-nexus` — manual only (framework changes are human decisions).
- `ITSB` — human-only, always. Identity is never auto-edited.
