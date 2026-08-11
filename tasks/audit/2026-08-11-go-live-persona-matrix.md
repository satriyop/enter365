---
status: done
date: 2026-08-11
type: audit
basis: code + tests (not plans/* docs)
---

# Go-Live Persona Matrix

Evidence-based readiness for end-user start→end flows.

## Method

1. Service/state machine methods under `app/`
2. FE hooks under `front-end-enter365/src/api/use*.ts` calling real API paths
3. Test quality:
   - **Strong** — DB asserts (`product_stocks`, JE lines, account codes, trial balance)
   - **Weak** — UI/status only, mocked inventory, hybrid API bypass UI
   - **None** — no browser E2E for that flow

## Legend

| Verdict | Meaning |
|---------|---------|
| **Go** | SPA start→end OK; side effects proven Strong |
| **Soft** | Pilot only; gaps (hybrid UI, missing FG assert, no browser) |
| **No-Go** | Do not rely for production end-user |

## Persona summary

| Persona | Overall | Why |
|---------|---------|-----|
| Sales | Soft → near Go | Strong browser + integration; email stubs |
| Purchasing | Soft → near Go | PO→GRN→Bill→Pay Strong |
| Gudang | Soft | Adjust/transfer Strong; opname hybrid |
| Accounting | Soft | Posting/TB Strong; bank recon/budget no browser |
| Produksi | Soft (API) | RM consume Strong; FG stock not asserted; 0 browser MFG |
| Project / Solar | Soft API / No-Go SPA proof | FE+API exist; 0 browser |
| Admin | Soft | Warehouse Strong; users thin |

## Backend chain proof (ran 2026-08-11)

`php artisan test tests/Feature/Integration/WorkflowIntegrationTest.php`  
→ **12 passed, 185 assertions** (real stock + JE + paid_amount, no inventory mock).

## Strong suites (trust for go-live decisions)

| Suite | Evidence |
|-------|----------|
| `tests/Feature/Integration/WorkflowIntegrationTest.php` | Full cycles stock/JE/paid |
| `tests/Browser/InvoiceTest.php` | AR line + trial balance |
| `tests/Browser/PaymentTest.php` | Cash/AR/AP + void reverse + TB |
| `tests/Browser/DeliveryOrderTest.php` | stock decrease + movement |
| `tests/Browser/GoodsReceiptNoteTest.php` | stock increase + movement |
| `tests/Browser/InventoryTest.php` | adjust/transfer stock deltas |
| `tests/Browser/ReportTest.php` | TB debit=credit; BS A vs L+E |

## Weak / do not over-trust

| Suite | Issue |
|-------|--------|
| Unit DO/GRN service tests | Often mock `InventoryService` |
| `WorkOrderCompletionPipelineTest` | Mock handler order only |
| `MrpServiceTest` | Thin CRUD/stats |
| `StockOpnameTest` browser | Hybrid API (native `confirm()`/`prompt()`) |
| `SmokeTest` | Page load only |
| MFG integration complete | Asserts RM consume; **does not assert FG ProductStock** |

## Critical product gaps (from code, not docs)

1. `FinishedGoodsHandler` exists; chain test does not assert FG stock-in.
2. Zero `tests/Browser` for WorkOrder / BOM / MRP / Project / Solar / Bank recon.
3. Notification listeners still `TODO` stubs.
4. `FEATURE_PPH_WITHHOLDING` defaults false.

## Detail flow verdicts

### Sales (Go where noted)

- Quotation → Invoice: **Go**
- Invoice post JE: **Go**
- Payment partial/full + void: **Go**
- DO ship stock down: **Go**
- Sales return stock+JE: **Go** (BE); Soft SPA deep JE
- Down payment: **Go**
- Multi-currency: **Soft**
- Email notif: **No-Go** (non-blocking for transaction)

### Purchasing

- PO workflow: **Go**
- GRN complete stock: **Go**
- Bill post + pay/void: **Go**
- Purchase return: **Go**
- One-click PO→Bill UI: **Soft** (manual form OK)

### Gudang

- Adjust / transfer: **Go**
- Opname: **Soft** (logic Strong service/integration; SPA pure UI Weak)

### Accounting

- CoA, manual JE, fiscal lock: **Go**
- TB/BS load + balance checks: **Go** (not full section math E2E)
- Bank recon / budget / recurring / year-end: **Soft** (service only)
- PPh: **No-Go** (flag off)

### Produksi

- BOM/WO/MR via API+FE hooks: **Soft**
- BOM→WO→MR→complete RM consume: **Soft** (integration Strong for RM)
- FG stock-in: **Soft/risk** (handler code, unasserted)
- SPA E2E production: **No-Go** proof

### Project / Solar / Admin

- Project + Solar: **Soft** API, **No-Go** SPA E2E proof  
- Warehouses browser: **Go**  
- Users/roles: **Soft**
