#!/usr/bin/env python3
"""
scribe.py — ai-nexus global-brain commit scribe.

Runs after a commit (or before a push), summarizes what the commit taught,
and APPENDS it to the idt-global-brain ledger. Claude is a scribe, not an
editor-in-chief: it classifies and appends; this script owns every path,
enforces the policy, and never lets the model rewrite or delete anything.

Design guarantees
  - Fail-soft: always exits 0 from hooks. A broken scribe must never break git.
  - Append-only: new entries and new note files only; existing entries may only
    be "reinforced" (last_used bumped, evidence added) — never rewritten.
  - Policy-gated: every write path is checked against scribe.policy.json.
  - Token-frugal: sends commit messages + a truncated, filtered diff + a compact
    id digest of existing knowledge. Never the whole repo, never full summaries.
  - Idempotent: remembers the last synced commit per repo in state/sync-state.json.

Stdlib only. Works on Windows (Git Bash), macOS, Linux.

Environment
  ANTHROPIC_API_KEY   API key (api transport)
  SCRIBE_TRANSPORT    "api" | "claude-code"
                      (auto: api if key set, else claude-code if `claude` found)
  SCRIBE_MODEL        default: claude-haiku-4-5-20251001
  BRAIN_DIR           override brain clone path
                      (else the "## Path" in _brain/memory/global_brain_link.md)
  BRAIN_REMOTE        git URL to clone if the brain dir is missing
                      (else the "## Repo" line in global_brain_link.md)
  SCRIBE_DIFF_LIMIT   max diff characters sent to the model (default 16000)
  SCRIBE_SKIP         set to anything to disable the scribe entirely

Usage
  scribe.py --range HEAD~1..HEAD              # what the post-commit hook runs
  scribe.py --range abc123..def456            # backfill any range manually
  scribe.py --range HEAD~1..HEAD --dry-run    # show the payload, call nothing
  scribe.py --mock response.json --range ...  # test apply logic without a model
  scribe.py --print-brain-path                # helper for install-scribe.sh
  scribe.py --set-brain-path /path/to/brain   # helper for install-scribe.sh
"""

import argparse
import datetime
import fnmatch
import json
import os
import re
import shutil
import subprocess
import sys
import time
import urllib.error
import urllib.request

# Windows consoles default stdout/stderr to the system codepage (e.g. cp1252),
# which cannot encode characters like "≈" or "—" used in log output below.
for _stream in (sys.stdout, sys.stderr):
    if hasattr(_stream, "reconfigure"):
        _stream.reconfigure(encoding="utf-8", errors="replace")

EMPTY_TREE = "4b825dc642cb6eb9a060e54bf8d69288fbee4904"
DEFAULT_MODEL = os.environ.get("SCRIBE_MODEL", "claude-haiku-4-5-20251001")
DIFF_LIMIT = int(os.environ.get("SCRIBE_DIFF_LIMIT", "16000"))
API_URL = "https://api.anthropic.com/v1/messages"

# Diff noise that carries no knowledge — never send these to the model.
DIFF_EXCLUDES = [
    ":(exclude)node_modules", ":(exclude)vendor", ":(exclude)dist",
    ":(exclude)build", ":(exclude).next", ":(exclude)__pycache__",
    ":(exclude)*.lock", ":(exclude)package-lock.json", ":(exclude)yarn.lock",
    ":(exclude)composer.lock", ":(exclude)*.min.js", ":(exclude)*.min.css",
    ":(exclude)*.map", ":(exclude)*.png", ":(exclude)*.jpg", ":(exclude)*.jpeg",
    ":(exclude)*.gif", ":(exclude)*.ico", ":(exclude)*.svg", ":(exclude)*.pdf",
    ":(exclude)*.zip", ":(exclude)*.woff", ":(exclude)*.woff2",
    ":(exclude)_brain",
]
# The parts of the project _brain that DO carry knowledge signal.
BRAIN_SIGNAL_PATHS = ["_brain/fixes", "_brain/decisions"]

TYPES = {
    "skill": ("skills", "summaries/skills-summary.json"),
    "bug": ("bugs", "summaries/bugs-summary.json"),
    "project": ("projects", "summaries/projects-summary.json"),
    "reference": ("references", "summaries/references-summary.json"),
}

