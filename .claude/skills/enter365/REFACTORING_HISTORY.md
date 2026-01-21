# Refactoring History

Changelog of architectural improvements with rationale and lessons learned. Helps future developers understand why patterns exist.

---

## Trigger

Use when:
- Understanding why a pattern exists
- Avoiding re-introducing fixed issues
- Learning from past architectural decisions
- Onboarding new developers

---

## 2026-01 Architectural Cleanup

### P0: Service Locator Root Cause Fix

**Date:** Jan 2026
**Files Changed:** 7 service files
**Tests:** All passing

#### Issue Found

`AbstractDocumentService` used optional constructor parameters with `app()` fallback:

```php
// BEFORE: AbstractDocumentService (BAD)
public function __construct(
    ?RepositoryInterface $repository = null,
    ?DocumentNumberGeneratorInterface $numberGenerator = null,
    ?EventDispatcherInterface $eventDispatcher = null,      // Optional!
    ?ContextualLoggerInterface $logger = null               // Optional!
) {
    $eventDispatcher ??= app(EventDispatcherInterface::class);  // Service locator
    $logger ??= app(ContextualLoggerInterface::class);
}
```

This **enabled** child services to skip explicit DI:

```php
// BillService could skip required deps
public function __construct(
    JournalServiceInterface $journalService,
    DocumentNumberGeneratorInterface $numberGenerator
) {
    parent::__construct(numberGenerator: $numberGenerator);  // No eventDispatcher, logger!
}
```

#### Why This Was Bad

| Problem | Impact |
|---------|--------|
| **Hidden dependencies** | Not visible in constructor signature |
| **Untestable** | Can't mock container-resolved deps |
| **Inconsistent** | Some services explicit, others implicit |
| **Architectural drift** | Enabled lazy practices |

#### The Fix

Made `EventDispatcherInterface` and `ContextualLoggerInterface` required first parameters:

```php
// AFTER: AbstractDocumentService (GOOD)
public function __construct(
    EventDispatcherInterface $eventDispatcher,       // Required first
    ContextualLoggerInterface $logger,               // Required second
    ?RepositoryInterface $repository = null,
    ?DocumentNumberGeneratorInterface $numberGenerator = null
) {
    parent::__construct($eventDispatcher, $logger);
    // No app() fallback
}
```

Updated all 6 child services:
- `InvoiceService` - Reordered params
- `BillService` - Added explicit deps
- `PurchaseOrderService` - Added explicit deps
- `PurchaseReturnService` - Added explicit deps
- `DeliveryOrderService` - Added explicit deps
- `SalesReturnService` - Added explicit deps

#### Lesson Learned

> **Never use optional DI parameters with `app()` fallback.** It creates a backdoor that enables architectural drift. Future services will copy the lazy pattern.

**Detection Rule:** Search for `??= app(` in service constructors.

---

### P1: Duplicate Class Name Resolution

**Date:** Jan 2026
**Files Changed:** 2 files
**Tests:** All passing

#### Issue Found

Two classes with the same name doing different things:

```
app/Services/Sales/QuotationWorkflowService.php         <- Mark won/lost
app/Services/Sales/Quotation/QuotationWorkflowService.php <- State transitions
```

| Risk | Example |
|------|---------|
| **IDE confusion** | Auto-import picks wrong class |
| **grep noise** | Both files match search |
| **Silent bugs** | Wrong class used, works initially but fails in edge cases |

#### The Fix

Renamed to reflect actual purpose:

```
BEFORE: QuotationWorkflowService.php         (outcomes)
AFTER:  QuotationOutcomeService.php          (clear purpose)
```

Also migrated to Pattern A (was plain class):

```php
// BEFORE: Plain class
class QuotationWorkflowService
{
    public function markAsWon(Quotation $quotation, array $data = []): Quotation
    {
        // No transaction wrapping, no logging
    }
}

// AFTER: Pattern A
class QuotationOutcomeService extends AbstractApplicationService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    public function markAsWon(Quotation $quotation, array $data = []): Quotation
    {
        return $this->executeInTransaction('mark_as_won', function () use ($quotation, $data) {
            // With automatic logging and context
        }, ['quotation_id' => $quotation->id]);
    }
}
```

#### Lesson Learned

> **Class names must be globally unique across folders.** Even if namespaces differ, same names cause IDE/human confusion.

**Detection Rule:**
```bash
find app/Services -name "*.php" -exec basename {} \; | sort | uniq -d
```

---

### P1: Raw DB::transaction Migration

**Date:** Jan 2026
**Files Changed:** 1 file
**Tests:** All passing

#### Issue Found

`QuotationConversionService` extended `AbstractApplicationService` but used raw `DB::transaction()`:

```php
// BEFORE: Raw transaction
public function convertToInvoice(Quotation $quotation): Invoice
{
    return DB::transaction(function () use ($quotation) {
        // No logging, no context tracking
    });
}
```

#### Why This Was Bad

