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

#### F-01 · Controllers still own transactions and writes (bypass service contracts) — **mostly FIXED 2026-08-12**

**Why it hurts:** Side effects, logging, OperationContext, and future cascade rules diverge from service layer; easy to ship untested money-adjacent flows.

| Evidence | Status |
|----------|--------|
| `QuotationFollowUpController` | **Fixed** — storeActivity / scheduleFollowUpWithNotes / assignTo / updatePriority / markAsWon|Lost via services (no `DB::transaction` / `activities()->create`) |
| `PaymentReminderController` | **Fixed** — create/send/cancel via `ReminderService` |
| `NsfpRangeController` | **Fixed** — createRange / updateRange / deactivateRange via `NsfpService` |
| `PurchaseOrderController` destroy | **Fixed** — via `PurchaseOrderService::delete` |
| `BomTemplateController` item mutations | **Fixed 2026-08-12** — addItem / updateItem / deleteItem / reorderItems / toggleActive via `BomTemplateServiceInterface` + shared `ManagesBomTemplateItems` trait (core + ElectricalPanel) |

**F-01 closed** for listed controllers. Thumbnail file IO still lives in controller (storage concern).

---

#### F-02 · `auth()->id()` residual in services — **FIXED 2026-08-12**

**Was:** AttachmentService / BankStatementImportService used `auth()->id()`.

**Fixed:** Both use `WithOperationContext` + `getUserId()` (regression in `StabilityHotfixRegressionTest`).

---

#### F-03 · Manufacturing cost strategies: default no-JE + lifecycle not wired — **FIXED 2026-08-12**

**Was:** Job/WIP JE implementations existed but WO lifecycle never invoked them.

**Fixed:**
| Path | Behavior |
|------|----------|
| `WorkOrderMaterialService::consumeMaterials` | Creates `MaterialConsumption` for remaining qty + `manufacturing()->onMaterialConsumption()` |
| `WorkOrderMaterialService::recordConsumption` | Calls strategy after each consumption save |
| `WorkOrderService::start` | Calls `onWorkOrderStart` (no-op for all current methods) |
| `WorkOrderService::complete` | After transition, calls `onWorkOrderComplete`; project_based still uses `updateProjectCosts` when `project_id` set |
| CoA seeder | Added `1-1410` Persediaan Barang Jadi + `1-1450` WIP (referenced by strategies) |
| Integration tests | `WorkOrderManufacturingCostStrategyWiringTest` — project_based (no JE), job_costing (consume+complete JEs, no double-count), wip_accounting (posted JEs) |

**Residual (honest):**
- Default remains `project_based` (no JE) — intentional for EPC/solar.
- `project_based` does **not** create `ProjectCost` rows; it relies on existing `WorkOrderCostService::updateProjectCosts` → `project.calculateFinancials()`.
- Domain `WorkOrderCompletionPipeline` / `MaterialConsumptionHandler` still not used by `WorkOrderService` (service path is live).

---

### P1 — Architecture / dual paths / fat hotspots

#### F-04 · God-scale services on critical paths — **partially FIXED 2026-08-12**

**Why it hurts:** SRP, test setup cost, merge conflicts, agent mis-edits.

**Fixed (coordinator pattern, interfaces unchanged):**

| Coordinator | Lines | Focused services |
|-------------|------:|------------------|
| `PaymentService` | 128 | Creation (420), Void (162), Query (121) |
| `DownPaymentService` | 149 | Crud (188), Application (346), Lifecycle (235) |
| `JournalService` | 113 | Entry (207), Document (541) |
| `DeliveryOrderService` | 172 | Crud (280), Workflow (334) |
| `InvoiceService` | 142 | Crud (166), Posting (97), Void (232), PaymentStatus (181) |
| `FinancialReportService` | 160 | BS, IS, Equity, GL + AccountHierarchyBuilder |
| `SubcontractorService` | 183 | WorkOrder, Invoice, Query |
| `SolarProposalService` | 167 | Crud, Calculation, Workflow, Bom |

**Intentionally not split:**

