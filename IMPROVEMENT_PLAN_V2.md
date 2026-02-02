# Enter365 — Top 5 High-Impact Improvements (V2)

## Executive Summary

After comprehensive codebase analysis (74 models, 101 services, 51 controllers, 84 domain events,
1,300+ tests, 422 PHPStan baseline errors), these are the **5 highest-impact improvements** that
affect stability, feature velocity, business alignment, and consistency — ordered by urgency.

**Key finding:** The previous improvement plan (V1) addressed strategy implementations, service
layer routing, test coverage, authorization policies, and event subscriber wiring. This V2 plan
targets the **next tier** of critical gaps discovered through deeper analysis.

---

## 1. Fix Race Conditions in Financial & Inventory Operations

**Impact:** CRITICAL | **Category:** Data Integrity / Stability
**Risk if ignored:** Concurrent operations corrupt stock levels, lose payment records, double-allocate materials.

### The Problem

Three core services perform read-modify-write operations **without row-level locking**, creating
classic race conditions that will cause data corruption under concurrent usage.

#### Race Condition A: Inventory Stock (InventoryService)

**File:** `app/Services/Inventory/InventoryService.php` — `stockIn()`, `stockOut()`, `adjust()`, `transfer()`

```
Thread A: reads quantity = 50
Thread B: reads quantity = 50  (same time)
Thread A: adds 10 → saves quantity = 60
Thread B: adds 20 → saves quantity = 70  (OVERWRITES Thread A)
Result: Lost 10 units from Thread A
```

The `ProductStock::addStock()` method (`app/Models/Inventory/ProductStock.php:69-86`) performs
a read-modify-write cycle on `quantity` and `average_cost` without any locking.

#### Race Condition B: Payment Allocation (PaymentService)

**File:** `app/Services/Shared/PaymentService.php:259-277`

```
Payment A: reads Invoice(paid_amount = 0, total = 1000)
Payment B: reads Invoice(paid_amount = 0, total = 1000)
Payment A: adds 500 → saves paid_amount = 500
Payment B: adds 500 → saves paid_amount = 500 (OVERWRITES)
Result: Customer paid 1000 but system shows 500
```

#### Race Condition C: Material Reservation (WorkOrderMaterialService)

**File:** `app/Services/Manufacturing/WorkOrderMaterialService.php:45-65`

```
WO-A: reads reserved_quantity = 0, available = 100
WO-B: reads reserved_quantity = 0, available = 100
WO-A: reserves 100 units
WO-B: reserves 100 units (OVERWRITES)
Result: 200 units reserved but reserved_quantity = 100
```

#### Secondary Issue: reserved_quantity Type Mismatch

Migration defines `reserved_quantity` as `decimal(12, 4)` but model casts to `integer` —
fractional quantities silently truncated.

### Why This Is #1

An ERP's most fundamental guarantee is **data accuracy**. If stock levels, payment records,
or material allocations can be corrupted by concurrent operations, every downstream report,
journal entry, and business decision is unreliable. This is a pre-production blocker.

### Definition of Done

- [ ] `lockForUpdate()` added to InventoryService (stockIn, stockOut, adjust, transfer)
- [ ] `lockForUpdate()` added to PaymentService (updatePayableAfterPayment)
- [ ] `lockForUpdate()` added to WorkOrderMaterialService (reserveMaterials, releaseMaterials, consumeMaterials)
- [ ] `reserved_quantity` type mismatch resolved (align migration and model cast)
- [ ] Document number locks enabled in all environments (not just production)
- [ ] Concurrency tests written using parallel database transactions
- [ ] Existing tests still pass

---

## 2. Wire Audit Trail + Complete Domain Event Dispatching

**Impact:** HIGH | **Category:** Business Alignment / Compliance
**Risk if ignored:** No change history for financial records. Inventory movements fire into void. No compliance audit trail.

### The Problem

Two related gaps compound each other:

#### Gap A: AuditLog Infrastructure Exists But Is Never Used