DEFAULT_POLICY = {
    "append_only": True,
    "max_entries_per_run": 3,
    "allowed_writes": [
        "skills/*.md", "bugs/*.md", "projects/*.md", "references/*.md",
        "summaries/skills-summary.json", "summaries/bugs-summary.json",
        "summaries/projects-summary.json", "summaries/references-summary.json",
        "state/sync-state.json", "global.index.json",
    ],
    "new_skill_confidence_cap": 0.5,
}

VERBOSE = False


def log(msg):
    print("[scribe] %s" % msg, flush=True)


def vlog(msg):
    if VERBOSE:
        log(msg)


def sh(args, cwd=None, check=True, inp=None):
    """Run a command, return stdout as text. Never raises unless check=True."""
    # git always writes UTF-8 regardless of platform; decoding with the system
    # locale (cp1252 on Windows) mangles any non-ASCII byte (e.g. em dashes in
    # _brain templates) into mojibake. Force UTF-8 explicitly.
    r = subprocess.run(args, cwd=cwd, input=inp, capture_output=True,
                       text=True, encoding="utf-8", errors="replace")
    if check and r.returncode != 0:
        raise RuntimeError("command failed: %s\n%s" % (" ".join(args), r.stderr.strip()))
    return r.stdout


def git(args, cwd=None, check=True):
    return sh(["git"] + args, cwd=cwd, check=check)


# ---------------------------------------------------------------- brain link


def link_file_path(project_root):
    return os.path.join(project_root, "_brain", "memory", "global_brain_link.md")


def _read_section_value(text, header):
    """Return the first non-blank, non-comment line after '## <header>'."""
    lines = text.splitlines()
    for i, line in enumerate(lines):
        if line.strip().lower() == ("## " + header).lower():
            for j in range(i + 1, len(lines)):
                s = lines[j].strip()
                if not s or s.startswith("<!--") or s.startswith("#"):
                    if s.startswith("#"):
                        break
                    continue
                return s
    return None


def read_brain_link(project_root):
    """Return (brain_path_or_None, remote_or_None) from the link file / env."""
    path = os.environ.get("BRAIN_DIR")
    remote = os.environ.get("BRAIN_REMOTE")
    lf = link_file_path(project_root)
    if os.path.isfile(lf):
        with open(lf, encoding="utf-8") as f:
            text = f.read()
        if not path:
            v = _read_section_value(text, "Path")
            if v and v.lower() != "none":
                path = v
        if not remote:
            v = _read_section_value(text, "Repo")
            if v and v.lower() != "none":
                remote = v
    if path:
        path = os.path.abspath(os.path.expanduser(path))
    return path, remote


def set_brain_path(project_root, new_path):
    lf = link_file_path(project_root)
    if not os.path.isfile(lf):
        raise RuntimeError("no %s — run the ai-nexus installer first" % lf)
    with open(lf, encoding="utf-8") as f:
        lines = f.read().splitlines()
    out, i, done = [], 0, False
    while i < len(lines):
        out.append(lines[i])
        if lines[i].strip().lower() == "## path" and not done:
            j = i + 1
            while j < len(lines):
                s = lines[j].strip()
                if not s or s.startswith("<!--"):
                    out.append(lines[j])
                    j += 1
                    continue
                break
            out.append(new_path)
            if j < len(lines) and not lines[j].strip().startswith("#"):
                j += 1  # replace the old value line
            i = j
            done = True
            continue
        i += 1
    if not done:
        out += ["", "## Path", new_path]
    with open(lf, "w", encoding="utf-8", newline="\n") as f:
        f.write("\n".join(out) + "\n")


def ensure_brain(path, remote):
    """Make sure the brain clone exists and is up to date. Tolerate offline."""
    if not os.path.isdir(os.path.join(path, ".git")):
        if not remote:
            raise RuntimeError(
                "brain repo missing at %s and no remote configured "
                "(set '## Repo' in global_brain_link.md or BRAIN_REMOTE)" % path)
        log("cloning brain repo into %s" % path)
        git(["clone", remote, path])
    else:
        try:
            git(["pull", "--rebase", "--autostash", "--quiet"], cwd=path)
        except RuntimeError as e:
            log("warning: could not pull brain repo (offline?): %s" % e)


# ------------------------------------------------------------ commit digest


