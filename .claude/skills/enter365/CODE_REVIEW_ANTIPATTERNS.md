# Code Review Anti-Patterns

Lessons learned from code review analysis. These are the patterns that cause the most pain.

---

## Top 3 Code Smells

### 1. Architectural Schizophrenia

**Symptom:** Multiple patterns coexist without commitment.

| Pattern | Infrastructure Built | Actual Usage |
|---------|---------------------|--------------|
| Domain Factory | 4 factories | Used by models with state machines ✅ |
| OperationContext | Full implementation | ✅ Fixed: Now uses middleware |

**Problem:** Developers see infrastructure that's never used. Confuses everyone.

**Solution:** Pick a lane and commit:
- Either use the pattern **everywhere** it applies
- Or **delete it** (YAGNI)

**Decision made for Enter365:**
- No repository layer — services access Eloquent directly
- Domain Factory: For models with state machines (Quotation, Invoice, PurchaseOrder, WorkOrder)
- OperationContext: Middleware auto-binding (complete)

---

### 2. God Services

**Symptom:** Service with 500+ lines, 8+ constructor dependencies, multiple responsibilities.

**Real Example - QuotationService (549 lines, 11 dependencies, before refactoring):**

```php
public function __construct(
    EventDispatcherInterface $eventDispatcher,
    ContextualLoggerInterface $logger,
    DocumentNumberGeneratorInterface $numberGenerator,
    QuotationNumberGeneratorInterface $quotationNumberGenerator,
    QuotationDefaults $defaults,
    QuotationItemCreator $itemCreator,
    QuotationStatistics $statistics,
    QuotationCalculatorInterface $calculator,
    QuotationDomainFactory $domainFactory,
    QuotationConversionService $conversionService  // Circular dependency risk
) {}
```

**Why It's Bad:**
- **SRP Violation**: CRUD + Workflow + Statistics + Conversion in one class
- **Testing Pain**: Must mock 12 dependencies to test one method
- **Merge Conflicts**: Everyone touches this file
- **Cognitive Load**: 549 lines is hard to reason about

**Solution: Coordinator Pattern**

Split into focused services, keep thin coordinator for backward compatibility:

```
app/Services/Sales/Quotation/
├── QuotationService.php              # Thin coordinator (~100 lines)
├── QuotationCrudService.php          # create, update, delete
├── QuotationWorkflowService.php      # submit, approve, reject, cancel
└── QuotationStatisticsService.php    # getStatistics
```

**When to Split vs Keep:**

| Split When | Keep When |
|------------|-----------|
| Different dependency needs | Single cohesive workflow |
| 500+ lines, 8+ dependencies | Uses Strategy pattern |
| Distinct responsibility groups | Methods interdependent |
| Testing requires unrelated mocks | Splitting would scatter code |

