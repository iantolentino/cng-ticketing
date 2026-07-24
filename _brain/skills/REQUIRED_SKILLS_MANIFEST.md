# REQUIRED SKILLS MANIFEST

These skills MUST be installed and present in the AI's context from session start. They are not optional.

## Installation Requirements

Before any AI tool starts work on a project using AI Nexus, ensure these skills are loaded:

### 1. PONYTAIL (Token Efficiency)
**Source:** https://github.com/DietrichGebert/ponytail

**What it is:** Token optimization framework — teaches AI to compress reasoning, avoid repetition, use structured outputs.

**Critical rules:**
- Assume prior state is known (don't repeat context)
- Use tables before bullets, bullets before prose
- One fact per line, not paragraphs
- No narration ("I will now..."), just output
- Skip explaining decisions already locked in memory

**Installed by:** Load as context or inject into system prompt

---

### 2. ANDREJ KARPATHY SKILLS (Coding Knowledge)
**Source:** https://github.com/multica-ai/andrej-karpathy-skills

**What it is:** Deep coding practices — attention to detail, correctness first, simplicity over cleverness.

**Critical rules:**
- Think like an experienced engineer (10+ years)
- Test the actual thing, don't assert from reading code
- No "TODO" or incomplete implementations
- No premature optimization
- No magic code — everything readable
- If it's clever, it's wrong

**Installed by:** Load as context or inject into system prompt

---

### 3. CLAUDE-MEM (Memory Integration)
**Source:** https://github.com/thedotmack/claude-mem

**What it is:** Structured memory reading/writing — cross-session continuity without context bloat.

**Critical rules:**
- Read memory files at session start, not speculatively
- Write memory atomically (one fact = one row/entry)
- Link related entries with [[references]]
- Memory is always truth — code changes; memory doesn't lie
- Prefer stored decisions over re-deliberating

**Installed by:** Enable claude-mem tool or equivalent; reference `_brain/memory/` at start

---

### 4. TASTESKILL (Anti-Slop Guidelines)
**Source:** https://www.tasteskill.dev/

**What it is:** Quality guardrails — prevents mediocre, bloated, incoherent output.

**Critical rules:**
- No placeholder content ("Lorem ipsum", "TODO", incomplete sections)
- No filler ("As mentioned earlier", "In summary", repetition)
- No generic explanations (explain the WHY, not the WHAT)
- No false certainty (say "unsure" if unsure)
- No code that "looks right but might break in edge cases"
- No output that needs editing to use

**Installed by:** Reference tasteskill.dev principles; internalize before every output

---

## Verification Checklist

Before starting ANY task, confirm:

- [ ] Ponytail token efficiency rules loaded
- [ ] Andrej Karpathy coding practices internalized
- [ ] Claude-mem memory system understood
- [ ] Tasteskill anti-slop guidelines active
- [ ] `governance/code_review_rules.md` read
- [ ] `skills/code_review_quick_ref.md` read

Do not proceed without all four.

---

## How They Interact

```
PONYTAIL (token efficiency)
    ↓
ANDREJ KARPATHY (coding excellence)
    ↓
CLAUDE-MEM (cross-session memory)
    ↓
TASTESKILL (output quality)
    ↓
CODE REVIEW GATES (verification)
    ↓
FINAL OUTPUT
```

Each layer filters output quality. If output fails ANY layer → FIX before stopping.

---

## Per-Skill Configuration

### Ponytail Integration
In prompts/template files, add:
```
Compress output:
- Bullets not paragraphs
- Tables for structured data
- One fact per line
- No narration or filler
```

### Andrej Karpathy Integration
In code generation tasks:
```
Code quality non-negotiable:
- No TODOs or incomplete code
- Test actual behavior, not theory
- Simplicity > cleverness
- If it's clever, rewrite it simpler
```

### Claude-mem Integration
At session start:
```
Read from memory:
- progress/progress.md
- summaries/current_state.md
- fixes/fix_log.md (if bug fix)
- decisions/decision_log.md (if architecture decision)

Write to memory:
- Atomically (one row = one fact)
- Immediately after task complete
- Link related entries with [[name]]
```

### Tasteskill Integration
Before stopping ANY task:
```
Quality checklist:
- No placeholders or TODOs?
- No filler or repetition?
- Explains WHY not just WHAT?
- Is actually usable, not a draft?
- Would a senior engineer approve?
```

---

## Installation Examples

### For Claude API / SDK
```python
# Load as system context
system_prompt = """
You are using AI Nexus governance.

Required skills installed:
1. Ponytail (token efficiency)
2. Andrej Karpathy (coding excellence)
3. Claude-mem (memory)
4. Tasteskill (anti-slop)

Read _brain/claude.md first, then proceed.
"""
```

### For Claude Code / VSCode Extension
```json
{
  "context": {
    "skills": [
      "ponytail:token-efficiency",
      "andrej-karpathy:coding-excellence",
      "claude-mem:memory-integration",
      "tasteskill:anti-slop"
    ],
    "brain": "_brain/claude.md"
  }
}
```

### For Claude Web (claude.ai)
Paste into session:
```
I'm using AI Nexus. Load these skills:
1. Ponytail (token efficiency)
2. Andrej Karpathy (coding excellence)
3. Claude-mem (memory integration)
4. Tasteskill (anti-slop)

Then read _brain/claude.md
```

---

## Failure Cases

If AI does NOT have these skills installed:

❌ Output is verbose and repetitive (Ponytail missing)
❌ Code has TODOs and incomplete sections (Andrej Karpathy missing)
❌ Context is re-derived session after session (Claude-mem missing)
❌ Output is mediocre, bloated, or generic (Tasteskill missing)

**Result:** AI Nexus breaks. Do not proceed without all four.

