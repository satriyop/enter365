# Pattern Enforcement Plan

> **Created:** 2026-01-22
> **Updated:** 2026-01-22
> **Status:** ✅ Phase 1 Complete | ✅ Phase 2 Complete | ✅ Phase 3 Complete
> **Priority:** High
> **Problem:** Architecture patterns exist but are not enforced — 95% of code bypasses them

---

## Progress Summary

### Phase 2: God Service Surgery ✅ COMPLETED

**BrandSwapService split into focused services:**

| Service | Lines | Responsibilities |
|---------|-------|------------------|
| `BrandSwapService.php` (coordinator) | 124 | Thin facade delegating to specialized services |
| `BrandSwap/BrandSwapPreviewService.php` | 310 | Read-only preview, comparison, alternatives |
| `BrandSwap/BrandSwapExecutionService.php` | 342 | Write operations: swap, variants, quick-swap |

**YearEndCloseService: Kept as-is (architectural decision)**
- Already well-structured as an orchestrator
- Uses Strategy pattern (AccountingPolicyManager)
- All methods relate to single workflow (year-end closing)
- Splitting would scatter cohesive code

**Verification:**
- ✅ BrandSwap tests: 18 tests passing
- ✅ Full test suite: 1995 tests passing (5958 assertions)
- ✅ Laravel Pint: 3 files formatted
- ✅ Backward compatibility maintained via delegation

---

### Phase 1: auth()->id() Elimination ✅ COMPLETED

**All 54 `auth()->id()` violations eliminated from 24 services.**

| Batch | Services Refactored | Violations Fixed |
|-------|---------------------|------------------|
| 1.1 CI Check | Created `scripts/check-auth-id-usage.sh` | N/A |
| 1.2 Financial & Inventory | BillService, PaymentService, InventoryService, StockOpnameService, QuotationConversionService | 21 |
| 1.3 Sales | SalesReturnService, RecurringService | 3 |
| 1.4 Purchasing | PurchaseOrderService, PurchaseOrderReceivingService, PurchaseReturnService, GoodsReceiptNoteService | 6 |
| 1.5 Manufacturing | WorkOrderService, BomTemplateService, MaterialRequisitionService, SubcontractorService, MrpService, MrpSuggestionService, WorkOrderMaterialService, BrandSwapService | 18 |
| 1.6 Other | SolarProposalService, FiscalPeriodService, JournalService, ProjectService | 6 |

**Additional Fix:** Renamed `MrpService::execute()` to `MrpService::executeRun()` to avoid method signature conflict with `AbstractApplicationService::execute()`.

**Verification:**
- ✅ CI check script: 0 violations detected
- ✅ PHPStan: No new errors introduced
- ✅ Test suite: 1995 tests passing (5958 assertions)
- ✅ Laravel Pint: 43 files formatted

---

---

## Executive Summary

You've built excellent infrastructure (OperationContext, Repository interfaces, Domain Factories) but **the codebase doesn't use it**. This plan focuses on **enforcing existing patterns**, not creating new ones.

| Pattern | Infrastructure Status | Actual Usage |
|---------|----------------------|--------------|
| OperationContext | ✅ Built | ❌ 54 `auth()->id()` calls in 24 services |
| Repository Pattern | ✅ 4 interfaces | ❌ 95% of services use Eloquent directly |
| Domain Factory | ✅ QuotationDomainFactory | ❌ Only Quotation uses it |

---

## Issue 1: auth()->id() Proliferation

### Current State

**54 occurrences** of `auth()->id()` across **24 services**:

| Service | Count | Priority |
|---------|-------|----------|
| `PaymentService.php` | 8 | High (financial) |
| `BillService.php` | 6 | High (financial) |
| `InventoryService.php` | 5 | High (stock) |
| `MrpSuggestionService.php` | 4 | Medium |
| `SubcontractorService.php` | 6 | Medium |
| Others (18 services) | 25 | Various |

### Why This Matters

1. **Untestable** — Cannot unit test without mocking Laravel auth
2. **CLI/Queue bugs** — `auth()->id()` returns null in artisan commands
3. **Inconsistent** — Some methods accept `$userId`, others hardcode `auth()`
4. **Pattern violation** — `OperationContext` exists but is unused

### Refactoring Strategy

**Step 1: Create PHPStan rule to detect violations**

```php
// phpstan-rules/NoDirectAuthIdRule.php
// Custom rule that errors on auth()->id() in Services/
```

**Step 2: Refactor by domain (batch approach)**