def normalize_range(project_root, rng):
    """Turn hook input into a safe a..b range."""
    if ".." in rng:
        return rng
    # A bare sha (new branch push) would mean "entire history" — cap it.
    try:
        git(["rev-parse", "--verify", rng + "~20"], cwd=project_root)
        return "%s~20..%s" % (rng, rng)
    except RuntimeError:
        return "%s..%s" % (EMPTY_TREE, rng)


def gather(project_root, rng):
    try:
        git(["rev-parse", "--verify", rng.split("..")[0]], cwd=project_root)
    except RuntimeError:
        # first commit in the repo: HEAD~1 does not exist
        rng = "%s..%s" % (EMPTY_TREE, rng.split("..")[-1])
    head = git(["rev-parse", "HEAD"], cwd=project_root).strip()
    short = head[:7]
    repo = os.path.basename(project_root)
    commits = git(["log", "--format=%h %s", rng], cwd=project_root, check=False).strip()
    stat = git(["diff", "--stat", rng, "--"] + DIFF_EXCLUDES,
               cwd=project_root, check=False).strip()
    diff = git(["diff", rng, "--"] + DIFF_EXCLUDES, cwd=project_root, check=False)
    signal = git(["diff", rng, "--"] + BRAIN_SIGNAL_PATHS,
                 cwd=project_root, check=False)
    if len(diff) > DIFF_LIMIT:
        diff = diff[:DIFF_LIMIT] + "\n... [diff truncated at %d chars]" % DIFF_LIMIT
    if len(signal) > 4000:
        signal = signal[:4000] + "\n... [truncated]"
    return {"repo": repo, "head": head, "short": short, "range": rng,
            "commits": commits, "stat": stat, "diff": diff, "signal": signal}


def load_json(path, fallback):
    try:
        with open(path, encoding="utf-8") as f:
            return json.load(f)
    except (OSError, json.JSONDecodeError):
        return fallback


def save_json(path, data):
    with open(path, "w", encoding="utf-8", newline="\n") as f:
        json.dump(data, f, indent=2, ensure_ascii=False)
        f.write("\n")


def knowledge_digest(brain):
    """Compact id lists of existing knowledge — small forever, dedupe-enabling."""
    lines = []
    for t, (_plural, summ) in TYPES.items():
        data = load_json(os.path.join(brain, summ), {"entries": []})
        entries = data.get("entries", [])
        if t == "bug":
            entries = entries[-15:]
        row = "; ".join(
            "%s — %s" % (e.get("id", "?"),
                         e.get("name") or e.get("title") or "")
            for e in entries) or "(none yet)"
        lines.append("existing %ss: %s" % (t, row))
    return "\n".join(lines)


def build_payload(ctx, digest):
    parts = [
        "repo: %s" % ctx["repo"],
        "head: %s" % ctx["short"],
        "commits:\n%s" % (ctx["commits"] or "(none)"),
        "diffstat:\n%s" % (ctx["stat"] or "(empty)"),
        "diff:\n%s" % (ctx["diff"] or "(empty)"),
    ]
    if ctx["signal"].strip():
        parts.append("project-brain signal (_brain/fixes, _brain/decisions):\n%s"
                     % ctx["signal"])
    parts.append(digest)
    return "\n\n".join(parts)


# -------------------------------------------------------------- transports


def scribe_prompt():
    here = os.path.dirname(os.path.abspath(__file__))
    with open(os.path.join(here, "SCRIBE.md"), encoding="utf-8") as f:
        return f.read()


def call_api(system, payload):
    key = os.environ.get("ANTHROPIC_API_KEY")
    if not key:
        raise RuntimeError("ANTHROPIC_API_KEY not set")
    body = json.dumps({
        "model": DEFAULT_MODEL,
        "max_tokens": 1500,
        "system": system,
        "messages": [{"role": "user", "content": payload}],
    }).encode("utf-8")
    req = urllib.request.Request(API_URL, data=body, method="POST", headers={
        "content-type": "application/json",
        "x-api-key": key,
        "anthropic-version": "2023-06-01",
    })
    for attempt in range(3):
        try:
            with urllib.request.urlopen(req, timeout=90) as r:
                data = json.loads(r.read().decode("utf-8"))
            return "".join(b.get("text", "") for b in data.get("content", [])
                           if b.get("type") == "text")
        except urllib.error.HTTPError as e:
            if e.code in (429, 500, 502, 503, 529) and attempt < 2:
                time.sleep(3 * (attempt + 1))
                continue
            raise RuntimeError("API error %s: %s" % (e.code, e.read().decode()[:300]))
    raise RuntimeError("API retries exhausted")


