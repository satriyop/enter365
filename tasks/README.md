# Tasks — Development Working System

Source of truth for **active product/engineering work**.  
Do **not** use `plans/` (legacy; gitignored).

## Directories

| Path | When to use |
|------|-------------|
| `tasks/roadmap/` | Multi-sprint direction, phased goals, go-live order |
| `tasks/audit/` | One-off findings from code+test audits (evidence-based) |
| `tasks/backlog/` | Actionable work items not yet scheduled |
| `tasks/done/` | Finished items (move here; keep short; date in filename) |
| `tasks/artifact/` | Supporting outputs: matrices, checklists, dumps, scripts notes |

## Rules

1. **Code + tests beat old write-ups.** Prefer links to `app/`, `tests/`, FE `src/api/`.
2. **One concern per file.** Name: `YYYY-MM-DD-short-slug.md` (or `NNN-short-slug.md` in backlog).
3. **Status lives in the file** (frontmatter or top section): `status: open | in_progress | blocked | done`.
4. When work finishes: move `backlog/` or active draft → `done/`, update roadmap checkboxes if needed.
5. Audits are **snapshots**. Do not keep rewriting old audits forever — add a new audit file if re-checked.
6. Artifacts are **not** the plan; they support decisions.

## Status labels (use consistently)

- `open` — not started  
- `in_progress` — actively worked  
- `blocked` — waiting on decision/dependency  
- `done` — complete (file should live under `done/`)

## Suggested workflow

```
audit  →  backlog items  →  roadmap phase  →  implement + real tests  →  done
                ↑_______________________________________________|
```