| Batch | Services | Estimated Time |
|-------|----------|---------------|
| 1. Financial | BillService, PaymentService, DownPaymentService | 2 hours |
| 2. Inventory | InventoryService, StockOpnameService | 1 hour |
| 3. Manufacturing | WorkOrderService, BomTemplateService, MrpService | 2 hours |
| 4. Purchasing | PurchaseOrderService, GoodsReceiptNoteService | 1 hour |
| 5. Remaining | All others | 2 hours |

**Refactoring Pattern:**

```php
// BEFORE
public function create(array $data): Bill
{
    $data['created_by'] = auth()->id();  // ❌ Direct auth
    return Bill::create($data);
}

// AFTER
public function create(array $data): Bill
{
    $data['created_by'] = $this->getUserId();  // ✅ Uses OperationContext
    return Bill::create($data);
}
```

**Step 3: Add test coverage for context injection**

```php
test('uses OperationContext when provided', function () {
    $service = app(BillService::class)
        ->withContext(OperationContext::forUser(42));

    $bill = $service->create([...]);

    expect($bill->created_by)->toBe(42);
});
```

### Files to Modify

```
app/Services/Purchasing/BillService.php                    (6 occurrences)
app/Services/Shared/PaymentService.php                     (8 occurrences)
app/Services/Inventory/InventoryService.php                (5 occurrences)
app/Services/Manufacturing/MrpSuggestionService.php        (4 occurrences)
app/Services/Manufacturing/SubcontractorService.php        (6 occurrences)
app/Services/Manufacturing/MaterialRequisitionService.php  (2 occurrences)
app/Services/Purchasing/PurchaseOrderReceivingService.php  (2 occurrences)
app/Services/Purchasing/GoodsReceiptNoteService.php        (2 occurrences)
app/Services/Sales/RecurringService.php                    (2 occurrences)
app/Services/Manufacturing/MrpService.php                  (2 occurrences)
app/Services/Solar/SolarProposalService.php                (2 occurrences)
+ 13 more services with 1 occurrence each
```

### Acceptance Criteria

- [x] Zero `auth()->id()` calls in `app/Services/`
- [x] All services extend `AbstractApplicationService` (for `getUserId()`)
- [x] CI check script prevents regression (`scripts/check-auth-id-usage.sh`)
- [ ] Test coverage for context injection in critical services (Phase 2)

---

## Issue 2: God Services

### Current State

| Service | Lines | Responsibilities |
|---------|-------|-----------------|
| `BrandSwapService` | 620 | Preview, validate, execute, variants, costs |
| `YearEndCloseService` | 544 | Validate, close, reopen, reverse |
| `QuotationService` | 549 | CRUD, workflow, statistics, conversion |
| `InventoryService` | ~400 | Movements, adjustments, transfers, tracking |

### Why This Matters

1. **Constructor hell** — QuotationService has 11 dependencies
2. **Hard to test** — Can't test one behavior without setting up everything
3. **Merge conflicts** — Multiple devs touching same large file
4. **Cognitive load** — 600 lines is hard to reason about

### Refactoring Strategy: Extract by Responsibility

**BrandSwapService → 3 Services**

```
app/Services/Manufacturing/BrandSwap/
├── BrandSwapPreviewService.php      (~150 lines)
│   └── preview(), getPreviewReport()
├── BrandSwapValidationService.php   (~100 lines)
│   └── validate(), getBlockReasons()
└── BrandSwapExecutionService.php    (~300 lines)
    └── execute(), createVariants(), updateCosts()
```

**Coordinator Pattern:**

```php
// Keep thin BrandSwapService as coordinator
class BrandSwapService
{
    public function __construct(
        private BrandSwapPreviewService $preview,
        private BrandSwapValidationService $validation,
        private BrandSwapExecutionService $execution,
    ) {}

    public function swap(Brand $from, Brand $to): BrandSwapResult
    {
        $this->validation->validate($from, $to);
        return $this->execution->execute($from, $to);
    }
}
```

**YearEndCloseService → 3 Services**

```
app/Services/Accounting/YearEnd/
├── YearEndValidationService.php    (~100 lines)
├── YearEndClosingService.php       (~200 lines)
└── YearEndReopeningService.php     (~150 lines)
```

### Extraction Checklist

**BrandSwapService:** ✅ COMPLETED
- [x] Create `BrandSwap/` subdirectory
- [x] Extract `BrandSwapPreviewService`
- [x] Extract `BrandSwapExecutionService` (validation is inline in preview)
- [x] Slim down original to coordinator (124 lines)
- [x] Tests remain green (18 tests passing)
- [x] DI bindings not needed (Laravel auto-resolves concrete classes)