def call_claude_code(system, payload):
    """Subscription-friendly path: `claude -p --output-format json`."""
    exe = shutil.which("claude")
    if not exe:
        raise RuntimeError("`claude` CLI not found on PATH")
    prompt = system + "\n\n---\n\nINPUT:\n\n" + payload
    out = sh([exe, "-p", "--output-format", "json", "--model", DEFAULT_MODEL,
              "--max-turns", "2"], inp=prompt)
    data = json.loads(out)
    return data.get("result", "")


def pick_transport():
    forced = os.environ.get("SCRIBE_TRANSPORT")
    if forced:
        return forced
    if os.environ.get("ANTHROPIC_API_KEY"):
        return "api"
    if shutil.which("claude"):
        return "claude-code"
    return None


# ------------------------------------------------------------ apply & guard


def parse_model_json(text):
    text = re.sub(r"^```(?:json)?\s*|\s*```$", "", text.strip())
    start, end = text.find("{"), text.rfind("}")
    if start == -1 or end == -1:
        raise RuntimeError("model returned no JSON object: %r" % text[:200])
    return json.loads(text[start:end + 1])


def policy_allows(policy, rel_path):
    return any(fnmatch.fnmatch(rel_path, pat)
               for pat in policy.get("allowed_writes", []))


def slugify(raw):
    s = re.sub(r"[^a-z0-9]+", "-", str(raw).lower()).strip("-")
    return s[:60] or "entry"


def normalize_entry(etype, entry, ctx, cap):
    today = datetime.date.today()
    tag = "%s@%s" % (ctx["repo"], ctx["short"])
    e = dict(entry or {})
    if etype == "skill":
        ev = e.get("evidence") or []
        if not isinstance(ev, list):
            ev = [str(ev)]
        if tag not in ev:
            ev.append(tag)
        conf = e.get("confidence", 0.3)
        try:
            conf = float(conf)
        except (TypeError, ValueError):
            conf = 0.3
        return {"id": e["id"], "name": e.get("name", e["id"]),
                "status": "active", "confidence": min(conf, cap),
                "evidence": ev, "last_used": today.strftime("%Y-%m")}
    if etype == "bug":
        return {"id": e["id"], "title": e.get("title", e["id"]),
                "project": e.get("project", ctx["repo"]),
                "cause": e.get("cause", ""), "fix": e.get("fix", ""),
                "insight": e.get("insight", ""),
                "date": today.isoformat()}
    if etype == "project":
        return {"id": e["id"], "name": e.get("name", e["id"]),
                "purpose": e.get("purpose", ""),
                "outcome": e.get("outcome", ""),
                "skills": e.get("skills", []) or [],
                "status": "active", "updated": today.strftime("%Y-%m")}
    return {"id": e["id"], "title": e.get("title", e["id"]),
            "url": e.get("url", ""),
            "authority": e.get("authority", "secondary"),
            "topics": e.get("topics", []) or []}


