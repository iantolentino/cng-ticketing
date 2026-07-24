#!/usr/bin/env bash
# AI Nexus — self-update script.
# Lives inside YOUR project at _brain/templates/repo_init_script.sh (copied there by the
# installer). Run it any time from your project root to pull the latest AI Nexus framework
# files without touching your project's data.
#
#   bash _brain/templates/repo_init_script.sh
#
# See _brain/templates/update_rules.md for exactly what is and isn't touched.

set -euo pipefail

REPO="https://github.com/iantolentino/ai-nexus.git"
TARGET="_brain"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

if [ ! -d "$TARGET" ]; then
  echo "No _brain/ folder found in this directory. Run this from your project root." >&2
  exit 1
fi

echo "Fetching latest AI Nexus framework files..."
git clone --depth 1 "$REPO" "$TMP_DIR" >/dev/null 2>&1

# Framework-only paths — safe to overwrite. Project data (memory/, progress/, decisions/,
# fixes/fix_log.md, etc.) is deliberately excluded. See update_rules.md.
FRAMEWORK_PATHS=(
  "claude.md"
  "aibrain.md"
  "INDEX.md"
  "overview"
  "prompts"
  "governance"
  "interaction"
  "tasks/task_rules.md"
  "tasks/task_templates.md"
  "templates"
  "fixes/README.md"
  "fixes/_template.md"
  "quick-ref/README.md"
)

for path in "${FRAMEWORK_PATHS[@]}"; do
  src="$TMP_DIR/_brain/$path"
  dest="$TARGET/$path"
  if [ -d "$src" ]; then
    # trailing /. copies CONTENTS into dest — plain "cp -r src dest" would nest src
    # inside an already-existing dest instead of merging/overwriting in place.
    mkdir -p "$dest"
    cp -rf "$src/." "$dest/"
    echo "Updated: $path/"
  elif [ -e "$src" ]; then
    mkdir -p "$(dirname "$dest")"
    cp -f "$src" "$dest"
    echo "Updated: $path"
  fi
done

echo "Done. Project data was not touched — log this update in _brain/releases/changelog.md."
