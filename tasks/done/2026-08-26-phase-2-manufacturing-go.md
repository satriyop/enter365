---
status: done
date: 2026-08-26
type: done
phase: 2
verdict: Go
persona: Produksi
---

# Phase 2 manufacturing — Go

**Verdict: Go** for generic shop floor on a **trading + manufacturing pack** tenant.  
Not a Vahana/electrical_panel go. Not MRP/subcontracting SPA proof.

## Evidence (2026-08-26)

| Suite | Result | What it proves |
|-------|--------|----------------|
| `WorkOrderFinishedGoodsReceiptTest` | **3 passed**, 18 assertions | WO complete increases FG `product_stocks`; `TYPE_PRODUCTION` movement; scrap netted; skip without warehouse |
| `WorkOrderManufacturingCostStrategyWiringTest` | **6 passed**, 34 assertions | `job_costing`: Inventory→WIP on consume, WIP→FG on complete, no double-count; `wip_accounting` posts; default `project_based` may **not** journal |
| `ManufacturingChainTest` (live SPA + `akuntansi`) | **1 passed**, 34 assertions | BOM → create WO (API) → Confirm → Start → Request Materials → Approve MR → Issue (raw stock ↓) → Complete (FG ↑). No second raw OUT on complete |

## Tenant that this Go assumes

```
FEATURE_PRESET=general
FEATURE_POS=true
FEATURE_MANUFACTURING=true
FEATURE_BOM=true
FEATURE_WORK_ORDERS=true
FEATURE_MATERIAL_REQUISITIONS=true
```

Do **not** switch to `FEATURE_PRESET=pos` (hides trading). Do **not** require `FEATURE_PRESET=manufacturing` (defaults POS off). Pack overrides on `general`. Skill #52.

## Stock invariant (do not regress)

- Raw materials leave stock on **MR issue**, movement reference = MaterialRequisition.
- WO complete does **not** issue the same raw again.
- FG receipt is `FinishedGoodsHandler` on complete: quantity = ordered − scrapped; movement type `production`.

## Out of this Go

MRP explosion, subcontracting, electrical_panel / Vahana, Solar. Phase 3 bank recon / budget / recurring.