def apply_response(resp, brain, ctx, policy):
    """Validate and apply the model's response. Returns (changed_paths, summary)."""
    action = resp.get("action", "NO_CHANGE")
    if action != "UPDATE":
        return [], "NO_CHANGE: %s" % resp.get("reason", "")

    today = datetime.date.today()
    cap = float(policy.get("new_skill_confidence_cap", 0.5))
    max_entries = int(policy.get("max_entries_per_run", 3))
    changed, added, reinforced = [], [], []
    summaries = {}

    def summary_for(etype):
        _plural, rel = TYPES[etype]
        if rel not in summaries:
            summaries[rel] = load_json(os.path.join(brain, rel),
                                       {"_type": rel, "entries": []})
        return summaries[rel]

    def reinforce(skill_id, note=""):
        data = summary_for("skill")
        for s in data.get("entries", []):
            if s.get("id") == skill_id:
                s["last_used"] = today.strftime("%Y-%m")
                tag = "%s@%s" % (ctx["repo"], ctx["short"])
                ev = s.setdefault("evidence", [])
                if tag not in ev:
                    ev.append(tag)
                reinforced.append(skill_id)
                return True
        vlog("reinforce skipped, unknown skill id: %s" % skill_id)
        return False

    for entry in (resp.get("entries") or [])[:max_entries]:
        etype = entry.get("type")
        if etype not in TYPES:
            vlog("skipping entry with unknown type: %r" % etype)
            continue
        raw = entry.get("entry") or {}
        raw["id"] = slugify(raw.get("id") or entry.get("id") or "")
        if not raw["id"] or raw["id"] == "entry":
            vlog("skipping entry with no usable id")
            continue
        plural, rel = TYPES[etype]
        data = summary_for(etype)
        existing_ids = {e.get("id") for e in data.get("entries", [])}
        if raw["id"] in existing_ids:
            if etype == "skill":
                reinforce(raw["id"])
            else:
                vlog("duplicate %s id %s — skipped (append-only)" % (etype, raw["id"]))
            continue
        data.setdefault("entries", []).append(
            normalize_entry(etype, raw, ctx, cap))
        added.append("%s:%s" % (etype, raw["id"]))

        note = entry.get("note")
        if note and isinstance(note, str) and note.strip():
            rel_note = "%s/%s-%s.md" % (plural, today.isoformat(), raw["id"])
            if not policy_allows(policy, rel_note):
                vlog("policy blocked note path %s" % rel_note)
            elif os.path.exists(os.path.join(brain, rel_note)):
                vlog("note exists, not overwriting: %s" % rel_note)
            else:
                header = ("# %s — %s\n\n> Source: %s@%s (%s)\n\n"
                          % (etype.upper(), raw["id"], ctx["repo"],
                             ctx["short"], today.isoformat()))
                path = os.path.join(brain, rel_note)
                os.makedirs(os.path.dirname(path), exist_ok=True)
                with open(path, "w", encoding="utf-8", newline="\n") as f:
                    f.write(header + note.strip() + "\n")
                for e in data["entries"]:
                    if e.get("id") == raw["id"]:
                        e["note"] = rel_note
                changed.append(rel_note)

    for r in (resp.get("reinforce") or []):
        sid = r.get("skill_id") if isinstance(r, dict) else r
        if sid:
            reinforce(slugify(sid))

    if not added and not reinforced:
        return [], "UPDATE proposed but nothing valid to apply"

    for rel, data in summaries.items():
        data["updated"] = today.isoformat()
        if not policy_allows(policy, rel):
            raise RuntimeError("policy blocked summary write: %s" % rel)
        save_json(os.path.join(brain, rel), data)
        changed.append(rel)

    idx_rel = "global.index.json"
    idx = load_json(os.path.join(brain, idx_rel), None)
    if idx is not None and policy_allows(policy, idx_rel):
        idx["updated"] = today.isoformat()
        save_json(os.path.join(brain, idx_rel), idx)
        changed.append(idx_rel)

    summary = "+%d new (%s)" % (len(added), ", ".join(added)) if added else ""
    if reinforced:
        summary += (" " if summary else "") + "~%d reinforced (%s)" % (
            len(set(reinforced)), ", ".join(sorted(set(reinforced))))
    return changed, summary


def update_state(brain, ctx, policy, changed):
    rel = "state/sync-state.json"
    state = load_json(os.path.join(brain, rel), {"repos": {}})
    state.setdefault("repos", {})[ctx["repo"]] = {
        "last_sha": ctx["head"],
        "last_run": datetime.datetime.now().isoformat(timespec="seconds"),
    }
    if policy_allows(policy, rel):
        save_json(os.path.join(brain, rel), state)
        changed.append(rel)


def already_synced(brain, ctx):
    state = load_json(os.path.join(brain, "state", "sync-state.json"), {})
    return state.get("repos", {}).get(ctx["repo"], {}).get("last_sha") == ctx["head"]


def commit_and_push(brain, ctx, changed, summary):
    for rel in sorted(set(changed)):
        git(["add", rel], cwd=brain)
    if not git(["status", "--porcelain"], cwd=brain).strip():
        vlog("nothing staged in brain repo")
        return
    msg = "scribe: %s@%s %s" % (ctx["repo"], ctx["short"], summary)
    git(["commit", "-m", msg, "--no-verify"], cwd=brain)
    log("committed to brain: %s" % msg)
    try:
        branch = git(["rev-parse", "--abbrev-ref", "HEAD"], cwd=brain).strip()
        git(["push", "origin", branch], cwd=brain)
        log("pushed to origin/%s" % branch)
    except RuntimeError as e:
        log("warning: push failed (will ride along next time): %s" % e)


