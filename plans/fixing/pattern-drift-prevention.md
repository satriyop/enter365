# Pattern Drift Prevention Plan

> **Created:** 2026-01-22
> **Status:** Active - Enforcement Required
> **Priority:** High
> **Source:** Brutal code review (Jan 2026)

---

## Executive Summary

Despite successful refactoring of QuotationService, **architectural drift** continues. This plan commits to **Pattern A** as the canonical service pattern and documents enforcement rules for future development.

---

## The Commitment: Pattern A is Canonical

**All new services MUST follow Pattern A (AbstractApplicationService with explicit DI).**

### Pattern A Definition (CANONICAL)

```php
class MyService extends AbstractApplicationService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        // Domain-specific dependencies...
        private MyRepositoryInterface $repository,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    public function doSomething(array $data): Model
    {
        return $this->executeInTransaction('do_something', function () use ($data) {
            // Business logic here
            // Uses $this->getUserId() for user context
            // Automatic logging, error handling, event dispatch
        });
    }
}
```

### Why Pattern A

| Feature | Pattern A | Pattern B (Legacy) | Pattern C (Plain) |
|---------|-----------|-------------------|-------------------|
| **Transaction wrapping** | `executeInTransaction()` | Mixed | Raw `DB::transaction()` |
| **Logging** | Automatic | Fallback via `app()` | None |
| **User context** | `$this->getUserId()` | Maybe | `auth()->id()` |
| **Dependencies** | Explicit constructor | Optional with `??=` | Hidden |
| **Testability** | Easy mocking | Container fallback | Hard |

---

## Remaining Issues to Fix

### Issue 0: AbstractDocumentService Uses Hidden Dependencies (ROOT CAUSE)

**Priority: P0 - Fix First**

**Problem:** `AbstractDocumentService` uses optional parameters with `app()` fallback - the exact anti-pattern that enables drift.

```php
// CURRENT (app/Services/Base/AbstractDocumentService.php lines 44-58)
public function __construct(
    ?RepositoryInterface $repository = null,
    ?DocumentNumberGeneratorInterface $numberGenerator = null,
    ?EventDispatcherInterface $eventDispatcher = null,      // ❌ Optional
    ?ContextualLoggerInterface $logger = null               // ❌ Optional
) {
    $eventDispatcher ??= app(EventDispatcherInterface::class);  // ❌ Service locator
    $logger ??= app(ContextualLoggerInterface::class);          // ❌ Service locator
    // ...
}
```

**Why This Is The Root Cause:**

This design **enables** child services to skip explicit DI:

```php
// BillService.php - relies on app() fallback
public function __construct(
    JournalServiceInterface $journalService,
    DocumentNumberGeneratorInterface $numberGenerator
) {
    parent::__construct(numberGenerator: $numberGenerator);  // ❌ No eventDispatcher, logger
}

// InvoiceService.php - does it correctly
public function __construct(
    InvoiceRepositoryInterface $repository,
    DocumentNumberGeneratorInterface $numberGenerator,
    EventDispatcherInterface $eventDispatcher,              // ✅ Explicit
    ContextualLoggerInterface $logger,                      // ✅ Explicit
    // ...
) {
    parent::__construct($repository, $numberGenerator, $eventDispatcher, $logger);
}
```

**Affected Child Services:**

| Service | Pattern | Needs Fix |
|---------|---------|-----------|
| `InvoiceService` | Passes all 4 deps | No |
| `BillService` | Only `numberGenerator` | **Yes** |
| `PurchaseOrderService` | Check | Maybe |
| `PurchaseReturnService` | Check | Maybe |
| `DeliveryOrderService` | Check | Maybe |
| `SalesReturnService` | Check | Maybe |

**Solution:**

**Step 1: Fix AbstractDocumentService**

```php
// AFTER - Strict DI, no fallback
public function __construct(
    EventDispatcherInterface $eventDispatcher,       // Required first (from parent)
    ContextualLoggerInterface $logger,               // Required second (from parent)
    ?RepositoryInterface $repository = null,         // Optional (some services use model directly)
    ?DocumentNumberGeneratorInterface $numberGenerator = null  // Optional (some generate differently)
) {
    parent::__construct($eventDispatcher, $logger);  // No app() fallback

    $this->repository = $repository;
    $this->numberGenerator = $numberGenerator;
}
```

**Step 2: Fix Child Services (e.g., BillService)**

```php
// BEFORE
public function __construct(
    JournalServiceInterface $journalService,
    DocumentNumberGeneratorInterface $numberGenerator
) {
    parent::__construct(numberGenerator: $numberGenerator);  // Relies on app() fallback
}

// AFTER
public function __construct(
    EventDispatcherInterface $eventDispatcher,
    ContextualLoggerInterface $logger,
    JournalServiceInterface $journalService,
    DocumentNumberGeneratorInterface $numberGenerator
) {
    parent::__construct($eventDispatcher, $logger, numberGenerator: $numberGenerator);
    $this->journalService = $journalService;
}
```

**Tasks:** ✅ ALL COMPLETE (Jan 2026)

