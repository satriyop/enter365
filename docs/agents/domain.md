# Domain Docs

How the engineering skills should consume this repo's domain documentation when exploring the codebase.

## Before exploring, read these

- **`CONTEXT.md`** at the repo root (if present), or
- **`CONTEXT-MAP.md`** at the repo root if it exists — it points at one `CONTEXT.md` per context. Read each one relevant to the topic.
- **`docs/08-adr/`** — project ADRs (this repo does **not** use `docs/adr/`; ADRs live under `docs/08-adr/`).
- **Enter365 architecture skills** — `.claude/skills/enter365/` (MODELS, STATE_MACHINES, EVENTS, STRATEGIES, etc.) when working domain code.
- **`docs/GLOSSARY.md`** — shared domain vocabulary when present.

If root `CONTEXT.md` does not exist yet, **proceed silently**. Don't flag its absence; don't suggest creating it upfront. The `/domain-modeling` skill (reached via `/grill-with-docs` and `/improve-codebase-architecture`) creates CONTEXT/ADRs lazily when terms or decisions actually get resolved. Prefer linking new ADRs into `docs/08-adr/` to match existing numbering.

## File structure

Single-context repo (this project):

```
/
├── CONTEXT.md                 ← optional; created by domain-modeling when needed
├── Claude.md                  ← agent instructions + skills pointers
├── docs/
│   ├── 08-adr/                ← architecture decision records
│   ├── GLOSSARY.md
│   └── agents/                ← issue tracker / triage / domain consumer rules
├── .claude/skills/enter365/   ← deep domain architecture references
└── app/
```

Multi-context layout (only if `CONTEXT-MAP.md` appears later):

```
/
├── CONTEXT-MAP.md
├── docs/08-adr/               ← system-wide decisions
└── src/
    ├── ordering/
    │   ├── CONTEXT.md
    │   └── docs/adr/
    └── billing/
        ├── CONTEXT.md
        └── docs/adr/
```

## Use the glossary's vocabulary

When your output names a domain concept (in an issue title, a refactor proposal, a hypothesis, a test name), use the term as defined in `CONTEXT.md` and/or `docs/GLOSSARY.md`. Don't drift to synonyms the glossary explicitly avoids.

If the concept you need isn't in the glossary yet, that's a signal — either you're inventing language the project doesn't use (reconsider) or there's a real gap (note it for `/domain-modeling`).

## Flag ADR conflicts

If your output contradicts an existing ADR under `docs/08-adr/`, surface it explicitly rather than silently overriding:

> _Contradicts ADR-0007 (…) — but worth reopening because…_