The `AuditLog` model (`app/Models/Core/AuditLog.php`) is well-designed with polymorphic
relationships, before/after state capture, user tracking, and an ADR spec (`docs/08-adr/0041-audit-trail.md`).

**But no service ever calls it.** Zero `AuditLog::log()` calls exist in the entire codebase.

| What ADR Says to Log | Currently Logged? |
|----------------------|-------------------|
| Invoice created/approved/voided | No |
| Payment received/voided | No |
| Journal entry posted/reversed | No |
| Fiscal period closed/reopened | No |
| User login/permission change | No |
| Stock adjustment | No |

#### Gap B: 5 Services Don't Dispatch Domain Events

These services perform state-changing operations but don't dispatch the domain events
already defined for them:

| Service | Missing Events | File |
|---------|---------------|------|
| **InventoryService** | InventoryReceived, InventoryIssued, InventoryAdjusted, InventoryTransferred | `app/Services/Inventory/InventoryService.php` |
| **DeliveryOrderService** | DeliveryOrderConfirmed, DeliveryOrderShipped, DeliveryOrderDelivered, DeliveryOrderCancelled | `app/Services/Sales/DeliveryOrderService.php` |
| **ReminderService** | No reminder events defined | `app/Services/Sales/ReminderService.php` |
| **OverdueService** | InvoiceOverdue (partially) | `app/Services/Sales/OverdueService.php` |

Additionally, 3 services bypass the DomainFactory pattern (calling `$model->stateMachine()`
directly instead of through injected factory):
- DeliveryOrderService
- SalesReturnService
- GoodsReceiptNoteService

### Why This Is #2

For a financial application, audit compliance is non-negotiable. The infrastructure is already
built — it just needs to be wired in. And domain events not firing means the event subscriber
system (15 subscribers, all with tests) is partially disconnected from actual operations.

### Definition of Done

- [ ] Create `Auditable` trait that hooks into model events (creating, updating, deleting)
- [ ] Apply `Auditable` trait to all financial models (Invoice, Bill, Payment, JournalEntry, FiscalPeriod)
- [ ] Add `AuditLog::log()` calls in critical service actions (approve, void, post, reverse, close)
- [ ] InventoryService dispatches all 4 inventory events
- [ ] DeliveryOrderService dispatches all 4 delivery events
- [ ] Create DomainFactory for DeliveryOrder, SalesReturn, GoodsReceiptNote
- [ ] Write tests for audit logging and event dispatching
- [ ] Verify all 15 event subscribers receive events correctly

---

## 3. Add Background Job Infrastructure & Scheduled Tasks

**Impact:** HIGH | **Category:** Stability / Scalability
**Risk if ignored:** Heavy operations timeout in HTTP requests. No automated recurring invoices. PDF generation blocks users.

### The Problem