- [x] Refactor `AbstractDocumentService` constructor (remove `??= app()`)
- [x] Update `InvoiceService` constructor (reorder params)
- [x] Update `BillService` constructor to pass required deps
- [x] Update `PurchaseOrderService` constructor
- [x] Update `PurchaseReturnService` constructor
- [x] Update `DeliveryOrderService` constructor
- [x] Update `SalesReturnService` constructor
- [x] Run tests - all passing
- [x] PHPStan - no errors

**Actual Effort:** ~1 hour

---

### Issue 1: Duplicate QuotationWorkflowService Classes ✅ COMPLETE

**Problem:** Two different classes with the same name doing different things.

| Location | Purpose | Pattern |
|----------|---------|---------|
| `app/Services/Sales/QuotationWorkflowService.php` | Mark won/lost (outcomes) | C (plain) |
| `app/Services/Sales/Quotation/QuotationWorkflowService.php` | State transitions | A (correct) |

**Risk:** Wrong class imported, IDE confusion, name collision.

**Resolution:**

```
BEFORE:
app/Services/Sales/QuotationWorkflowService.php         <- RENAME
app/Services/Sales/Quotation/QuotationWorkflowService.php

AFTER:
app/Services/Sales/QuotationOutcomeService.php          <- Clear name (+ migrated to Pattern A)
app/Services/Sales/Quotation/QuotationWorkflowService.php
```

**Tasks:** ✅ ALL COMPLETE (Jan 2026)
- [x] Rename `app/Services/Sales/QuotationWorkflowService.php` to `QuotationOutcomeService.php`
- [x] Migrate to Pattern A (extends AbstractApplicationService, uses executeInTransaction)
- [x] Update all imports and usages (QuotationFollowUpController)
- [x] Run tests: 321 passed

---

### Issue 2: Services Not Using Pattern A ✅ PARTIAL COMPLETE

**Services migrated from Pattern C to Pattern A:**

| Service | Status | Notes |
|---------|--------|-------|
| `QuotationConversionService` | ✅ Done | Already extended AbstractApplicationService, fixed to use `executeInTransaction()` |
| `QuotationOutcomeService` | ✅ Done | Migrated from plain class to Pattern A |

**Services still using legacy patterns:**

| Service | Current Pattern | Location |
|---------|-----------------|----------|
| Legacy services in Manufacturing | B (AbstractDocumentService) | Various |

**Note:** `AbstractDocumentService` is acceptable for document services (Invoice, Bill, PO, etc.) and now follows strict DI (P0 fix).

**Migration Checklist for Pattern C → A (Reference):**

```php
// BEFORE (Pattern C)
class QuotationConversionService
{
    public function convertToInvoice(Quotation $quotation): Invoice
    {
        return DB::transaction(function () use ($quotation) {
            // No logging, no context
        });
    }
}

// AFTER (Pattern A)
class QuotationConversionService extends AbstractApplicationService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        // Other dependencies...
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    public function convertToInvoice(Quotation $quotation): Invoice
    {
        return $this->executeInTransaction('convert_to_invoice', function () use ($quotation) {
            // With logging and context
        });
    }
}
```

---

### Issue 3: Strategy Tight Coupling

**Problem:** Strategies create other strategies internally with `new`.

```php
// Bad: PerpetualInventoryStrategy.php
return (new HybridInventoryStrategy($this->journalService))
    ->calculateCOGS($invoice);
```

**Solution:** Inject strategies via constructor or factory.

```php
// Good: Constructor injection
public function __construct(
    private JournalServiceInterface $journalService,
    private HybridInventoryStrategy $hybridStrategy,  // Injected, not created
) {}
```

**Tasks:**
- [ ] Audit strategy classes for `new` usage
- [ ] Refactor to constructor injection
- [ ] Update `AppServiceProvider` bindings

---

## Enforcement Rules for Future Agents

### Rule 1: Service Pattern Commitment

**When creating NEW services:**
1. ALWAYS extend `AbstractApplicationService`
2. ALWAYS inject `EventDispatcherInterface` and `ContextualLoggerInterface`
3. ALWAYS use `executeInTransaction()` for write operations
4. ALWAYS use `$this->getUserId()` not `auth()->id()`
5. NEVER use raw `DB::transaction()` directly

### Rule 2: Service Naming

**Avoid naming collisions:**
- Services in subfolders (e.g., `Quotation/`) handle specific concerns
- Root-level services should have distinct names
- If functionality is related, put in the same folder

**Good:**
```
app/Services/Sales/
├── QuotationService.php           # Coordinator
├── QuotationOutcomeService.php    # Distinct: win/lost tracking
└── Quotation/
    ├── QuotationCrudService.php
    ├── QuotationWorkflowService.php  # State transitions
    └── QuotationStatisticsService.php
```

**Bad:**
```
app/Services/Sales/
├── QuotationWorkflowService.php     # Name collision!
└── Quotation/
    └── QuotationWorkflowService.php # Same name, different purpose
```

### Rule 3: No Service Locator

**Never use `app()` in:**
- Models
- Services
- Domain objects

**Exception:** `AbstractApplicationService::getContext()` has controlled fallback for legacy support.

