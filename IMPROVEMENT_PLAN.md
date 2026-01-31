# Enter365 - Top 5 High-Impact Improvements

## Executive Summary

After comprehensive codebase analysis (72 models, 61 services, 2,354 tests, 84 domain events),
these are the **5 highest-impact improvements** that affect stability, feature velocity,
business alignment, and consistency — ordered by urgency.

---

## 1. Complete Accounting Strategy Implementations

**Impact:** CRITICAL | **Category:** Business Alignment
**Risk if ignored:** Financial data will be incorrect in production. No journal entries for inventory, COGS, or manufacturing costs.

### The Problem

6 accounting strategies return `null` instead of creating journal entries:

| Strategy | Method | What's Missing |
|----------|--------|----------------|
| `PerpetualInventoryStrategy` | `onGoodsReceived()` | No Dr Inventory / Cr GRNI journal |
| `PerpetualInventoryStrategy` | `onGoodsShipped()` | No Dr COGS / Cr Inventory journal |
| `COGSOnDeliveryStrategy` | `onDeliveryShip()` | No COGS recognition on delivery |
| `JobCostingStrategy` | `onMaterialConsume()` | No WIP / Raw Materials journal |
| `JobCostingStrategy` | `onWorkOrderComplete()` | No FG / WIP journal |
| `WIPAccountingStrategy` | `onWorkOrderStart()` | Optional, but needed for full WIP tracking |

**Files:**
- `app/Services/Accounting/Strategies/Inventory/PerpetualInventoryStrategy.php`
- `app/Services/Accounting/Strategies/COGS/COGSOnDeliveryStrategy.php`
- `app/Services/Accounting/Strategies/Manufacturing/JobCostingStrategy.php`
- `app/Services/Accounting/Strategies/Manufacturing/WIPAccountingStrategy.php`

### Why This Is #1

The entire accounting module — the backbone of any ERP — has hollow implementations.
The strategy _pattern_ is correct, the interfaces exist, the wiring exists, but the
actual journal entries are never created. Every financial report downstream will be wrong.

### Definition of Done

- [ ] Each strategy method creates proper double-entry journal entries
- [ ] Account codes sourced from `AccountLookupService` (not hardcoded)
- [ ] Each strategy has dedicated unit tests
- [ ] Existing strategy tests updated to verify journal entry content
- [ ] PHPStan exclusions for these files removed from `phpstan.neon`

---

## 2. Enforce Service Layer Consistency (15 Controllers Bypass It)

**Impact:** HIGH | **Category:** Inconsistency / Feature Velocity
**Risk if ignored:** Business rules applied inconsistently. Events not fired. Harder to add cross-cutting concerns.

### The Problem

15 controllers call `Model::create()` / `Model::update()` / `Model::delete()` directly,
bypassing the service layer. This means:

- Domain events don't fire for these operations
- Business validation is scattered
- Adding a new cross-cutting concern (audit, notification, cache invalidation) requires touching 15+ files

**Controllers with direct model manipulation:**

| Controller | Operations | Complexity |
|------------|------------|------------|
| `UserController` | create, update, delete | Medium (password hashing, role sync) |
| `AccountController` | create, update, delete | Medium (chart of accounts integrity) |
| `ContactController` | create, update, delete | Medium (customer/vendor flags) |
| `WarehouseController` | create, update, delete | Low (simple CRUD) |
| `ProductCategoryController` | create, update, delete | Low |
| `RoleController` | create, update, delete | Medium (permission sync) |
| `BomTemplateController` | create, update, delete | Medium (template items) |
| `CompanyProfileController` | create/update | Low (singleton) |
| `FiscalPeriodController` | create, update | Medium (period validation) |
| `RecurringTemplateController` | create, update, delete | Medium (scheduling logic) |
| `BankReconciliationController` | create, update | Medium (reconciliation logic) |
| `AttachmentController` | create, delete | Low (file handling) |
| `ComponentStandardController` | create, update, delete | Low |
| `ComponentBrandMappingController` | create, update, delete | Low |
| `SpecValidationRuleSetController` | create, update, delete | Low |