# -------------------------------------------------------------------- main


def main():
    global VERBOSE
    ap = argparse.ArgumentParser(description="ai-nexus global-brain scribe")
    ap.add_argument("--range", default="HEAD~1..HEAD")
    ap.add_argument("--dry-run", action="store_true",
                    help="print the payload and exit; no model call, no writes")
    ap.add_argument("--mock", metavar="FILE",
                    help="use this JSON file as the model response (testing)")
    ap.add_argument("--force", action="store_true",
                    help="ignore brain.sync config and already-synced state")
    ap.add_argument("--strict", action="store_true",
                    help="exit non-zero on errors (default: fail-soft, exit 0)")
    ap.add_argument("--verbose", action="store_true")
    ap.add_argument("--print-brain-path", action="store_true")
    ap.add_argument("--print-brain-remote", action="store_true",
                    help="print the '## Repo' URL from global_brain_link.md (or 'none')")
    ap.add_argument("--set-brain-path", metavar="PATH")
    args = ap.parse_args()
    VERBOSE = args.verbose or args.dry_run

    try:
        if os.environ.get("SCRIBE_SKIP"):
            vlog("SCRIBE_SKIP set — doing nothing")
            return 0
        project_root = git(["rev-parse", "--show-toplevel"]).strip()

        if args.set_brain_path:
            set_brain_path(project_root, os.path.abspath(args.set_brain_path))
            print("Path set.")
            return 0
        brain, remote = read_brain_link(project_root)
        if args.print_brain_path:
            print(brain or "none")
            return 0
        if args.print_brain_remote:
            print(remote or "none")
            return 0
        if not brain:
            vlog("no global brain path set — see _brain/memory/global_brain_link.md")
            return 0

        # Loop / self-write guards
        origin = git(["config", "--get", "remote.origin.url"], check=False).strip()
        if "idt-global-brain" in origin or origin.rstrip("/").endswith("ITSB.git") \
                or origin.rstrip("/").endswith("/ITSB"):
            vlog("refusing to scribe the brain/identity repos themselves")
            return 0
        if os.path.realpath(project_root) == os.path.realpath(brain):
            vlog("project IS the brain repo — skipping")
            return 0
        if not args.force and git(["config", "--get", "brain.sync"],
                                  check=False).strip() != "true":
            vlog("brain.sync is not 'true' for this repo — skipping "
                 "(run install-scribe.sh, or use --force)")
            return 0

        ensure_brain(brain, remote)
        policy = load_json(os.path.join(brain, "scribe.policy.json"), DEFAULT_POLICY)

        rng = normalize_range(project_root, args.range)
        ctx = gather(project_root, rng)
        if not args.force and already_synced(brain, ctx):
            vlog("HEAD %s already synced for %s" % (ctx["short"], ctx["repo"]))
            return 0
        if not ctx["commits"] and not ctx["diff"].strip():
            vlog("empty range %s — nothing to do" % rng)
            return 0

        payload = build_payload(ctx, knowledge_digest(brain))
        if args.dry_run:
            print(payload)
            print("\n[scribe] ~%d chars ≈ %d tokens in payload"
                  % (len(payload), len(payload) // 4))
            return 0

        if args.mock:
            with open(args.mock, encoding="utf-8") as f:
                raw = f.read()
        else:
            transport = pick_transport()
            if not transport:
                log("no transport available: set ANTHROPIC_API_KEY or install "
                    "the `claude` CLI (Claude Code). Skipping.")
                return 0
            vlog("transport=%s model=%s payload≈%d tokens"
                 % (transport, DEFAULT_MODEL, len(payload) // 4))
            raw = (call_api if transport == "api" else call_claude_code)(
                scribe_prompt(), payload)

        resp = parse_model_json(raw)
        changed, summary = apply_response(resp, brain, ctx, policy)
        log(summary)
        update_state(brain, ctx, policy, changed)
        commit_and_push(brain, ctx, changed, summary)
        return 0
    except Exception as e:  # fail-soft: git must never break because of us
        log("ERROR: %s" % e)
        if VERBOSE:
            import traceback
            traceback.print_exc()
        return 1 if args.strict else 0


if __name__ == "__main__":
    sys.exit(main())