### Rule 4: Coordinator Pattern

**For services split into focused sub-services:**
1. Coordinator is plain class (no AbstractApplicationService)
2. Coordinator MUST implement `withContext()` that propagates to sub-services
3. Sub-services extend `AbstractApplicationService`

```php
class QuotationService implements QuotationServiceInterface
{
    public function __construct(
        private QuotationCrudService $crud,
        private QuotationWorkflowService $workflow,
    ) {}

    // REQUIRED: Propagate context to sub-services
    public function withContext(OperationContext $context): static
    {
        $clone = clone $this;
        $clone->crud = $this->crud->withContext($context);
        $clone->workflow = $this->workflow->withContext($context);
        return $clone;
    }
}
```

---

## Detection Script

Add to CI to prevent drift:

```bash
#!/bin/bash
# scripts/check-pattern-compliance.sh

echo "=== Pattern Compliance Check ==="

# 1. Check for auth()->id() in services
echo "Checking for auth()->id() usage..."
if grep -r "auth()->id()" app/Services/ --include="*.php"; then
    echo "ERROR: Found auth()->id() in services. Use \$this->getUserId()"
    exit 1
fi

# 2. Check for raw DB::transaction in Pattern A services
echo "Checking for raw DB::transaction()..."
# Services extending AbstractApplicationService should use executeInTransaction
for file in $(grep -l "extends AbstractApplicationService" app/Services/**/*.php 2>/dev/null); do
    if grep -q "DB::transaction" "$file"; then
        echo "WARNING: $file uses raw DB::transaction() - prefer executeInTransaction()"
    fi
done

# 3. Check for service locator in models
echo "Checking for app() in models..."
if grep -r "app(" app/Models/ --include="*.php" | grep -v "// allowed"; then
    echo "WARNING: Found app() in models - consider removing"
fi

echo "=== Pattern Check Complete ==="
```

---

## Migration Priority

### P0 - Do First (This Week) - ROOT CAUSE FIX ✅ COMPLETE

| Task | File | Status |
|------|------|--------|
| **Fix AbstractDocumentService** | `app/Services/Base/AbstractDocumentService.php` | ✅ Done |
| Fix InvoiceService constructor | `app/Services/Sales/InvoiceService.php` | ✅ Done |
| Fix BillService constructor | `app/Services/Purchasing/BillService.php` | ✅ Done |
| Fix PurchaseOrderService | `app/Services/Purchasing/PurchaseOrderService.php` | ✅ Done |
| Fix PurchaseReturnService | `app/Services/Purchasing/PurchaseReturnService.php` | ✅ Done |
| Fix DeliveryOrderService | `app/Services/Sales/DeliveryOrderService.php` | ✅ Done |
| Fix SalesReturnService | `app/Services/Sales/SalesReturnService.php` | ✅ Done |
| Run tests | All document service tests | ✅ All passing |
| PHPStan | Modified files | ✅ No errors |

**Completed:** Jan 2026 (~1 hour actual)

### P1 - High Priority (This Sprint) ✅ MOSTLY COMPLETE

| Task | File | Status |
|------|------|--------|
| Rename duplicate WorkflowService | `QuotationOutcomeService.php` | ✅ Done |
| Migrate OutcomeService to Pattern A | `QuotationOutcomeService.php` | ✅ Done |
| Fix ConversionService raw DB::transaction | `QuotationConversionService.php` | ✅ Done |
| Audit Manufacturing services | `app/Services/Manufacturing/` | Pending |

**Completed:** Jan 2026

### P2 - Medium Priority (Next Sprint) ✅ COMPLETE

| Task | File | Status |
|------|------|--------|
| Refactor strategy coupling | `app/Services/Accounting/Strategies/` | ✅ Done |
| Add CI script | `scripts/check-pattern-compliance.sh` | ✅ Done |
| Fix FiscalPeriodService `app()` fallback | `app/Services/Accounting/FiscalPeriodService.php` | ✅ Done |

**Completed:** Jan 2026

---

## Success Metrics

| Metric | Before | After | Target |
|--------|--------|-------|--------|
| `AbstractDocumentService` uses `app()` fallback | Yes | ✅ **No** | No |
| Child services with implicit deps | 5 | ✅ **0** | 0 |
| Services using Pattern A (strict DI) | ~40% | ✅ **~70%** | 80%+ |
| Services using raw `DB::transaction()` | ~30% | ~25% | <10% |
| Duplicate class names | 1 | ✅ **0** | 0 |
| Strategy classes with `new` internal | 3 | ✅ **0** | 0 |
| CI pattern check | Not present | ✅ **Enforced** | Enforced |

---

## Related Documentation

| Topic | File |
|-------|------|
| Pattern A definition | `.claude/skills/enter365/ARCHITECTURE_PATTERNS.md` |
| Anti-patterns | `.claude/skills/enter365/CODE_REVIEW_ANTIPATTERNS.md` |
| Service bindings | `.claude/skills/enter365/SERVICE_BINDINGS.md` |
| Previous remediation | `/plans/fixing/top-3-code-issues-remediation.md` |