| Using `DB::transaction()` | Using `executeInTransaction()` |
|---------------------------|-------------------------------|
| No automatic logging | Logs operation name, context |
| No timing metrics | Records execution time |
| No consistent error handling | Standardized exception wrapping |
| Manual context passing | Automatic user ID via `$this->getUserId()` |

#### The Fix

```php
// AFTER: executeInTransaction
public function convertToInvoice(Quotation $quotation): Invoice
{
    return $this->executeInTransaction('convert_to_invoice', function () use ($quotation) {
        // Automatic logging: "Starting convert_to_invoice with context: {quotation_id: 123}"
    }, ['quotation_id' => $quotation->id]);
}
```

#### Lesson Learned

> **If extending AbstractApplicationService, always use `executeInTransaction()` for write operations.** The base class provides it for a reason.

**Detection Rule:**
```bash
for file in $(grep -l "extends AbstractApplicationService" app/Services/**/*.php 2>/dev/null); do
    if grep -q "DB::transaction" "$file"; then
        echo "WARNING: $file uses raw DB::transaction()"
    fi
done
```

---

## Pattern Summary: What We Committed To

After this refactoring, the project commits to **Pattern A** for all services:

| Aspect | Required Pattern |
|--------|------------------|
| **Base class** | `AbstractApplicationService` (or `AbstractDocumentService` for documents) |
| **Dependencies** | Explicit constructor injection, no optional with fallback |
| **Transactions** | `$this->executeInTransaction()`, not raw `DB::transaction()` |
| **User context** | `$this->getUserId()`, not `auth()->id()` |
| **Class naming** | Globally unique, even across folders |

