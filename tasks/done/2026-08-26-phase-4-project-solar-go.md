---
status: done
date: 2026-08-26
type: done
phase: 4
verdict: Go
persona: Project, Solar (NEX)
---

# Phase 4 Project / Solar SPA — Go

**Verdict: Go** for project start + cost line, and solar wizard + convert-to-quotation, on `FEATURE_PRESET=general` with pack overrides.

## Evidence (2026-08-26)

| Suite | Result | What it proves |
|-------|--------|----------------|
| `ProjectLifecycleTest` | **1 passed** | Planning → Start (`in_progress`) → Add Cost material 2×50000 = 100000 on `project_costs` and `projects.total_cost` |
| `SolarConvertTest` | **2 passed** | `/solar-proposals/new` shows Site Info; accepted proposal with selected BOM converts; quotation total > 0 |
| `SolarProposalApiTest` convert | **passed** | Response has top-level `quotation.id` (not wrapped Resource `data`) |

## Root causes fixed

1. `src/api/addons/solar/useSolarProposals.ts` imported `./client` (file lives two directories down). Vite overlay blocked the wizard. Same for electrical-panel `useComponentStandards` / `useSpecRuleSets`. Now `@/api/client`.
2. `ProjectCostModal` bound `v-model="values.description"` so vee-validate never saw Playwright fills (`Description is required` with text visible). `defineField()`.
3. `convertToQuotation` returned `new QuotationResource` inside `response()->json()`, so the SPA read `quotation.id` as undefined. `->resolve()`.

## Tenant

```
FEATURE_PRESET=general
FEATURE_PROJECTS=true
FEATURE_SOLAR_PROPOSALS=true
```

(Plus existing POS + manufacturing pack flags.) Do not use `FEATURE_PRESET=solar` if trading/POS must stay on.

## Out of this Go

Public solar accept page, electrical_panel / Vahana, project tasks deep SPA.