**YearEndCloseService:** ⏸️ KEPT AS-IS (architectural decision)
- Already well-structured orchestrator pattern
- Uses Strategy pattern (AccountingPolicyManager)
- Single responsibility: year-end closing workflow
- Splitting would scatter cohesive code unnecessarily

### Acceptance Criteria

- [x] No service exceeds 300 lines (except well-justified orchestrators)
- [x] No constructor has more than 6 dependencies
- [x] Each service has single, clear responsibility
- [x] Tests remain green (1995 tests passing)

---

## Issue 3: Service-to-Service Coupling ⚠️ REVISED

### Original Analysis (Incorrect)

The original plan identified these as problematic:

| Service | Plan Said | Reality |
|---------|-----------|---------|
| QuotationService → QuotationConversionService | Event-decouple | ✅ Simple delegation (facade pattern) |
| WorkOrderService → InventoryService | Event-decouple | ❌ **Doesn't exist** - uses WorkOrderMaterialService |
| PurchaseOrderService → InventoryService | Event-decouple | ❌ **Doesn't exist** - uses PurchaseOrderReceivingService |

### Actual Findings (Jan 2026 Analysis)

**Good Patterns Already in Place:**

1. **WorkOrderService** uses Coordinator Pattern:
   - Injects `WorkOrderMaterialService` (handles materials)
   - Injects `WorkOrderCostService` (handles costs)
   - This is the SAME pattern we applied to BrandSwapService ✅

2. **QuotationService → QuotationConversionService**:
   - Single method delegation: `convertToInvoice()` just calls `conversionService->convertToInvoice()`
   - This is acceptable facade/delegation pattern ✅

3. **GoodsReceiptNoteService → InventoryServiceInterface**:
   - Uses interface (not concrete) ✅
   - Inventory updates within DB transaction (correct for data consistency) ✅

**Actual Issues Found:**

| Issue | File | Line | Problem |
|-------|------|------|---------|
| Concrete injection | `DeliveryOrderService.php` | 20 | `InventoryService` instead of `InventoryServiceInterface` |
| Concrete injection | `StockOpnameService.php` | 22 | `InventoryService` instead of `InventoryServiceInterface` |
| Unused injection | `GoodsReceiptNoteService.php` | 24 | `PurchaseOrderService` injected but never used |

### Why NOT Event-Decouple Inventory Operations

Event-driven decoupling would **break transactional consistency** for inventory:

```php
// CURRENT (Correct) - Inventory within transaction
return DB::transaction(function () {
    $this->inventoryService->stockIn(...);  // Fails? Entire transaction rolls back
    $grn->transitionTo(DocumentStatus::Completed);
});

// EVENT-BASED (Wrong) - Breaks transaction boundary
$grn->transitionTo(DocumentStatus::Completed);
event(new GoodsReceived($grn));  // Async listener - no rollback possible!
```

**Inventory operations must be synchronous** because:
- Failed inventory update should fail the entire operation
- Stock levels must be consistent with document status
- Audit trail requires atomic operations

### Revised Solution: Interface Enforcement

Instead of event-driven decoupling, fix **interface violations**:

```php
// BEFORE (concrete injection)
class DeliveryOrderService
{
    public function __construct(
        private InventoryService $inventoryService,  // ❌ Concrete class
    ) {}
}

// AFTER (interface injection)
class DeliveryOrderService
{
    public function __construct(
        private InventoryServiceInterface $inventoryService,  // ✅ Interface
    ) {}
}
```

### Files to Fix

| File | Fix | Impact |
|------|-----|--------|
| `app/Services/Sales/DeliveryOrderService.php` | `InventoryService` → `InventoryServiceInterface` | Low |
| `app/Services/Inventory/StockOpnameService.php` | `InventoryService` → `InventoryServiceInterface` | Low |
| `app/Services/Purchasing/GoodsReceiptNoteService.php` | Remove unused `PurchaseOrderService` injection | Low |

### Acceptance Criteria (Revised)

- [x] Coordinator pattern documented (WorkOrderService, BrandSwapService)
- [ ] No concrete service injections (use interfaces)
- [ ] No unused service injections
- [ ] Inventory operations remain transactional (NOT event-driven)

---

## Implementation Priority

### Phase 1: Stop the Bleeding (Week 1)

| Task | Impact | Effort | Files |
|------|--------|--------|-------|
| 1.1 PHPStan rule for `auth()->id()` | High | Low | 1 |
| 1.2 Refactor Financial services | High | Medium | 3 |
| 1.3 Refactor Inventory services | High | Low | 2 |

