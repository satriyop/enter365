# Pre-feature stability audit — tech debt, architecture, code smells

**Date:** 2026-08-12  
**Status:** open (snapshot — do not treat as forever-green)  
**Objective:** Document what can break correctness, money/inventory, isolation, or agent/dev velocity **before** new feature work.  
**Method:** Greps + file size inventory + spot reads of shipped `app/`, `tests/`, FE `src/`; cross-check `.claude/skills/enter365` standards. PHPStan sample on InvoiceService clean.  
**Not in scope:** Implementing fixes (→ `tasks/backlog/`).

---

## Executive summary

The app is **architecturally mature in core domains** (service layer, state machines on money documents, recent industry isolation A–L3 + feature-set journey tests). It is **not** “clean bill of health” pre-feature:

| Area | Verdict |
|------|---------|
| Industry isolation (core Manufacturing vs Vahana/NEX) | **Mostly healthy** after residual polish |
| Money-document cascades (Invoice void, etc.) | **Strong pattern** when services are used |
| God services / fat modules | **Still present** on critical paths |
| Controllers doing transactions / Eloquent writes | **Real debt** (follow-up, reminders, NSFP, budget edges) |
| OperationContext / `auth()->id()` | **Mostly fixed**; 2 service leftovers |
| Model service-locator `app()` | **Residual** on calculateTotals |
| FE branding / package name / core page soft-gates | **Cosmetic + residual coupling** |
| Browser E2E for MFG / solar / panel | **Gap** (feature API journeys exist; SPA E2E does not) |
| Manufacturing costing strategies | **JE strategies exist but unwired; default `project_based` posts no JE** |

**Recommendation:** Fix P0–P1 money-path and service-layer leaks before large new features; treat P2–P3 as parallel hygiene.

---

## Healthy baseline (do not regress)

Evidence of standards that already hold:

1. **Invoice void cascade** stays in service + transaction with DO reverse / SR cancel / payment void (`app/Services/Sales/InvoiceService.php` ~274–396). Matches Claude.md “never reverse accounting side effects outside service.”
2. **Industry package isolation** — core Manufacturing HTTP greps clean of `ElectricalPanel` / `component_standard`; add-ons under `app/Services/ElectricalPanel`, `routes/addons/*`, `AddonExtensions`. Locked by `tests/Feature/IndustryIsolationTest.php` + feature-set suite.
3. **Feature-set seed + API journeys** — `tests/Feature/Seeders/DemoSeederOrchestrationTest.php`, `tests/Feature/FeatureSets/*` (general→full).
4. **Domain factories / BaseService + traits** documented and largely adopted (skills).
5. **BrandSwap** correctly under ElectricalPanel (skills updated; not under Manufacturing).

---

## Findings (ranked)

Severity legend:

| Severity | Meaning |
|----------|---------|
| **P0** | Can corrupt money/stock/permissions or ship wrong product posture |
| **P1** | High risk of bugs, dual paths, or silent wrong behavior under load/agents |
| **P2** | Maintainability / agent confusion / inconsistency |
| **P3** | Cleanup, naming, optional polish |

### P0 — Correctness / money / inventory

#### F-01 · Controllers still own transactions and writes (bypass service contracts)

**Why it hurts:** Side effects, logging, OperationContext, and future cascade rules diverge from service layer; easy to ship untested money-adjacent flows.

| Evidence | Pattern |
|----------|---------|
| `app/Http/Controllers/Api/V1/QuotationFollowUpController.php` | `DB::transaction` + `$quotation->activities()->create` + direct field updates (`storeActivity`, `scheduleFollowUp`, mark won/lost paths) |
| `app/Http/Controllers/Api/V1/PaymentReminderController.php` | `PaymentReminder::create([...])` inline (~122, ~188) |
| `app/Http/Controllers/Api/V1/NsfpRangeController.php` | `NsfpRange::create` / `update` on controller |
| `app/Http/Controllers/Api/V1/PurchaseOrderController.php` | `$purchaseOrder->delete()` after `isEditable()` check (~destroy) — skips service delete/hooks |
| `app/Http/Controllers/Api/V1/BomTemplateController.php` | Item `create`/`update`/`delete`/`reorder` + `toggleActive` largely on model, not full template service |

**Remediation:** Move to dedicated services (or existing follow-up/reminder services); controller = authorize + request + resource only.

---

#### F-02 · `auth()->id()` residual in services (breaks CLI/queue/test context)