**Also:** `DashboardController` and `QuotationFollowUpController` have raw `DB::table()` queries
that belong in repository/query service classes.

### Why This Is #2

Every new feature that touches these entities requires modifying controllers instead of
services. When you add audit logging, notifications, or multi-tenancy, you'll need to
retrofit 15+ controllers. The cost of fixing this grows with every new feature added.

### Definition of Done

- [ ] Each controller delegates to a corresponding service class
- [ ] Dashboard queries moved to `DashboardQueryService`
- [ ] Quotation follow-up stats moved to `QuotationStatisticsService`
- [ ] Controllers are thin: validate → authorize → delegate → respond
- [ ] Existing API tests still pass (no behavior change)

---

## 3. Service Layer Test Coverage (6% → 60%+)

**Impact:** HIGH | **Category:** Stability
**Risk if ignored:** Regressions in business logic go undetected. Refactoring becomes dangerous.

### The Problem

Only **6 out of 61 services** have dedicated test coverage (6%). While API tests provide
indirect coverage, they don't test:

- Edge cases in business logic
- Error handling paths
- Complex calculations (pricing, COGS, tax)
- State transitions triggered by services
- Cross-service interactions

**Most critical untested services:**

| Priority | Service | Risk |
|----------|---------|------|
| P0 | `JournalService` | Financial integrity |
| P0 | `PaymentService` | Money handling |
| P0 | `InvoicePaymentService` | Payment allocation |
| P0 | `AccountBalanceService` | Balance calculations |
| P0 | `FiscalPeriodService` | Period management |
| P1 | `DeliveryOrderService` | Inventory movements |
| P1 | `PurchaseOrderService` | Procurement workflow |
| P1 | `WorkOrderService` | Manufacturing flow |
| P1 | `BomService` | Bill of materials |
| P1 | `MrpService` | Material planning |
| P2 | `RecurringService` | Recurring invoices |
| P2 | `StockOpnameService` | Inventory auditing |
| P2 | All Report Services (8) | Report accuracy |

### Why This Is #3

