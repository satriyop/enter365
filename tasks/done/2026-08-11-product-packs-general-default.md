---
status: done
priority: P0
persona: Product
---

# Product packs: general company default

## Goal

Enter365 is **mainly general SME ERP** (sales/purchasing/inventory/accounting end-to-end).  
NEX (solar) and Vahana (manufacturing/panel) packs **default OFF**, enable via env when needed.

## Done in code

- [x] `config/features.php` — `FEATURE_PRESET=general` defaults; vertical OFF
- [x] Presets: `general` | `manufacturing` | `solar` | `full`
- [x] Public solar routes gated by `feature:solar_proposals`
- [x] `GET /api/v1/features` for any authenticated user (+ `preset` field)
- [x] FE `stores/features.ts` + sidebar `feature:` filter
- [x] FE fetch on login / me
- [x] `phpunit.xml` `FEATURE_PRESET=full` so full suite still exercises packs
- [x] `.env.example` docs

## Follow-up (optional)

- [ ] Router guards for deep links to disabled packs
- [ ] Demo seeder respect preset (skip NEX/Vahana seed when general)
- [ ] Settings UI to view pack status (read-only for now)
