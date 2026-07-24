#!/usr/bin/env bash
# Enable the global-brain scribe for THIS project.
# Usage (from the project root, after the ai-nexus installer has run):
#   bash _brain/templates/scribe/install-scribe.sh              # post-commit (default)
#   bash _brain/templates/scribe/install-scribe.sh pre-push     # batch per push instead
set -euo pipefail

HOOK="${1:-post-commit}"
case "$HOOK" in post-commit|pre-push) ;; *)
  echo "hook must be post-commit or pre-push" >&2; exit 1;; esac

ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || { echo "Run inside a git repo." >&2; exit 1; }
SRC="$ROOT/_brain/templates/scribe"
[ -f "$SRC/scribe.py" ] || { echo "scribe not found — run the ai-nexus installer first." >&2; exit 1; }
PY="$(command -v python3 2>/dev/null || command -v python 2>/dev/null || true)"
[ -n "$PY" ] || { echo "python is required (Git Bash on Windows: install Python and add to PATH)." >&2; exit 1; }

# 1) Trampoline hook — the real hook lives in _brain and auto-updates with the framework.
DEST="$ROOT/.git/hooks/$HOOK"
if [ -f "$DEST" ] && ! grep -q "ai-nexus scribe" "$DEST"; then
  echo "An unrelated $HOOK hook already exists — not overwriting."
  echo "Add this line to it manually:"
  echo "  sh \"\$(git rev-parse --show-toplevel)/_brain/templates/scribe/hooks/$HOOK\" \"\$@\" <&0"
else
  {
    echo '#!/bin/sh'
    echo '# ai-nexus scribe trampoline — real hook lives in _brain (updates with the framework)'
    echo 'R="$(git rev-parse --show-toplevel 2>/dev/null)" || exit 0'
    echo "H=\"\$R/_brain/templates/scribe/hooks/$HOOK\""
    echo '[ -f "$H" ] && sh "$H" "$@" <&0'
    echo 'exit 0'
  } > "$DEST"
  chmod +x "$DEST"
  echo "Installed .git/hooks/$HOOK (trampoline)."
fi

# 2) Per-repo opt-in switch.
git config brain.sync true
echo "brain.sync = true  (disable anytime: git config brain.sync false)"

# 3) Make sure the global brain is linked.
BRAIN="$("$PY" "$SRC/scribe.py" --print-brain-path 2>/dev/null || echo none)"
if [ "$BRAIN" = "none" ] || [ -z "$BRAIN" ]; then
  DEFAULT="$HOME/idt-global-brain"
  if [ -d "$DEFAULT/.git" ]; then
    "$PY" "$SRC/scribe.py" --set-brain-path "$DEFAULT"
    echo "Linked global brain: $DEFAULT"
  else
    REPO_URL="$("$PY" "$SRC/scribe.py" --print-brain-remote 2>/dev/null || echo none)"
    if [ "$REPO_URL" = "none" ] || [ -z "$REPO_URL" ]; then
      echo "No global brain linked, and no '## Repo' URL set in"
      echo "_brain/memory/global_brain_link.md — set that first, or set Path directly."
    else
      echo "Global brain not cloned locally yet (Repo: $REPO_URL)."
      if [ -t 0 ]; then
        printf "Clone it to %s now? [Y/n] " "$DEFAULT"
        read -r ans
        if [ "${ans:-Y}" != "n" ] && [ "${ans:-Y}" != "N" ]; then
          git clone "$REPO_URL" "$DEFAULT"
          "$PY" "$SRC/scribe.py" --set-brain-path "$DEFAULT"
          echo "Linked global brain: $DEFAULT"
        fi
      else
        echo "Do it manually:"
        echo "  git clone $REPO_URL \"$DEFAULT\""
        echo "  $PY _brain/templates/scribe/scribe.py --set-brain-path \"$DEFAULT\""
      fi
    fi
  fi
else
  echo "Global brain already linked: $BRAIN"
fi

# 4) Transport check.
if [ -n "${ANTHROPIC_API_KEY:-}" ]; then
  echo "Transport: Anthropic API (ANTHROPIC_API_KEY is set)."
elif command -v claude >/dev/null 2>&1; then
  echo "Transport: Claude Code CLI (claude -p), billed to your subscription/plan."
else
  echo "WARNING: no transport found. Either:"
  echo "  - export ANTHROPIC_API_KEY=sk-ant-...   (Claude Platform key), or"
  echo "  - install Claude Code so \`claude\` is on PATH."
  echo "The scribe will silently skip until one exists."
fi

echo ""
echo "Test it without spending tokens:"
echo "  $PY _brain/templates/scribe/scribe.py --range HEAD~1..HEAD --dry-run"
echo "Log lives at .git/scribe.log"
