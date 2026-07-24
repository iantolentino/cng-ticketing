# ENTRYPOINTS

> Not part of `_brain/` itself. These are pointer files the installer (`install.sh` / `install.ps1`
> / `setup.bat`) copies to the **root** of the target project — one per major AI coding tool — so
> that whichever AI tool a user runs, it auto-loads a file that tells it to read `_brain/claude.md`
> first, in full, before doing anything else.

---

| File                              | Auto-read by                          | Target location in project      |
|------------------------------------|----------------------------------------|----------------------------------|
| `CLAUDE.md`                        | Claude Code                            | `./CLAUDE.md`                    |
| `AGENTS.md`                        | Codex CLI and other agentic tools that follow the AGENTS.md convention | `./AGENTS.md` |
| `.cursorrules`                     | Cursor                                 | `./.cursorrules`                 |
| `.windsurfrules`                   | Windsurf                               | `./.windsurfrules`               |
| `copilot-instructions.md`          | GitHub Copilot                         | `./.github/copilot-instructions.md` |

---

## Installer Rule
Only write a file if it does not already exist at the target path. If the target project already
has its own `CLAUDE.md`, `.cursorrules`, etc., the installer must leave it untouched and instead
print a one-line instruction telling the user to manually add:

> "Read `_brain/claude.md` in full before doing anything else."

...to the top of their existing file. Never overwrite a user's existing AI instructions.