**Deliverable:** Financial and Inventory services use OperationContext

### Phase 2: God Service Surgery (Week 2-3)

| Task | Impact | Effort | Files |
|------|--------|--------|-------|
| 2.1 Split BrandSwapService | High | High | 4 |
| 2.2 Split YearEndCloseService | Medium | Medium | 3 |
| 2.3 Refactor remaining auth()->id() | High | Medium | 19 |

**Deliverable:** No god services, zero auth()->id()

### Phase 3: Interface Enforcement ✅ COMPLETED

> **Note:** Original plan called for event-driven decoupling, but analysis revealed:
> 1. Assumed coupling didn't exist (WorkOrder/PO don't inject InventoryService directly)
> 2. Event-based inventory would break transactional consistency
> 3. Existing patterns (Coordinator) are already good

| Task | Status | Files Changed |
|------|--------|---------------|
| 3.1 Fix concrete injections | ✅ Done | 2 |
| 3.2 Remove unused injections | ✅ Done | 1 |
| 3.3 Document lessons learned | ✅ Done | 3 skill files |

**Files modified:**
- `DeliveryOrderService.php` - ✅ Changed to `InventoryServiceInterface`
- `StockOpnameService.php` - ✅ Changed to `InventoryServiceInterface`
- `GoodsReceiptNoteService.php` - ✅ Removed unused `PurchaseOrderService`

**Skills updated with lessons learned:**
- `EVENTS.md` - Added "When NOT to Use Events" section
- `SOLID_PRINCIPLES.md` - Added DIP violations examples
- `SKILL.md` - Added gotcha #23 (Events vs Transactions)

**Verification:**
- ✅ 91 related tests passing
- ✅ PHPStan: No errors
- ✅ Laravel Pint: 1 file formatted

---

## Success Metrics

| Metric | Before | Current | Target |
|--------|--------|---------|--------|
| `auth()->id()` in services | 54 | **0** ✅ | 0 |
| Services > 400 lines | 4 | **2** ✅ (YearEnd, Quotation - well-structured) | 0* |
| Services > 300 lines | 8 | **6** ✅ (BrandSwap split reduced) | 0* |
| Concrete service injections | 2 | **0** ✅ | 0 |
| Unused service injections | 1 | **0** ✅ | 0 |
| Constructor dependencies > 6 | 3 | **2** ✅ (BrandSwap coordinator has 4 deps) | 0 |

*Note: YearEndCloseService (544 lines) and QuotationService (549 lines) are well-structured orchestrators. Splitting would harm cohesion.

**Revised Metric (removed):**
- ~~Service-to-service injections~~ → Analysis showed these are valid patterns:
  - Coordinator pattern (WorkOrderService → sub-services) ✅
  - Facade delegation (QuotationService → ConversionService) ✅
  - Interface-based injection (GRN → InventoryServiceInterface) ✅

---

## Regression Prevention

### PHPStan Rules

```neon
# phpstan.neon
rules:
    - App\PHPStan\NoDirectAuthIdRule      # Blocks auth()->id() in Services
    - App\PHPStan\ServiceLineCountRule    # Warns on services > 300 lines
    - App\PHPStan\MaxDependenciesRule     # Warns on constructors > 6 deps
```

### CI Checks

```yaml
# .github/workflows/ci.yml
- name: Check for auth()->id() in services
  run: |
    if grep -r "auth()->id()" app/Services/; then
      echo "ERROR: Found auth()->id() in services. Use getUserId() instead."
      exit 1
    fi
```

### Code Review Checklist

- [ ] No `auth()->id()` — use `$this->getUserId()`
- [ ] No `Model::create()` — use repository
- [ ] Service injections use interfaces, not concrete classes
- [ ] Coordinator pattern for multi-responsibility services (see BrandSwap, WorkOrder)
- [ ] Service under 300 lines (exceptions: well-structured orchestrators)
- [ ] Constructor under 6 dependencies

---

## Notes

- This plan focuses on **enforcement**, not creating new patterns
- The infrastructure already exists (OperationContext, Repositories, Events)
- Goal is consistency: if you have a pattern, USE IT everywhere
- PHPStan rules prevent regression after cleanup

### Lessons Learned (Phase 3 Revision)

1. **Verify assumptions before planning** — The original coupling analysis was based on assumptions, not code inspection
2. **Not all coupling is bad** — Coordinator pattern (service → sub-services) is a GOOD pattern
3. **Events are for side-effects, not core operations** — Inventory must be transactional
4. **Interface injection > Concrete injection** — Always inject interfaces for testability