See: [ARCHITECTURE_PATTERNS.md](ARCHITECTURE_PATTERNS.md#coordinator-pattern-for-god-services)

---

### 3. Business Logic Misplacement

**Symptom:** Logic in wrong layer - repository doing calculations, controller doing business logic.

#### 3a. Data Access Mixed with Business Logic

**Bad - Service mixing raw query and calculation in one method:**
```php
public function getWinRateStats(?DateRange $range = null): array
{
    $results = DB::table('quotations')
        ->select(...)
        ->get();

    // ❌ Business calculation mixed with data access
    $winRate = $decided > 0 ? round(($won / $decided) * 100, 2) : 0.0;

    return [...];
}
```

**Why It's Bad:**
- Data access and business logic are tangled
- Can't test calculation logic without database
- Method does two things (query + calculate)

**Good - Separate data retrieval from calculation:**
```php
// Data retrieval method (or use DB::table() for aggregation)
private function getQuotationCountsByOutcome(?DateRange $range = null): array
{
    return DB::table('quotations')
        ->select('outcome')
        ->selectRaw('COUNT(*) as count')
        ->groupBy('outcome')
        ->get()
        ->keyBy('outcome')
        ->toArray();
}

// Public method - business logic
public function getWinRateStats(?DateRange $range = null): array
{
    $rawStats = $this->getQuotationCountsByOutcome($range);

    // ✅ Business calculation separated from data access
    $decided = $rawStats['won'] + $rawStats['lost'];
    $winRate = $decided > 0 ? round(($rawStats['won'] / $decided) * 100, 2) : 0.0;

    return [...];
}
```

#### 3b. Controller Doing Business Logic

**Bad - QuotationController::syncVariantOptions (57 lines):**
```php
public function syncVariantOptions(Request $request, Quotation $quotation): JsonResponse
{
    $validated = $request->validate([...]);

    // ❌ Business logic in controller
    if (! $quotation->isMultiOption()) {
        $quotation->update(['quotation_type' => QuotationType::MultiOption->value]);
    }

    $quotation->variantOptions()->delete();

    $options = collect($validated['options'])->map(function ($option, $index) use ($quotation) {
        return $quotation->variantOptions()->create([...]);
    });

    // ... more logic
}
```

**Why It's Bad:**
- Can't reuse logic elsewhere (API v2, jobs, commands)
- Controller becomes untestable fat controller
- Business rules hidden in HTTP layer

**Good - Controller delegates to Service:**
```php
// Controller - thin, delegates
public function syncVariantOptions(Request $request, Quotation $quotation): JsonResponse
{
    $validated = $request->validate([...]);

    $options = $this->quotationService->syncVariantOptions(
        $quotation,
        $validated['options']
    );

    return response()->json(['data' => $options]);
}

// Service - business logic
public function syncVariantOptions(Quotation $quotation, array $options): Collection
{
    if (! $quotation->isMultiOption()) {
        $quotation->update(['quotation_type' => QuotationType::MultiOption->value]);
    }

    $quotation->variantOptions()->delete();

    return collect($options)->map(fn ($option, $index) =>
        $quotation->variantOptions()->create([...])
    );
}
```

#### 3c. Model Using Service Locator

**Bad - Quotation model calling app():**
```php
public function getFullNumber(): string
{
    return app(QuotationNumberGeneratorInterface::class)->getFullNumber($this);
}
```

**Why It's Bad:**
- Hidden dependency (not in constructor)
- Hard to mock in tests
- Model shouldn't know about services

**Good - Use Domain Factory or Accessor:**
```php
// Option A: Domain Factory (if transformation is complex)
$fullNumber = $this->domainFactory->getFullNumber($quotation);

// Option B: Simple accessor (if just formatting)
public function getFullNumber(): string
{
    return $this->quotation_number; // If no transformation needed
}

// Option C: API Resource (if only for API output)
// QuotationResource.php
public function toArray($request): array
{
    return [
        'full_number' => $this->numberGenerator->getFullNumber($this->resource),
    ];
}
```

---

## Detection Checklist

Use this during code review:

### God Service Detection
- [ ] More than 400 lines?
- [ ] More than 6 constructor dependencies?
- [ ] Methods that could be grouped by responsibility?
- [ ] Pass-through methods that just delegate?

### Business Logic Misplacement Detection
- [ ] Data access method has calculations (SUM, AVG, percentages)?
- [ ] Controller has more than validation/auth/response?
- [ ] Model uses `app()` helper?
- [ ] Domain Factory has proxy methods that just forward calls?

### Architectural Consistency Detection
- [ ] Is there infrastructure that's never used?
- [ ] Are similar features implemented differently?
- [ ] Are there multiple patterns for the same concern?

---

## Refactoring Priority

When you find these issues, prioritize:

| Priority | Issue | Effort | Impact |
|----------|-------|--------|--------|
| **High** | God Service in hot path | Medium | High - affects all devs |
| **High** | Business logic in controller | Low | High - reusability |
| **Medium** | Repository calculations | Low | Medium - testability |
| **Medium** | Service locator in model | Low | Medium - hidden deps |
| **Low** | Unused infrastructure | Low | Low - just confusing |

---

## Fixes Applied in Enter365

Tracking what was actually fixed:

### ✅ Phase 1 Complete

| Issue | File | Before | After |
|-------|------|--------|-------|
| **OperationContext dead code** | `BindOperationContext.php` | Zero controllers used it | Auto-bound via middleware |
| **Service locator in model** | `Quotation.php` | `app(Interface::class)` | Inline logic |
| **Proxy methods** | `QuotationDomainFactory.php` | 195 lines (9 proxies) | 119 lines |

### ✅ Phase 2 Complete

| Issue | File | Before | After |
|-------|------|--------|-------|
| **God Service** | `QuotationService.php` | 549 lines, 12 deps | 197 lines (coordinator), 4 deps |

**New focused services:**
- `QuotationCrudService.php` (241 lines) - create, update, delete, duplicate, revise
- `QuotationWorkflowService.php` (205 lines) - submit, approve, reject, cancel
- `QuotationStatisticsService.php` (67 lines) - statistics, dashboard

### All Phases Complete

All identified anti-patterns have been resolved. See [REFACTORING_HISTORY.md](REFACTORING_HISTORY.md) for detailed migration notes.

---

## Additional Anti-Patterns (Jan 2026 Review)

### 4. Duplicate Service Names

**Symptom:** Two classes with the same name in different folders doing different things.

**Real Example:**
```
app/Services/Sales/QuotationWorkflowService.php         # Mark won/lost
app/Services/Sales/Quotation/QuotationWorkflowService.php # State transitions
```

**Why It's Bad:**
- IDE may auto-import the wrong one
- `grep` shows both, confuses developers
- Future developer picks wrong class
- Silent bugs when wrong service is used

**Solution:** Rename to be distinct:
```
app/Services/Sales/QuotationOutcomeService.php          # Clear: outcomes
app/Services/Sales/Quotation/QuotationWorkflowService.php # Clear: workflow
```

**Detection:**
```bash
# Find duplicate class names
find app/Services -name "*.php" -exec basename {} \; | sort | uniq -d
```

---

### 5. Pattern Drift (Multiple Service Patterns) ✅ RESOLVED

**Status:** 100% Pattern A compliance achieved (Jan 2026). All 29 services now use `executeInTransaction()`.

**Previously (the problem):** Services used different base classes and transaction patterns:

| Service | Pattern | Base Class | Transaction |
|---------|---------|------------|-------------|
| `QuotationCrudService` | A (good) | BaseService + WithTransaction | `executeInTransaction()` |
| `PurchaseOrderService` | A (current) | BaseService + WithDocuments | `executeInTransaction()` |
| `QuotationConversionService` | A (current) | BaseService + WithTransaction | `executeInTransaction()` |

**The Fix Applied (Jan 2026):**

All services now follow BaseService + Traits pattern:
- Extend `BaseService` (all services)
- Use traits: `WithTransaction`, `WithEventDispatching`, `WithOperationContext`, `WithDocuments` (as needed)
- Use `executeInTransaction()` for all write operations
- Use `$this->getUserId()` instead of `auth()->id()`

**Services migrated in final phase:**
- `ProductService` (2 transactions)
- `InvoicePaymentService` (2 transactions)
- `DownPaymentService` (8 transactions)
- `BudgetService` (2 transactions)
- `YearEndCloseService` (2 transactions)
- `QuotationFollowUpService` (1 transaction)
- `CostOptimizationService` (1 transaction)

**Verification:**
```bash
# Should return 0 results (only BaseService.php and WithTransaction.php allowed)
grep -rn "DB::transaction" app/Services/ | grep -v "BaseService\|WithTransaction"
```

See: [SKILL.md - Pattern Commitment](SKILL.md#critical-pattern-commitment-read-first) and [REFACTORING_HISTORY.md](REFACTORING_HISTORY.md#p4-complete-pattern-a-migration-jan-2026)

---

### 6. Strategy Tight Coupling

**Symptom:** Strategy classes create other strategies with `new` internally.

**Real Example:**
```php
// PerpetualInventoryStrategy.php
public function calculateCOGS(Invoice $invoice): array
{
    // ❌ Creating strategy directly - can't mock, can't swap
    return (new HybridInventoryStrategy($this->journalService))
        ->calculateCOGS($invoice);
}
```

**Why It's Bad:**
- Violates Dependency Inversion Principle
- Can't mock the nested strategy in tests
- Can't swap implementations without code change
- Defeats purpose of Strategy pattern

**Solution:** Inject strategies via constructor:
```php
public function __construct(
    private JournalServiceInterface $journalService,
    private HybridInventoryStrategy $hybridStrategy,  // Injected
) {}

public function calculateCOGS(Invoice $invoice): array
{
    return $this->hybridStrategy->calculateCOGS($invoice);
}
```

**Detection:**
```bash
# Find strategies creating other strategies
grep -r "new.*Strategy" app/Services/Accounting/Strategies/
```

---

## Fixes Applied (Jan 2026)

All architectural issues resolved. Pattern A compliance at 100%.

| Priority | Issue | File | Status |
|----------|-------|------|--------|
| ~~**P0**~~ | ~~AbstractDocumentService `app()` fallback~~ | `AbstractDocumentService.php` | ✅ **FIXED** |
| ~~**P0**~~ | ~~BillService implicit deps~~ | All 6 document services | ✅ **FIXED** |
| ~~**P1**~~ | ~~Duplicate QuotationWorkflowService~~ | `app/Services/Sales/` | ✅ **FIXED** (renamed to QuotationOutcomeService) |
| ~~**P1**~~ | ~~Pattern C services~~ | QuotationOutcome, QuotationConversion | ✅ **FIXED** (migrated to Pattern A) |
| ~~**P2**~~ | ~~Strategy tight coupling~~ | `Strategies/Inventory/`, `Strategies/COGS/` | ✅ **FIXED** (inject via constructor) |
| ~~**P2**~~ | ~~FiscalPeriodService `app()` fallback~~ | `FiscalPeriodService.php` | ✅ **FIXED** |
| ~~**P2**~~ | ~~CI pattern compliance script~~ | `scripts/check-pattern-compliance.sh` | ✅ **CREATED** |
| ~~**P3**~~ | ~~Manufacturing services Pattern A~~ | `BomService`, `BomVariantGroupService` | ✅ **FIXED** |
| ~~**P4**~~ | ~~Complete Pattern A migration~~ | 7 remaining services | ✅ **FIXED** (18 transactions migrated) |

**Final State (Jan 2026):**
- All services extend `BaseService` with composable traits
- All services use `executeInTransaction()` for write operations (via `WithTransaction` trait)
- All services use `$this->getUserId()` instead of `auth()->id()` (via `WithOperationContext` trait)
- CI script prevents regression: `scripts/check-pattern-compliance.sh`

**Note:** `AbstractApplicationService` and `AbstractDocumentService` are deprecated. Use `BaseService + traits` instead.

See: [REFACTORING_HISTORY.md](REFACTORING_HISTORY.md) for detailed migration notes.

---

## Related Documentation

| Topic | File |
|-------|------|
| God Service fix | [ARCHITECTURE_PATTERNS.md](ARCHITECTURE_PATTERNS.md#coordinator-pattern-for-god-services) |
| Domain Factory | [ARCHITECTURE_PATTERNS.md](ARCHITECTURE_PATTERNS.md#domain-factory-pattern) |
| Data access patterns | [REPOSITORIES.md](REPOSITORIES.md) |
| Refactoring history | [REFACTORING_HISTORY.md](REFACTORING_HISTORY.md) |
| Gotcha #26 (Proxy methods) | [SKILL.md](SKILL.md#26-proxy-methods-add-no-value---use-state-machine-directly) |
| Gotcha #27 (Service locator) | [SKILL.md](SKILL.md#27-service-locator-in-models---replace-with-inline-logic) |