The application has **zero background jobs** (`app/Jobs/` directory doesn't exist) and
**zero scheduled tasks**. Only 2 notification classes implement `ShouldQueue`.

#### Operations That Will Timeout in Production

| Operation | Current Behavior | Risk |
|-----------|-----------------|------|
| **PDF generation** (dompdf installed) | Blocks HTTP request | Gateway timeout on large invoices |
| **Excel export** (maatwebsite/excel) | User waits 30+ seconds | Bad UX, potential timeout |
| **Fiscal period closing** | Processes thousands of entries synchronously | HTTP timeout |
| **MRP calculation** | Calculates entire product catalog | Long-running request |
| **Bulk import** (component mappings) | Synchronous processing | Timeout on large files |
| **Report generation** | Aggregates 1000+ records per request | Slow dashboard |

#### Missing Scheduled Tasks

| Task | Frequency Needed | Current State |
|------|-----------------|---------------|
| Send payment reminders | Daily | Manual only |
| Mark invoices as overdue | Daily | Manual only |
| Generate recurring invoices | Daily | Not automated |
| Queue clean-up | Daily | Not configured |
| Cache warm-up | Hourly | No caching exists |

#### Missing Email Infrastructure

The `SendInvoicePaidNotification` listener references `emails.payment-confirmation` Blade
template that **does not exist** — will throw exception at runtime.

### Why This Is #3

Without background jobs, any operation involving PDF generation, bulk data processing, or
complex calculations will timeout under production load. Scheduled tasks are essential for
automated billing workflows (recurring invoices, payment reminders, overdue marking).

### Definition of Done

- [ ] Create job classes: GenerateDocumentPdf, GenerateExcelExport, CloseFiscalPeriod, RunMrpCalculation, ProcessBulkImport
- [ ] Convert ReminderService to dispatch jobs instead of processing inline
- [ ] Create scheduled commands: SendDueReminders, MarkOverdueInvoices, GenerateRecurringInvoices
- [ ] Register schedules in `routes/console.php`
- [ ] Create missing email templates (payment-confirmation at minimum)
- [ ] Add Laravel Horizon or queue monitoring
- [ ] Write tests for all jobs and scheduled commands

---

## 4. Standardize API Response Patterns & Add Missing Authorization

**Impact:** HIGH | **Category:** Consistency / Security / Feature Velocity
**Risk if ignored:** Frontend must handle 4 different response formats. Dashboard/reports accessible to any authenticated user.

### The Problem

#### Problem A: 4 Different Response Patterns

| Pattern | Used By | Structure |
|---------|---------|-----------|
| **Base Controller helpers** | InvoiceController, QuotationController | `{success, message, data}` |
| **Direct response()->json()** | AccountController, BudgetController, InventoryController | `{message}` or `{data}` |
| **API Resources** | Most controllers | `{data: {…}}` |
| **Raw arrays** | DashboardController, ReportController | `{report_name, accounts, …}` |

36 controller methods across 12+ controllers use `response()->json()` directly instead
of the base controller helpers or API Resources.

#### Problem B: Missing Authorization on Sensitive Controllers

| Controller | What It Exposes | Authorization |
|-----------|----------------|---------------|
| **DashboardController** | Financial summaries, revenue, expenses | None |
| **ReportController** | Trial balance, P&L, balance sheet, tax reports | None |
| **ExportController** | Full data exports (Excel/PDF) | None |

Any authenticated user can access all financial reports and dashboards.

#### Problem C: Inconsistent Pagination Defaults

Controllers use different default `per_page` values: 15, 25, or 50, creating
unpredictable UX across the frontend.

#### Problem D: Missing FormRequest Classes

3 controllers use inline `$request->validate()`:
- ComponentCrossReferenceController (4 methods)
- SolarDataController (2 methods)
- ComponentMappingImportController (1 method)

### Why This Is #4

The frontend Vue SPA consumes these APIs via TypeScript types generated from `api.json`.
Inconsistent response structures break type generation and force the frontend to handle
multiple formats. Missing authorization is a security gap. Both issues multiply as more
features are added.

### Definition of Done

- [ ] Create DashboardPolicy, ReportPolicy with `reports.financial`, `reports.tax`, etc. permissions
- [ ] Add `$this->authorize()` calls to DashboardController, ReportController, ExportController
- [ ] Refactor all `response()->json()` calls to use base controller helpers or API Resources
- [ ] Create API Resources for Dashboard and Report responses
- [ ] Standardize pagination default to 25 across all controllers (via config)
- [ ] Create FormRequest classes for ComponentCrossReference, SolarData, ComponentMappingImport
- [ ] Run `php check-api-mismatches.php` and fix all Resource ↔ Schema drift
- [ ] Regenerate `api.json` and frontend types

---

## 5. Add Caching Strategy & Service Layer Test Coverage Expansion

**Impact:** MEDIUM-HIGH | **Category:** Stability / Performance
**Risk if ignored:** Dashboard recalculates everything per request. 74% of services untested. Regressions in business logic undetected.

### The Problem

#### Problem A: Zero Caching (Grade F)

No service uses `Cache::remember()`, `Cache::rememberForever()`, or any caching pattern.

```bash
grep -r "cache(\|Cache::\|remember(" app/Services/  # Zero results
```

**Impact on specific operations:**

| Operation | Current Behavior | Expected With Cache |
|-----------|-----------------|-------------------|
| Dashboard stats | 6 aggregate queries per load | Cached 10 min, ~0 queries |
| Contact outstanding | Recalculated per list item | Cached per contact, invalidated on payment |
| Account chart | Full query per dropdown | Forever cache, invalidated on change |
| Tax rate lookup | Config read per calculation | Request-level cache |
| Report generation | Full recalculation per view | Cached with tag-based invalidation |

#### Problem B: Service Layer Test Coverage at 26%

75 out of 101 services have no dedicated tests. Critical untested services:

| Priority | Untested Services | Risk |
|----------|------------------|------|
| P0 | QuotationWorkflowService, QuotationCrudService, QuotationConversionService | State machine orchestration untested |
| P0 | BrandSwapPreviewService, BrandSwapExecutionService | Cost calculations untested |
| P0 | WorkOrderCostService, WorkOrderMaterialService | Manufacturing cost logic untested |
| P1 | All 5 Report Services (Tax, CashFlow, COGS, Financial, BankRecon) | Report accuracy untested |
| P1 | NumberGenerationManager, DashboardQueryService | Core infrastructure untested |
| P1 | BillService, SubcontractorService | Purchasing/manufacturing workflows |
| P2 | ProjectService, SolarProposalService | Domain-specific logic |

#### Missing Integration Tests

No end-to-end workflow tests exist for:
- PurchaseOrder → GoodsReceiptNote → Bill → Payment (purchasing cycle)
- BOM → WorkOrder → MaterialRequisition → Completion (manufacturing cycle)
- Quotation → Invoice → DeliveryOrder → Payment (full sales cycle)
- StockOpname → InventoryAdjustment → Journal Entry (inventory audit)

### Why This Is #5

Performance without caching degrades linearly with data volume. And with 74% of services
untested, every new feature risks breaking existing business logic without detection. Both
issues compound: the more features you add, the slower and more fragile the system becomes.

### Definition of Done

- [ ] Implement request-level caching in BaseService (`private $cache = []`)
- [ ] Add TTL caching to DashboardQueryService (10 min), QuotationStatisticsService (10 min)
- [ ] Add forever caching for Account chart, tax rates, company settings (with invalidation)
- [ ] Add tag-based cache invalidation on model save events
- [ ] Write P0 service tests (~15 new test files)
- [ ] Write 4 end-to-end workflow integration tests
- [ ] Write P1 report service tests (~5 new test files)
- [ ] Reduce PHPStan baseline by fixing top 50 critical errors

---

## Phased Implementation Plan

### Phase 1: Data Integrity (Item #1)

**Goal:** Eliminate race conditions before any production usage.

```
├── Add lockForUpdate() to InventoryService (4 methods)
├── Add lockForUpdate() to PaymentService (1 method)
├── Add lockForUpdate() to WorkOrderMaterialService (3 methods)
├── Fix reserved_quantity type mismatch
├── Enable document number locks in all environments
├── Write concurrency tests
└── Run full test suite — verify no regressions
```

**Deliverables:**
- 8 methods patched with row-level locking
- 1 migration or model fix for type mismatch
- ~10 new concurrency tests
- Zero data corruption risk

---

### Phase 2: Audit Trail + Event Wiring (Item #2)

**Goal:** Every financial operation is tracked and events fire correctly.

```
├── Create Auditable trait with model event hooks
├── Apply Auditable to: Invoice, Bill, Payment, JournalEntry, FiscalPeriod
├── Wire AuditLog::log() into critical service actions (approve, void, post, reverse)
├── Add event dispatching to InventoryService (4 events)
├── Add event dispatching to DeliveryOrderService (4 events)
├── Create DomainFactory for DeliveryOrder, SalesReturn, GoodsReceiptNote
├── Write audit trail tests
└── Verify event subscribers receive dispatched events
```

**Deliverables:**
- Auditable trait applied to 5+ core models
- 8 missing event dispatches wired
- 3 new DomainFactory classes
- ~30 new tests
- Complete audit trail for financial operations

---

### Phase 3: Background Jobs & Scheduling (Item #3)

**Goal:** Heavy operations don't block HTTP requests. Automated workflows run on schedule.

```
├── Create job classes:
│   ├── GenerateDocumentPdf
│   ├── GenerateExcelExport
│   ├── CloseFiscalPeriod
│   ├── RunMrpCalculation
│   └── ProcessBulkImport
├── Create scheduled commands:
│   ├── SendDueReminders (daily)
│   ├── MarkOverdueInvoices (daily)
│   └── GenerateRecurringInvoices (daily)
├── Register schedules in routes/console.php
├── Create missing email templates
├── Write job and command tests
└── Run full test suite
```

**Deliverables:**
- 5 job classes
- 3 scheduled commands
- Email templates for payment confirmations
- ~20 new tests
- No more HTTP timeouts on heavy operations

---

### Phase 4: API Standardization (Item #4)

**Goal:** All API responses follow one pattern. All endpoints authorized.

```
├── Create DashboardPolicy, ReportPolicy, ExportPolicy
├── Add authorization to Dashboard/Report/Export controllers
├── Refactor 36 response()->json() calls to use base helpers
├── Create API Resources for Dashboard and Report responses
├── Standardize pagination defaults via config
├── Create 7 missing FormRequest classes
├── Run check-api-mismatches.php and fix drift
├── Regenerate api.json
└── Run full API test suite
```

**Deliverables:**
- 3 new policies
- Consistent API response format (single pattern)
- 7 new FormRequest classes
- Updated api.json
- Full authorization coverage

---

### Phase 5: Caching + Test Expansion (Item #5)

**Goal:** Performance doesn't degrade with data volume. Service layer tested.

```
├── Implement request-level caching in BaseService
├── Add TTL caching to Dashboard and Statistics services
├── Add forever caching for configuration data
├── Implement cache invalidation on model events
├── Write P0 service tests (QuotationWorkflow, BrandSwap, WorkOrderCost)
├── Write 4 end-to-end workflow integration tests
├── Write P1 report service tests
├── Fix top 50 PHPStan baseline errors
└── Run full test suite
```

**Deliverables:**
- Caching layer across all read-heavy services
- ~20 new service test files
- 4 integration workflow tests
- PHPStan baseline reduced from 422 to ~370
- Service test coverage: 26% → 45%+

---

## Progress Tracking

| Phase | Items | Status | Tests Added |
|-------|-------|--------|-------------|
| Phase 1 | #1 (Race Conditions) | ✅ Complete | 13 |
| Phase 2 | #2 (Audit + Events) | ✅ Complete | 23 |
| Phase 3 | #3 (Jobs + Scheduling) | ✅ Complete | 48 |
| Phase 4 | #4 (API Standards) | ✅ Complete | 28 |
| Phase 5 | #5 (Caching + Tests) | ⬜ Not Started | ~40 |
| **Total** | | | **112 + ~40 remaining** |

---

## What This Enables

After all 5 phases:

| Capability | Before | After |
|-----------|--------|-------|
| Data integrity | Race conditions in 8 methods | Row-level locking everywhere |
| Audit compliance | Zero change tracking | Full audit trail on financial models |
| Scalability | Everything synchronous | Heavy ops queued, recurring automated |
| API consistency | 4 response formats, gaps in auth | Single format, full authorization |
| Performance | No caching, everything recalculated | Intelligent caching, sub-second dashboards |
| Test coverage | 26% services tested | 45%+ services, 4 workflow integration tests |
