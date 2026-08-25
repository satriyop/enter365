---
status: open
date: 2026-08-11
updated: 2026-08-26
type: roadmap
source_audit: tasks/audit/2026-08-11-go-live-persona-matrix.md
phase_1: go
phase_2: go
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

- [ ] Browser E2E bank recon: import/match/reconcile + book balance sanity
- [ ] Browser E2E budget create/compare (smoke + one strong number check)
- [ ] Recurring generate smoke + DB document created

## Phase 4 — Project / Solar SPA proof

- [ ] Browser project lifecycle + cost line
- [ ] Browser solar wizard → convert to quotation (or public accept if product-critical)

## Phase 5 — Hardening

- [ ] Replace native `confirm()`/`prompt()` on Stock Opname for pure UI E2E
- [ ] Notification infrastructure or remove stub listeners from “ready” claims
- [ ] PPh product decision (enable + tests, or keep off and document)

## Out of roadmap (explicitly later)

- Full Odoo-parity (CRM, RFQ, serial/lot, customer portal)
- Multi-tenant