**Why it hurts:** Skills forbid this; queues/import jobs get `null` creator; inconsistent with OperationContext.

| Path | Line / symbol |
|------|----------------|
| `app/Services/Shared/AttachmentService.php` | `'uploaded_by' => auth()->id()` |
| `app/Services/Accounting/BankImport/BankStatementImportService.php` | `'created_by' => auth()->id()` (~207) |

**Remediation:** Extend `BaseService` + `$this->getUserId()` (or inject OperationContext).

---

#### F-03 · Manufacturing cost strategies: default no-JE + lifecycle not wired

**Why it hurts (real residual risk):** Job/WIP JE implementations exist but **WO lifecycle never invokes them**. Default policy is **`project_based`**, a deliberate **no-journal stub** (costs expected in `project_costs`). Factories/shops on `job_costing` / `wip_accounting` get **zero strategy-driven JEs** until wired — false confidence from unit tests alone.

| Fact | Evidence |
|------|----------|
| Default config | `config/accounting.php` → `manufacturing_costing` default `project_based` via `env('ACCOUNTING_MFG_METHOD', 'project_based')` |
| Policy resolution | `AccountingPolicyManager::manufacturing()` defaults `'project_based'` |
| Default strategy behavior | `ProjectBasedCostingStrategy` — all hooks return `null`; `calculateTotalCost` returns `0` (comments: costs in `project_costs`) |
| JE strategies implemented | `JobCostingStrategy` (~118 LOC) posts inventory→WIP on consume, WIP→FG on complete; `WIPAccountingStrategy` (~124 LOC) similar full WIP path |
| Unit coverage (strategy only) | `tests/Unit/Services/Accounting/Strategies/Manufacturing/JobCostingStrategyTest.php` |
| **Not called from MFG services** | Grep of `app/Services/Manufacturing`: **no** `onMaterialConsumption` / `onWorkOrderComplete` / `onWorkOrderStart` / `manufacturing()` usage |
| WO cost helper | `WorkOrderCostService::updateProjectCosts` only recalculates project financials if `project_id` set — does **not** call `ManufacturingCostStrategy` |
| PHPStan exclude (**one file only**) | `phpstan.neon:29–30` excludes **only** `ProjectBasedCostingStrategy.php` with comment *“intentionally returns null (costs tracked in project_costs table)”* — **not** all Manufacturing strategies |

