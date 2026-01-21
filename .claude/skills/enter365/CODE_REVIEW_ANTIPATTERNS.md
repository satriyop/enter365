# Code Review Anti-Patterns

Lessons learned from code review analysis. These are the patterns that cause the most pain.

---

## Top 3 Code Smells

### 1. Architectural Schizophrenia

**Symptom:** Multiple patterns coexist without commitment.

| Pattern | Infrastructure Built | Actual Usage |
|---------|---------------------|--------------|
| Repository | 4 interfaces | 70 models use Eloquent directly |
| Domain Factory | QuotationDomainFactory | Only Quotation uses it |
| OperationContext | Full implementation | ✅ Fixed: Now uses middleware |

**Problem:** Developers see infrastructure that's never used. Confuses everyone.

**Solution:** Pick a lane and commit:
- Either use the pattern **everywhere** it applies
- Or **delete it** (YAGNI)

**Decision made for Enter365:**
- Repository: Hybrid approach (aggregates only, not all models)
- Domain Factory: For models with state machines
- OperationContext: Middleware auto-binding (complete)

---

### 2. God Services

**Symptom:** Service with 500+ lines, 8+ constructor dependencies, multiple responsibilities.

**Real Example - QuotationService (549 lines, 12 dependencies):**

```php
public function __construct(
    QuotationRepositoryInterface $repository,
    DocumentNumberGeneratorInterface $numberGenerator,
    EventDispatcherInterface $eventDispatcher,
    ContextualLoggerInterface $logger,
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

#### 3a. Repository Doing Business Logic

**Bad - EloquentQuotationRepository (40 lines of calculation):**
```php
public function getWinRateStats(?DateRange $range = null): array
{
    $results = DB::table('quotations')
        ->select(...)
        ->get();

    // ❌ Business calculation in repository
    $winRate = $decided > 0 ? round(($won / $decided) * 100, 2) : 0.0;

    return [...];
}
```

**Why It's Bad:**
- Repository should return **data**, not calculate business metrics
- Can't test calculation logic without database
- Mixes data access with business rules

**Good - Repository returns raw data, Service calculates:**
```php
// Repository - just data
public function getQuotationCountsByOutcome(?DateRange $range = null): array
{
    return DB::table('quotations')
        ->select('outcome')
        ->selectRaw('COUNT(*) as count')
        ->groupBy('outcome')
        ->get()
        ->keyBy('outcome')
        ->toArray();
}

// Analytics Service - business logic
public function getWinRateStats(?DateRange $range = null): array
{
    $rawStats = $this->repository->getQuotationCountsByOutcome($range);

    // ✅ Business calculation in service
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
- [ ] Repository has calculations (SUM, AVG, percentages)?
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

### Pending

| Issue | File | Status |
|-------|------|--------|
| **Business logic in repository** | `EloquentQuotationRepository.php` | Phase 3 - Move calculations to AnalyticsService |
| **Business logic in controller** | `QuotationController.php` | Phase 3 - Move to service |

See: `/plans/fixing/top-3-code-issues-remediation.md` for full plan.

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

### 5. Pattern Drift (Multiple Service Patterns)

**Symptom:** Services use different base classes and transaction patterns.

**Real Evidence from Codebase:**

| Service | Pattern | Base Class | Transaction |
|---------|---------|------------|-------------|
| `QuotationCrudService` | A (good) | AbstractApplicationService | `executeInTransaction()` |
| `PurchaseOrderService` | B (legacy) | AbstractDocumentService | Mixed |
| `QuotationConversionService` | C (bad) | None | Raw `DB::transaction()` |

**Why It's Bad:**
- Inconsistent logging (some ops logged, others not)
- Inconsistent error handling
- New devs don't know which pattern to follow
- Testing complexity varies by pattern

**Solution:** Commit to Pattern A for all new services.

See: [SKILL.md - Pattern Commitment](SKILL.md#critical-pattern-commitment-read-first)

**Migration for existing Pattern C services:**
```php
// BEFORE
class ConversionService
{
    public function convert(): Model
    {
        return DB::transaction(function () {
            // No logging
        });
    }
}

// AFTER
class ConversionService extends AbstractApplicationService
{
    public function convert(): Model
    {
        return $this->executeInTransaction('convert', function () {
            // Automatic logging, context
        });
    }
}
```

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

## Pending Fixes (Jan 2026)

| Priority | Issue | File | Status |
|----------|-------|------|--------|
| ~~**P0**~~ | ~~AbstractDocumentService `app()` fallback~~ | `AbstractDocumentService.php` | ✅ **FIXED** |
| ~~**P0**~~ | ~~BillService implicit deps~~ | All 6 document services | ✅ **FIXED** |
| ~~**P1**~~ | ~~Duplicate QuotationWorkflowService~~ | `app/Services/Sales/` | ✅ **FIXED** (renamed to QuotationOutcomeService) |
| ~~**P1**~~ | ~~Pattern C services~~ | QuotationOutcome, QuotationConversion | ✅ **FIXED** (migrated to Pattern A) |
| ~~**P2**~~ | ~~Strategy tight coupling~~ | `Strategies/Inventory/`, `Strategies/COGS/` | ✅ **FIXED** (inject via constructor) |
| ~~**P2**~~ | ~~FiscalPeriodService `app()` fallback~~ | `FiscalPeriodService.php` | ✅ **FIXED** |
| ~~**P2**~~ | ~~CI pattern compliance script~~ | `scripts/check-pattern-compliance.sh` | ✅ **CREATED** |

**P0 Root Cause Fixed (Jan 2026):**
- `AbstractDocumentService` now requires `EventDispatcherInterface` and `ContextualLoggerInterface`
- All 6 child services updated to pass required deps explicitly
- No more `app()` fallback in document service hierarchy

See: `/plans/fixing/pattern-drift-prevention.md` for full remediation plan.

---

## Related Documentation

| Topic | File |
|-------|------|
| God Service fix | [ARCHITECTURE_PATTERNS.md](ARCHITECTURE_PATTERNS.md#coordinator-pattern-for-god-services) |
| Domain Factory | [ARCHITECTURE_PATTERNS.md](ARCHITECTURE_PATTERNS.md#domain-factory-pattern) |
| Repository pattern | [REPOSITORIES.md](REPOSITORIES.md) |
| Remediation plan | `/plans/fixing/top-3-code-issues-remediation.md` |
| Pattern enforcement | `/plans/fixing/pattern-drift-prevention.md` |
| Gotcha #26 (Proxy methods) | [SKILL.md](SKILL.md#26-proxy-methods-add-no-value---use-state-machine-directly) |
| Gotcha #27 (Service locator) | [SKILL.md](SKILL.md#27-service-locator-in-models---replace-with-inline-logic) |