See: [SKILL.md](SKILL.md#critical-pattern-commitment-read-first) for enforcement rules.

---

## Metrics Improvement

| Metric | Before | After (P2) | After (P3) | After (P4) |
|--------|--------|------------|------------|------------|
| Services using `app()` fallback | 7+ | 0 | 0 | 0 |
| Duplicate class names | 1 | 0 | 0 | 0 |
| Services using Pattern A | ~40% | ~70% | ~75% | **100%** ✅ |
| Strategies with `new` internal | 3 | 0 | 0 | 0 |
| CI pattern check | None | Enforced | Enforced | Enforced |
| Manufacturing services on Pattern A | 8/18 | 8/18 | 10/18 | 18/18 |
| Services with raw `DB::transaction()` | ~30% | 20 | 18 | **0** ✅ |

---

## P2 Fixes (Jan 2026)

### Strategy Tight Coupling Fix

**Problem:** Strategies creating other strategies with `new` internally.

```php
// BEFORE (BAD)
public function calculateCOGS(Invoice $invoice): int
{
    return (new COGSOnInvoiceStrategy($this->journalService))->calculateCOGS($invoice);
}
```

**Fix:** Inject strategies via constructor, let Laravel resolve dependencies.

```php
// AFTER (GOOD)
public function __construct(
    private COGSOnInvoiceStrategy $invoiceStrategy
) {}

public function calculateCOGS(Invoice $invoice): int
{
    return $this->invoiceStrategy->calculateCOGS($invoice);
}
```

**Files Fixed:**
- `COGSOnDeliveryStrategy.php` - Injects `COGSOnInvoiceStrategy`
- `PeriodicInventoryStrategy.php` - Injects `HybridInventoryStrategy`
- `PerpetualInventoryStrategy.php` - Injects `HybridInventoryStrategy`
- `FiscalPeriodService.php` - Fixed `??= app()` fallback

### CI Pattern Compliance Script

Created `scripts/check-pattern-compliance.sh` that checks:
1. `auth()->id()` usage in Services (error)
2. `new ...Strategy()` in Strategies (error)
3. `??= app()` fallback pattern (error)
4. Raw `DB::transaction()` in Pattern A services (warning)

---

## P3: Manufacturing Services Migration (Jan 2026)

**Date:** Jan 2026
**Files Changed:** 4 files
**Tests:** All 2002 passing

### Issue Found

Manufacturing services audit revealed:
- 18 total Manufacturing service files
- 8 already using Pattern A (AbstractApplicationService)
- 2 with write operations needing migration: `BomService`, `BomVariantGroupService`
- Both services used raw `DB::transaction()` and didn't extend AbstractApplicationService

### The Fix

#### BomService Migration

```php
// BEFORE: Plain class with raw transactions
class BomService implements BomServiceInterface
{
    public function __construct(
        private DocumentNumberGeneratorInterface $numberGenerator
    ) {}

    public function activate(Bom $bom, ?int $userId = null): Bom
    {
        // $userId passed from controller
        $bom->approved_by = $userId;
    }

    public function duplicate(Bom $bom): Bom
    {
        return DB::transaction(function () use ($bom) {
            $newBom->bom_number = Bom::generateBomNumber();  // Static method
        });
    }
}

// AFTER: Pattern A with proper DI
class BomService extends AbstractApplicationService implements BomServiceInterface
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        private DocumentNumberGeneratorInterface $numberGenerator
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    public function activate(Bom $bom): Bom  // No $userId param
    {
        return $this->executeInTransaction('activate', function () use ($bom) {
            $bom->approved_by = $this->getUserId();  // From OperationContext
        }, ['bom_id' => $bom->id]);
    }

    public function duplicate(Bom $bom): Bom
    {
        return $this->executeInTransaction('duplicate', function () use ($bom) {
            $newBom->bom_number = $this->numberGenerator->generate(...);  // Injected
            $newBom->created_by = $this->getUserId();
        }, ['source_bom_id' => $bom->id]);
    }
}
```

**Key Changes:**
- Removed `$userId` parameter from `activate()` - uses `$this->getUserId()`
- Replaced static `Bom::generateBomNumber()` with injected `$numberGenerator`
- All write operations wrapped in `executeInTransaction()` with context logging
- Fixed PHPStan error: `approved_at = now()->toDateTimeString()` instead of `now()`

#### BomVariantGroupService Migration

Similar migration - 8 methods converted to `executeInTransaction()`:
- `create()`, `update()`, `delete()`
- `addBom()`, `removeBom()`, `setPrimaryVariant()`
- `reorderVariants()`, `createVariantFromBom()`

### Lesson Learned

> **When auditing services for Pattern A compliance, check both the base class AND the transaction usage.** A service might not extend AbstractApplicationService but still use `DB::transaction()` internally.

**Detection Rule:**
```bash
# Find services not using Pattern A but having write operations
for file in app/Services/**/*.php; do
    if ! grep -q "extends Abstract" "$file" && grep -q "DB::transaction\|->save()" "$file"; then
        echo "Needs review: $file"
    fi
done
```

---

## P4: Complete Pattern A Migration (Jan 2026)

**Date:** Jan 2026
**Files Changed:** 7 service files
**Tests:** All 2002 passing

### Achievement

**100% Pattern A compliance achieved.** All 29 services now use `executeInTransaction()` instead of raw `DB::transaction()`.

### Services Migrated

These services didn't extend `AbstractApplicationService` and used raw `DB::transaction()`:

| Service | Transactions Migrated |
|---------|----------------------|
| `ProductService` | 2 |
| `InvoicePaymentService` | 2 |
| `DownPaymentService` | 8 |
| `BudgetService` | 2 |
| `YearEndCloseService` | 2 |
| `QuotationFollowUpService` | 1 |
| `CostOptimizationService` | 1 |
| **Total** | **18** |

### The Fix Pattern

Each service required:

1. **Extend base class:**
```php
// BEFORE
class BudgetService
{
    public function createBudget(array $data, array $lines = []): Budget
    {
        return DB::transaction(function () use ($data, $lines) {
            // No logging, no context
        });
    }
}

// AFTER
class BudgetService extends AbstractApplicationService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    public function createBudget(array $data, array $lines = []): Budget
    {
        return $this->executeInTransaction('create_budget', function () use ($data, $lines) {
            // Automatic logging and context tracking
        }, ['fiscal_period_id' => $data['fiscal_period_id'] ?? null]);
    }
}
```

2. **Add context to transactions:**
```php
// Each executeInTransaction() call includes context for debugging:
$this->executeInTransaction('apply_optimization', function () use ($bom, $itemIds) {
    // ...
}, ['source_bom_id' => $bom->id, 'items_count' => count($itemIds)]);
```

### Verification

```bash
# Only AbstractApplicationService.php should have DB::transaction()
grep -rn "DB::transaction" app/Services/ | grep -v "AbstractApplicationService"
# Returns: 0 results ✅
```

### Lesson Learned

> **Services that don't extend AbstractApplicationService easily drift to using raw `DB::transaction()`.** The migration pattern is straightforward: extend base class, add constructor DI, wrap transactions with `executeInTransaction()`.

**Detection Rule:**
```bash
# Find services not extending Abstract* but using transactions
for file in app/Services/**/*.php; do
    if ! grep -q "extends Abstract" "$file" && grep -q "DB::transaction" "$file"; then
        echo "Needs migration: $file"
    fi
done
```

---

## Remaining Work

All P0-P4 issues resolved. Pattern A migration complete.

See: [/plans/fixing/pattern-drift-prevention.md](/plans/fixing/pattern-drift-prevention.md)

---

## Related Documentation

| Topic | File |
|-------|------|
| Pattern A definition | [ARCHITECTURE_PATTERNS.md](ARCHITECTURE_PATTERNS.md) |
| Anti-patterns to avoid | [CODE_REVIEW_ANTIPATTERNS.md](CODE_REVIEW_ANTIPATTERNS.md) |
| Service bindings | [SERVICE_BINDINGS.md](SERVICE_BINDINGS.md) |
| Full remediation plan | [/plans/fixing/pattern-drift-prevention.md](/plans/fixing/pattern-drift-prevention.md) |