With only 6% service test coverage, every change to business logic is a gamble.
The accounting strategies fix (Item #1) and the service layer refactor (Item #2)
both produce **untestable code** without service tests. This is the safety net
that makes everything else possible.

### Definition of Done

- [ ] P0 services: 80%+ method coverage with happy + error paths
- [ ] P1 services: Core methods tested with happy paths
- [ ] P2 services: At least one test per public method
- [ ] All accounting strategy tests verify actual journal entries
- [ ] Test factories updated where needed

---

## 4. Complete Authorization Policies for Transactional Models

**Impact:** HIGH | **Category:** Security / Business Alignment
**Risk if ignored:** Any authenticated user can create/edit/delete sensitive records.

### The Problem

16 policies exist, but **8 transactional models** that handle money, inventory, or
manufacturing lack authorization policies:

| Missing Policy | Domain | Why Critical |
|----------------|--------|-------------|
| `SalesReturnPolicy` | Sales | Controls refund approvals |
| `GoodsReceiptNotePolicy` | Purchasing | Controls inventory intake |
| `PurchaseReturnPolicy` | Purchasing | Controls vendor returns |
| `MaterialRequisitionPolicy` | Manufacturing | Controls material requests |
| `SubcontractorWorkOrderPolicy` | Manufacturing | Controls external work |
| `StockOpnamePolicy` | Inventory | Controls inventory adjustments |
| `FiscalPeriodPolicy` | Accounting | Controls period open/close |
| `WarehousePolicy` | Inventory | Controls warehouse config |

**Note:** Controllers currently call `$this->authorize()` but this will silently pass
or throw generic errors without proper policies.

### Why This Is #4

Authorization is a security boundary. Without policies, the permission system you've
already built (roles, permissions, gates) has holes. Any user with API access can
potentially approve returns, close fiscal periods, or adjust stock.

### Definition of Done

- [ ] Policy created for each model above
- [ ] Policies follow existing pattern (extend `BaseResourcePolicy`)
- [ ] Permission constants defined in permission seeder
- [ ] Each policy tested (authorized + unauthorized scenarios)
- [ ] Controllers verified to use correct policy methods

---

## 5. Wire Missing Event Subscribers for Cross-Domain Integration

**Impact:** MEDIUM-HIGH | **Category:** Business Alignment / Feature Readiness
**Risk if ignored:** Cross-domain side effects don't happen. Inventory movements don't trigger accounting. Period closures don't cascade.

### The Problem

84 domain events exist, but only 10 subscribers handle them. Key domains have
events that fire into the void:

| Domain | Events That Fire | Subscriber Exists? | Side Effects Missing |
|--------|-----------------|-------------------|---------------------|
| Inventory | InventoryReceived, InventoryIssued, InventoryTransferred, InventoryAdjusted | NO | Accounting journals, stock alerts |
| Accounting | FiscalPeriodOpened, FiscalPeriodClosed | NO | Period-end tasks, report generation |
| Purchasing/GRN | GoodsReceiptNoteCompleted | NO | Inventory update confirmation, accounting |
| Purchasing/Return | PurchaseReturn events (6) | NO | Inventory reversal, vendor credit |
| Inventory/StockOpname | StockOpnameCompleted | NO | Inventory adjustment journals |

**Additionally:** 11 notification listeners are stubs (return without doing anything):
- `NotifySalesTeamOnQuotationWon`
- `NotifyCustomerOnInvoiceSent`
- `NotifyAccountPayableOnBillReceived`
- etc.

### Why This Is #5

The event-driven architecture is well-designed but incompletely wired. When a GRN is
completed, inventory should update and an accounting journal should be created — but
currently nothing happens. This is the "last mile" of your domain architecture.

### Definition of Done

- [ ] InventoryEventSubscriber handles all 4 inventory events
- [ ] FiscalPeriodEventSubscriber handles period open/close
- [ ] GoodsReceiptNote events trigger inventory + accounting
- [ ] PurchaseReturn events trigger inventory reversal
- [ ] StockOpname completion triggers adjustment journals
- [ ] Each subscriber has dedicated tests
- [ ] Notification listeners stubbed with TODO for email infrastructure

---

## Phased Implementation Plan

### Phase 1: Financial Integrity (Items #1 + #3 partial)

**Goal:** Make accounting produce correct numbers.

```
Week A:
├── Complete PerpetualInventoryStrategy (onGoodsReceived + onGoodsShipped)
├── Complete COGSOnDeliveryStrategy (onDeliveryShip)
├── Write unit tests for both strategies
└── Remove PHPStan exclusions, fix any type errors

Week B:
├── Complete JobCostingStrategy (onMaterialConsume + onWorkOrderComplete)
├── Complete WIPAccountingStrategy (onWorkOrderStart)
├── Write unit tests for both strategies
├── Write integration tests: GRN → Inventory Journal → Balance Check
└── Write integration tests: DO → COGS Journal → P&L Check
```

**Deliverables:**
- 6 strategy methods implemented
- ~30 new tests
- PHPStan clean on strategy files
- Financial reports produce correct numbers

---

### Phase 2: Architectural Consistency (Item #2)

**Goal:** All mutations go through service layer. Controllers are thin.

```
Week C:
├── Create services for simple CRUD controllers (7 controllers):
│   ├── ProductCategoryService
│   ├── WarehouseService
│   ├── ComponentStandardService
│   ├── ComponentBrandMappingService
│   ├── SpecValidationRuleSetService
│   ├── AttachmentService
│   └── CompanyProfileService
├── Refactor controllers to delegate to services
└── Verify all API tests pass

Week D:
├── Create services for medium-complexity controllers (6 controllers):
│   ├── UserService (password hashing, role sync)
│   ├── AccountService (chart of accounts)
│   ├── ContactService (customer/vendor)
│   ├── RoleService (permission sync)
│   ├── RecurringTemplateService
│   └── BankReconciliationService
├── Create DashboardQueryService (move 6 raw queries)
├── Create QuotationStatisticsService (move 3 raw queries)
└── Verify all API tests pass
```

**Deliverables:**
- 15 new service classes
- 2 new query service classes
- All controllers thin (validate → authorize → delegate → respond)
- Zero behavior change (API tests unchanged)

---

### Phase 3: Test Safety Net (Item #3)

**Goal:** Service layer test coverage from 6% to 60%+.

```
Week E (P0 - Financial Services):
├── JournalService tests (create, reverse, void)
├── PaymentService tests (allocate, overpayment, partial)
├── InvoicePaymentService tests (apply, unapply)
├── AccountBalanceService tests (trial balance, period balance)
├── FiscalPeriodService tests (open, close, reopen)
└── AccountingPolicyManager tests (strategy selection)

Week F (P1 - Core Workflow Services):
├── DeliveryOrderService tests
├── PurchaseOrderService tests
├── GoodsReceiptNoteService tests
├── WorkOrderService tests
├── BomService tests
├── MrpService tests
└── SalesReturnService + PurchaseReturnService tests

Week G (P2 - Supporting Services):
├── RecurringService tests
├── StockOpnameService tests
├── All Report Service tests (8 services)
├── Number generator tests
└── Remaining service tests
```

**Deliverables:**
- ~200+ new test cases
- P0 services at 80%+ coverage
- P1 services at 60%+ coverage
- P2 services at minimum viable coverage

---

### Phase 4: Authorization Hardening (Item #4)

**Goal:** Every transactional model has proper authorization.

```
Week H:
├── Create 8 missing policies (extending BaseResourcePolicy)
├── Register policies in AppServiceProvider
├── Add permission constants to seeder
├── Write policy tests (authorized + unauthorized for each action)
├── Verify controllers use correct authorize() calls
└── Run full test suite
```

**Deliverables:**
- 8 new policy classes
- ~40 new policy tests
- Permission seeder updated
- Full authorization coverage for all transactional models

---

### Phase 5: Event Wiring (Item #5)

**Goal:** Cross-domain side effects work automatically.

```
Week I:
├── Create InventoryEventSubscriber
│   ├── Handle InventoryReceived → log, notify
│   ├── Handle InventoryIssued → log, alert on low stock
│   ├── Handle InventoryTransferred → log
│   └── Handle InventoryAdjusted → log, create adjustment journal
├── Create FiscalPeriodEventSubscriber
│   ├── Handle FiscalPeriodOpened → log
│   └── Handle FiscalPeriodClosed → lock entries, log
├── Wire GoodsReceiptNote events to inventory + accounting
├── Wire PurchaseReturn events to inventory reversal
├── Wire StockOpname completion to adjustment journals
└── Write subscriber tests for all new subscribers

Week J:
├── Stub notification listeners with proper interface
├── Document notification requirements for future implementation
├── Integration test: full workflow GRN → Inventory → Journal → Balance
├── Integration test: full workflow DO → COGS → Journal → P&L
└── Run full test suite, fix any regressions
```

**Deliverables:**
- 5 new/updated event subscribers
- ~50 new subscriber tests
- 2 end-to-end integration tests
- Cross-domain workflows verified

---

## Progress Tracking

| Phase | Items | Status | Tests Added |
|-------|-------|--------|-------------|
| Phase 1 | #1 (Accounting Strategies) | ⬜ Not Started | ~30 |
| Phase 2 | #2 (Service Consistency) | ⬜ Not Started | 0 (uses existing) |
| Phase 3 | #3 (Test Coverage) | ⬜ Not Started | ~200 |
| Phase 4 | #4 (Authorization) | ⬜ Not Started | ~40 |
| Phase 5 | #5 (Event Wiring) | ⬜ Not Started | ~50 |
| **Total** | | | **~320 new tests** |

---

## What This Enables

After all 5 phases:

| Capability | Before | After |
|-----------|--------|-------|
| Financial accuracy | Journals missing for inventory, COGS, manufacturing | Complete double-entry accounting |
| Feature velocity | 15 controllers need manual updates for cross-cutting concerns | One service change propagates everywhere |
| Regression safety | 6% service test coverage | 60%+ service test coverage |
| Security | 8 transactional models unprotected | Full RBAC on all transactional operations |
| Cross-domain integration | Events fire into void | Automatic side effects (journals, notifications, alerts) |