| LOC (approx) | Path | Why |
|--------------|------|-----|
| ~554 | `YearEndCloseService.php` | Strategy-based orchestrator (skill allows keep) |

**F-04 god-service coordinator pass complete** for audit list.

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

#### F-06 · Model service locator `app(CalculatorInterface)` in totals — **FIXED 2026-08-12**

**Was:** Invoice/Bill/SR/PO/PR models called `app(CalculatorInterface)` inside `calculateTotals()`.

**Fixed:**
| Document | Totals path |
|----------|-------------|
| Invoice | `InvoiceDomainFactory::applyTotals` via `InvoiceCrudService::recalculateTotals` |
| Bill | new `BillDomainFactory` + `BillService` |
| PurchaseOrder | existing `PurchaseOrderDomainFactory` + `PurchaseOrderService` |
| SalesReturn | new `SalesReturnDomainFactory` + `SalesReturnService` |
| PurchaseReturn | new `PurchaseReturnDomainFactory` + `PurchaseReturnService` |

Model `calculateTotals()` removed from the five money documents. BOM still has simple model totals (no calculator locator).

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

#### F-08 · Notification listeners are no-ops (TODO) — **FIXED 2026-08-12**

**Was:** Auto-discovered listeners registered but empty TODOs.

**Fixed:** Real queued mail notifications + config flags:
| Event | Notification | Recipient |
|-------|--------------|-----------|
| InvoiceSent | `InvoiceSentNotification` | Contact email |
| QuotationApproved | `QuotationApprovedNotification` | Contact email |
| QuotationSubmitted | `QuotationSubmittedTeamNotification` | team email (`TeamNotifier`) |
| QuotationWon | `QuotationWonTeamNotification` | team email |
| BillReceived | `BillReceivedTeamNotification` | team email |

Config under `accounting.notifications.*` (per-event `enabled`, `channels`, optional `team_email`). Default team inbox: `NOTIFICATION_TEAM_EMAIL` / `COMPANY_EMAIL`. Channels use **mail only** (no `notifications` table yet).

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
| No `auth()->id()` in services | **Fixed** (F-02) |
| Business logic not in controller | **Fixed** for F-01 list |
| No `app()` in models | **Money docs fixed** (F-06); BOM totals still on model |
| God service split at 500+ LOC | **F-04 complete** (YearEndClose kept as orchestrator) |
| Industry add-ons outside Manufacturing | **Healthy** for BrandSwap / HTTP namespaces |
| Accounting reverse only via document services | **Healthy** on Invoice void path when service used |
| Feature flags gate packs | **Healthy** + tests |

---

## Prioritized remediation backlog (for `tasks/backlog/`)

Suggested file names / order:

| Pri | Item | Outcome |
|-----|------|---------|
| 1 | ~~Extract QuotationFollowUp + PaymentReminder + NsfpRange + BomTemplate item writes into services~~ **DONE 2026-08-12** | Single transaction/logging path |
| 2 | ~~Replace `auth()->id()` in AttachmentService + BankStatementImportService~~ **DONE** | Queue/import safe |
| 3 | ~~PO destroy via service~~ **DONE** | Cascades/events preserved |
| 4 | ~~BomTemplate item mutations via BomTemplateServiceInterface~~ **DONE 2026-08-12** | AddonAttributes already there |
| 5 | ~~Split PaymentService / JournalService / DownPaymentService (coordinator)~~ **DONE 2026-08-12** | Testability |
| 6 | ~~Wire ManufacturingCostStrategy into WO consume/complete~~ **DONE 2026-08-12** | Real JEs when job_costing/wip on; honest default |
| 7 | ~~Implement notification listeners~~ **DONE 2026-08-12** | Honest product behavior |
| 8 | FE: extract panel UI from core BOM pages; rename package; remove login console.log | Agent + brand hygiene |
| 9 | Browser smoke: MFG + solar + panel nav under presets | SPA stability signal |
| 10 | ~~Model calculateTotals → service/factory only~~ **DONE 2026-08-12** (money docs) | DIP hygiene |

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
