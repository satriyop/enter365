---
status: open
date: 2026-08-11
updated: 2026-08-26
type: roadmap
source_audit: tasks/audit/2026-08-11-go-live-persona-matrix.md
phase_1: go
phase_2: go
phase_3: go
phase_4: go
phase_5: go
---

# Roadmap — Pre-Prod → Pilot Go-Live

Ordered by evidence gaps. Only promote a phase when **Strong** tests (DB side effects) pass.

## Phase 0 — Freeze truth (now)

- [x] New task system: `tasks/{roadmap,audit,backlog,done,artifact}`
- [x] Ignore legacy `plans/` in git
- [x] Persona matrix audit from code+tests
- [x] Keep `tasks/` as only planning surface in day-to-day work

## Phase 0b — Product packs (done 2026-08-11)

- [x] General company default; NEX/Vahana packs OFF (`FEATURE_PRESET=general`)
- [x] FE sidebar gates modules via GET /features

## Phase 1 — Trading pilot (Sales + Purchasing + Gudang dasar + Accounting dasar)

**Target personas:** Sales, Purchasing, Gudang, Accounting (core only)

- [x] One documented “happy path” script for pilot users (artifact) — `tasks/artifact/2026-08-25-trading-pilot-happy-path.md`
- [x] Ensure browser chain still green with SPA running (Invoice/Payment/DO/GRN/Inventory/Report) — 2026-08-25: 54 passed after live `FEATURE_PRESET=general` (was `pos`, which hid documents). Also PO/Quotation/Bill.
- [x] Fix any flake that blocks pilot (quotation valid_until already fixed 2026-08-11; preset `pos` skip/login-redirect on 2026-08-25)
- [x] Pilot go/no-go decision recorded under `tasks/done/` — **Go** `tasks/done/2026-08-25-phase-1-trading-pilot-go.md`

**Exit criteria:** Core **Go** flows only; no MFG/Solar required.

## Phase 2 — Prove manufacturing side effects

- [x] Assert FG `ProductStock` (and movements) on WO complete in integration test — `WorkOrderFinishedGoodsReceiptTest` (3 passed, 2026-08-26)
- [x] Assert WIP/FG JE if strategy expects journals — `WorkOrderManufacturingCostStrategyWiringTest` (job_costing Inventory→WIP / WIP→FG; project_based may skip JE)
- [x] Browser E2E: BOM → WO → MR issue → complete — `tests/Browser/ManufacturingChainTest.php` 1 passed / 34 assertions on live SPA (raw ↓ on MR issue, FG ↑ on complete, no second raw OUT)
- [x] Move backlog items to done when Strong — `tasks/done/2026-08-26-phase-2-manufacturing-go.md`

**Exit criteria:** Produksi Soft → near Go for panel shop pilot.

## Phase 3 — Accounting power features

- [x] Browser E2E bank recon: import/match/reconcile + book balance sanity — `BankReconciliationTest` 2 passed (match→unmatch→rematch→reconcile; report Book Balance)
- [x] Browser E2E budget create/compare (smoke + one strong number check) — comparison API now `data.comparison`/`budgeted`/`totals`; `BudgetCompareTest` asserts 12.345.000
- [x] Recurring generate smoke + DB document created — `RecurringGenerateTest` Generate Now creates invoice; `RecurringServiceTest` amounts

## Phase 4 — Project / Solar SPA proof

- [x] Browser project lifecycle + cost line — `ProjectLifecycleTest`: Start Project then Add Cost (qty×unit_cost persisted)
- [x] Browser solar wizard → convert to quotation — `SolarConvertTest`: wizard Site Info; accepted+BOM **Convert to Quotation** writes `quotations` + `converted_quotation_id`

## Phase 5 — Hardening

- [x] Replace native `confirm()`/`prompt()` on Stock Opname for pure UI E2E — `StockOpnameTest` 5 passed (create → generate → count → approve; stock +5)
- [x] Notification infrastructure or remove stub listeners from “ready” claims — listeners send mail; dispatch-through-Laravel test proves discovery
- [x] PPh product decision — **keep off** (`FEATURE_PPH_WITHHOLDING=false`); documented in `tasks/done/2026-08-26-phase-5-hardening-go.md`

## Out of roadmap (explicitly later)

- Full Odoo-parity (CRM, RFQ, serial/lot, customer portal)
- Multi-tenant