**Not the bug:** “JobCosting JE mapping incomplete” or “PHPStan excludes Manufacturing/*”.

**Remediation:**
1. Decide product default: keep `project_based` for EPC/solar **or** switch shops to `job_costing` / `wip_accounting` via config/demo presets.
2. **Wire** `AccountingPolicyManager::manufacturing()` into `WorkOrderMaterialService::consumeMaterials` and `WorkOrderService::complete` (and start if WIP needs open entries).
3. For `project_based`, ensure material costs actually land in `project_costs` on consume/complete (today strategy returns null — verify WO→project cost path is complete elsewhere).
4. Add **integration** tests: WO consume/complete with each method asserts JE presence/absence correctly.
5. Keep PHPStan exclude limited to intentional stub; document in audit not Claude.md lore.

---

### P1 — Architecture / dual paths / fat hotspots

#### F-04 · God-scale services on critical paths (skill threshold 500+ LOC)

**Why it hurts:** SRP, test setup cost, merge conflicts, agent mis-edits.

| LOC (approx) | Path |
|--------------|------|
| 713 | `app/Services/Accounting/JournalService.php` |
| 700 | `app/Services/Sales/DownPaymentService.php` |
| 699 | `app/Services/Accounting/Reports/FinancialReportService.php` |
| 596 | `app/Services/Shared/PaymentService.php` |
| 568 | `app/Services/Sales/DeliveryOrderService.php` |
| 561 | `app/Services/Sales/InvoiceService.php` |
| 554 | `app/Services/Accounting/YearEndCloseService.php` |
| 509 | `app/Services/Solar/SolarProposalService.php` |
| 502 | `app/Services/Manufacturing/SubcontractorService.php` |

**Note:** YearEndClose may be intentionally orchestrator-style (skill allows keep). Invoice/DO/Payment are better coordinator candidates (void/ship/pay already multi-phase).

**Remediation:** Split by write vs read / lifecycle vs report (Coordinator pattern already used for Quotation + BrandSwap).

---

#### F-05 · Fat controllers (reports, export, cross-ref, quotation)

| LOC | Path |
|-----|------|
| 706 | `app/Http/Controllers/Api/V1/ReportController.php` |
| 444 | `app/Http/Controllers/Api/V1/ExportController.php` |
| 422 | `app/Http/Controllers/Api/V1/QuotationController.php` |
| 419 | `app/Http/Controllers/Api/V1/ProjectController.php` |
| 409 | `app/Http/Controllers/Api/V1/ElectricalPanel/ComponentCrossReferenceController.php` |

**Remediation:** Report/export already partially in services — push remaining assembly out of HTTP; split panel cross-ref by read vs write.

---

#### F-06 · Model service locator `app(CalculatorInterface)` in totals

**Why it hurts:** Hidden DI; unit-test friction; skill “no app() in models” residual.

| Path |
|------|
| `app/Models/Sales/Invoice.php` `calculateTotals()` |
| `app/Models/Purchasing/Bill.php` |
| `app/Models/Sales/SalesReturn.php` |
| `app/Models/Purchasing/PurchaseOrder.php` |
| `app/Models/Purchasing/PurchaseReturn.php` |

**Remediation:** Prefer DomainFactory/service-only totals (Quotation path already moved); keep models read-only.

---

#### F-07 · God models still large

| LOC | Path |
|-----|------|
| 728 | `app/Models/Sales/Quotation.php` |
| 652 | `app/Models/Solar/SolarProposal.php` |
| 638 | `app/Models/Inventory/Product.php` |
| 543 | `app/Models/Purchasing/PurchaseOrder.php` |
| 529 | `app/Models/Manufacturing/WorkOrder.php` |

**Remediation:** Continue domain factory extraction; avoid new business methods on models.

---

#### F-08 · Notification listeners are no-ops (TODO)

**Why it hurts:** Product appears to “notify” but does nothing; silent UX/compliance gap.

| Path |
|------|
| `app/Infrastructure/Listeners/Sales/NotifyCustomerOnInvoiceSent.php` |
| `app/Infrastructure/Listeners/Sales/NotifyCustomerOnQuotationApproved.php` |
| `app/Infrastructure/Listeners/Sales/NotifySalesTeamOnQuotationSubmitted.php` |
| `app/Infrastructure/Listeners/Sales/NotifySalesTeamOnQuotationWon.php` |
| `app/Infrastructure/Listeners/Purchasing/NotifyAccountPayableOnBillReceived.php` |

**Remediation:** Implement or remove listeners + events until infrastructure exists (avoid false confidence).

---

### P2 — Isolation residuals, FE, agent confusion

#### F-09 · FE core BOM/template pages still soft-gate industry fields

**Why it hurts:** Agents re-introduce Vahana fields as “core BOM”; dual mental model.

| Evidence |
|----------|
| `front-end-enter365/src/pages/boms/BomDetailPage.vue`, `CreateBomFromTemplatePage.vue` |
| `front-end-enter365/src/pages/settings/bom-templates/*` (`features.enabled('electrical_panel')`, component standards) |
| Clients moved under `src/api/addons/*` but UI still mixed |

**Remediation:** Extract panel UI slots under `pages/addons/electrical-panel` (already partial).

---

#### F-10 · FE package/name debt

| Evidence |
|----------|
| `front-end-enter365/package.json` `"name": "solar-erp-frontend"` |
| Login rebranded to Enter365 (`LoginPage.vue`) — package name lags |
| ~110 `as any` / `: any` hits under `src/` |
| Debug `console.log` in `LoginPage.vue`, `stores/auth.ts` |

**Remediation:** Rename package; strip login debug logs; tighten types on hot paths.

---

#### F-11 · Browser E2E gap vs feature packs

| Evidence |
|----------|
| `tests/Browser/*` — sales/purchase/inventory/auth only |
| No browser coverage for BOM, WO, MRP, solar, electrical-panel UI |
| Backlog already notes: `tasks/backlog/002-browser-mfg-chain.md` |

**Remediation:** Smoke browser per FEATURE_PRESET (nav gate + one happy path) after API journeys (already present).

---

#### F-12 · Demo seeder complexity / vertical fragility

| Evidence |
|----------|
| Heavy vertical seeders (`ComponentLibrarySeeder` ~2.4k LOC; Vahana/Nex TX large) |
| Fixed mid-isolation: WO stock ensure in `DemoExtendedTransactionSeeder` (material stock) — still easy to re-break |
| Alternate-path seeders swallow some failures |

**Remediation:** Guard stock before any material consume; keep feature-set orchestration tests green as regression net.

---

### P3 — Hygiene

#### F-13 · Multi-tenant TODOs without implementation

- `app/Support/OperationContext.php` tenantId TODO  
- `app/Http/Middleware/BindOperationContext.php` resolveTenantId TODO  

Acceptable pre-prod; document as non-blocking.

#### F-14 · Deprecated model helpers still present

- `PurchaseOrder` receiving status deprecated method still on model  
- FiscalPeriodService deprecated close path  

Remove after callers verified gone.

#### F-15 · Skills vs code drift risk

Isolation skills were updated; keep `SERVICE_BINDINGS.md` / SKILL.md in sync when moving services (process debt, not a current BrandSwap path error).

---

## Standards cross-check

| Skill standard | Current state |
|----------------|---------------|
| No `auth()->id()` in services | **2 leftovers** (F-02) |
| Business logic not in controller | **Violated** follow-up/reminders/NSFP/PO delete/BOM template items (F-01) |
| No `app()` in models | **Residual** calculators (F-06) |
| God service split at 500+ LOC | **Several remain** (F-04) |
| Industry add-ons outside Manufacturing | **Healthy** for BrandSwap / HTTP namespaces |
| Accounting reverse only via document services | **Healthy** on Invoice void path when service used |
| Feature flags gate packs | **Healthy** + tests |

---

## Prioritized remediation backlog (for `tasks/backlog/`)

Suggested file names / order:

| Pri | Item | Outcome |
|-----|------|---------|
| 1 | Extract QuotationFollowUp + PaymentReminder + NsfpRange writes into services | Single transaction/logging path |
| 2 | Replace `auth()->id()` in AttachmentService + BankStatementImportService | Queue/import safe |
| 3 | PO/GRN/etc. destroy always via service (no direct `$model->delete()`) | Cascades/events preserved |
| 4 | BomTemplate item mutations via BomTemplateServiceInterface | AddonAttributes already there |
| 5 | Split PaymentService / JournalService / DownPaymentService (coordinator) | Testability |
| 6 | Wire ManufacturingCostStrategy into WO consume/complete; validate project_based → project_costs; integration tests per method | Real JEs when job_costing/wip on; honest default |
| 7 | Remove or implement notification listeners | Honest product behavior |
| 8 | FE: extract panel UI from core BOM pages; rename package; remove login console.log | Agent + brand hygiene |
| 9 | Browser smoke: MFG + solar + panel nav under presets | SPA stability signal |
| 10 | Model calculateTotals → service/factory only | DIP hygiene |

Existing related backlog: `tasks/backlog/001-assert-fg-stock-on-wo-complete.md`, `002-browser-mfg-chain.md`.

---

## What “stable enough for new features” means here

**OK to build on now** if:

- New features use **service layer + Form Requests + interfaces**  
- No new industry strings in core Manufacturing  
- Money mutations never call JE reverse outside document services  
- Feature-set isolation + DemoSeeder orchestration tests stay green  

**Block or carefully gate** features that touch:

- Invoice/DO/Payment/DP void paths (fat services, high risk)  
- Manufacturing costing wiring (strategy hooks not called from WO lifecycle) / WO complete stock  
- Controllers that still open `DB::transaction`  

---

## Evidence sources (this audit)

| Source | Location |
|--------|----------|
| Service/controller/model LOC ranking | workspace greps 2026-08-12 |
| Pattern greps (`auth()`, `app()`, TODOs, FE any) | same |
| Spot reads | QuotationFollowUpController, PaymentReminderController, InvoiceService void, AttachmentService, Invoice::calculateTotals, JobCostingStrategy, ProjectBasedCostingStrategy, WorkOrderCostService, config/accounting.php, phpstan.neon |
| Standards | `.claude/skills/enter365/CODE_REVIEW_ANTIPATTERNS.md`, `ARCHITECTURE_PATTERNS.md`, `SKILL.md` |
| Isolation history | `tasks/done/2026-08-11-*.md` |
| PHPStan | InvoiceService sample clean; exclude path is **only** `ProjectBasedCostingStrategy.php` |

---

## Appendix: size hotspots (snapshot)

Top services by lines: JournalService 713, DownPaymentService 700, FinancialReportService 699, PaymentService 596, DeliveryOrderService 568, InvoiceService 561.  
Top controllers: ReportController 706, ExportController 444, QuotationController 422.  
Top models: Quotation 728, SolarProposal 652, Product 638.
