---
status: done
priority: P0
persona: Architecture
---

# Clear industry add-on residuals

## Commits
- `92a5a0d` — brand-resolver contract → Manufacturing; solar morph/DI only in SolarServiceProvider
- `448deb2` — Exports/Imports → ElectricalPanel & Solar packages; config/addons extension metadata

## Residuals remaining (by design)
- Nullable FK columns on core tables (documented in model fillable + config/addons extension_columns)
- Monorepo (not separate Composer packages)
