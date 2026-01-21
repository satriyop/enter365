# Architecture Patterns & Debt Reference

Patterns implemented to address architectural issues, and anti-patterns to avoid.

---

## OperationContext Pattern

### Problem: Global State via `auth()->id()`

Services calling `auth()->id()` directly creates:
- **Testability nightmare**: Can't unit test without mocking Laravel auth
- **Hidden dependency**: Not visible in constructor
- **Silent bugs**: Returns `null` in CLI/queue context

**Bad Pattern (avoid):**
```php
class QuotationConversionService
{
    public function convertToInvoice(Quotation $quotation): Invoice
    {
        $invoice = Invoice::create([
            'created_by' => auth()->id(),  // Hidden dependency!
        ]);
    }
}
```

### Solution: OperationContext (Laravel Way - Middleware)

`OperationContext` is an immutable value object that encapsulates user context.

**The Laravel Way:** Middleware binds context to container, services resolve automatically.

**Key Files:**
- `app/Support/OperationContext.php` - Value object
- `app/Http/Middleware/BindOperationContext.php` - Auto-binds for all HTTP requests
- `app/Services/Base/AbstractApplicationService.php` - Resolves from container

### How It Works

```
HTTP Request
    ↓
BindOperationContext Middleware
    ↓
app()->scoped(OperationContext::class, $context)
    ↓
Controller (does NOTHING - zero ceremony)
    ↓
Service calls $this->getContext()
    ↓
Resolves from container automatically
```

**Controllers stay clean:**
```php
// No withContext() calls needed - middleware handles it
public function store(StoreQuotationRequest $request): JsonResponse
{
    $quotation = $this->quotationService->create($request->validated());
    return new QuotationResource($quotation);
}
```

**Services resolve automatically:**
```php
class InvoiceService extends AbstractApplicationService
{
    public function create(array $data): Invoice
    {
        $data['created_by'] = $this->getUserId();  // Auto-resolved from container
        // ...
    }
}
```

### Container Resolution Order

`AbstractApplicationService::getContext()` uses this priority:

```php
protected function getContext(): OperationContext
{
    // 1. Explicit context (tests, jobs)
    if ($this->operationContext !== null) {
        return $this->operationContext;
    }

    // 2. Container binding (middleware - automatic for HTTP)
    if (app()->bound(OperationContext::class)) {
        return app(OperationContext::class);
    }

    // 3. Fallback (shouldn't happen if middleware registered)
    return OperationContext::fromAuth();
}
```

### Usage by Context

| Context | How to Set | Example |
|---------|------------|---------|
| **HTTP Requests** | Automatic (middleware) | Controllers do nothing |
| **Queue Jobs** | Explicit `withContext()` | `$service->withContext(OperationContext::forJob($userId))` |
| **Artisan Commands** | Explicit `withContext()` | `$service->withContext(OperationContext::forCommand('sync'))` |
| **Tests** | Bind to container OR `withContext()` | See testing section below |

### Multi-Tenant Ready

OperationContext includes `tenantId` property for future multi-tenant support:

```php
final class OperationContext
{
    public function __construct(
        public readonly ?int $userId,
        public readonly ?int $tenantId = null,  // Ready for multi-tenant
        public readonly ?string $ipAddress = null,
        public readonly ?DateTimeInterface $timestamp = null,
        public readonly array $metadata = [],
    ) {}
}
```

**When multi-tenant infrastructure is ready:**

```php
// app/Http/Middleware/BindOperationContext.php
private function resolveTenantId(Request $request): ?int
{
    return auth()->user()?->tenant_id;  // Uncomment when ready
}
```

Services can then use:
```php
$tenantId = $this->getTenantId();        // May be null
$tenantId = $this->requireTenantId();    // Throws if null
```

### Testing with OperationContext

**Option 1: Bind to Container (Recommended)**
```php
it('creates invoice for specific user', function () {
    $this->app->scoped(
        OperationContext::class,
        fn () => OperationContext::forUser(42)
    );

    $invoice = $this->invoiceService->create($data);

    expect($invoice->created_by)->toBe(42);
});
```

**Option 2: Explicit withContext()**
```php
it('creates invoice for specific user', function () {
    $invoice = $this->invoiceService
        ->withContext(OperationContext::forUser(42))
        ->create($data);

    expect($invoice->created_by)->toBe(42);
});
```

**Option 3: Use TestsWithOperationContext Trait**
```php
uses(TestsWithOperationContext::class);

it('creates with specific user', function () {
    $invoice = $this->withUserContext($this->invoiceService, 42)->create($data);
    expect($invoice->created_by)->toBe(42);
});
```

### Factory Methods

| Method | When to Use |
|--------|-------------|
| `OperationContext::fromAuth()` | Fallback only (middleware handles HTTP) |
| `OperationContext::forUser($id)` | Tests, impersonation |
| `OperationContext::system()` | Jobs, commands with no user |
| `OperationContext::forJob($userId, $jobName)` | Queue workers |
| `OperationContext::forCommand($name)` | Artisan commands |

### Queue Job Pattern

Jobs must explicitly pass context (no middleware in queue):

```php
class ProcessInvoiceJob implements ShouldQueue
{
    public function __construct(
        public Invoice $invoice,
        public ?int $userId,
    ) {}

    public function handle(InvoiceService $service): void
    {
        $context = $this->userId
            ? OperationContext::forUser($this->userId)
            : OperationContext::system();

        $service->withContext($context)->process($this->invoice);
    }
}

// Dispatching
ProcessInvoiceJob::dispatch($invoice, auth()->id());
```

---

## Interface Contract Completeness

### Problem: Interface Doesn't Match Implementation

When interfaces are incomplete, they provide false confidence:

```php
// Interface says:
interface QuotationServiceInterface
{
    public function create(array $data): Quotation;
    public function approve(Quotation $quotation): Quotation;
    // Missing: cancel(), markAsSent()
}

// But implementation has:
class QuotationService implements QuotationServiceInterface
{
    public function cancel(...) { }      // Not in interface!
    public function markAsSent(...) { }  // Not in interface!
}
```

**Problems:**
- Can't mock `cancel()` in tests via interface
- IDE autocomplete shows incomplete contract
- Violates Interface Segregation Principle

### Solution: Keep Interfaces in Sync

**Rule:** Every public method in a service MUST be declared in its interface.

**Checklist when adding service methods:**
1. Add method signature to interface first
2. Implement in service
3. Update SERVICE_BINDINGS.md if new interface

**Finding mismatches:**
```bash
# Compare public methods
grep "public function" app/Services/Sales/QuotationService.php
grep "public function" app/Contracts/Sales/QuotationServiceInterface.php
```

---

## Identified Architectural Debt

### 1. God Models ✅ SOLVED

**Symptom:** Models with 500+ lines, business logic methods, service instantiation.

**Solution: Domain Factory Pattern**

See [Domain Factory Pattern](#domain-factory-pattern) below for the implementation.

**Result:** Quotation model reduced from 735 → 650 lines (~12% reduction)

### 2. Inconsistent Service Layer ✅ RESOLVED

**Symptom:** Two patterns coexisted:
- Pattern A: Services extend `AbstractDocumentService`
- Pattern B: Standalone services with different structure

**Services using AbstractDocumentService:**
- `InvoiceService`
- `BillService`
- `DeliveryOrderService`
- `SalesReturnService`
- `PurchaseOrderService`
- `PurchaseReturnService`
- `QuotationService` ✅ (migrated Jan 2026)

**Specialized services (acceptable exceptions):**
- `QuotationConversionService` - Single-purpose conversion logic

**Migration Notes (QuotationService):**
- Now extends `AbstractDocumentService`
- Uses `QuotationRepositoryInterface` for data access
- Uses `QuotationDomainFactory` for state machine and calculations
- Key dependencies: `QuotationNumberGeneratorInterface`, `QuotationDefaults`, `QuotationItemCreator`

### 3. Direct `auth()->id()` Calls ✅ RESOLVED

**Status:** All 54 `auth()->id()` violations eliminated from 24 services (Jan 2026).

**Solution Applied:**
- All services now extend `AbstractApplicationService`
- Use `$this->getUserId()` which respects `OperationContext`
- CI check script prevents regression: `scripts/check-auth-id-usage.sh`

**Verification:** `grep -r "auth()->id()" app/Services/` returns 0 results.

### 4. God Services ✅ RESOLVED

**Symptom:** Services with 500+ lines handling multiple responsibilities.

**Solution: Coordinator Pattern** (see below)

**Applied to:**
- `BrandSwapService` (627 → 124 lines) - Split into Preview + Execution services

**Kept as-is (architectural decision):**
- `YearEndCloseService` (544 lines) - Well-structured orchestrator using Strategy pattern

---

## Best Practices Reference

### Creating New Services

1. **Always create interface first:**
   ```
   app/Contracts/{Domain}/{Model}ServiceInterface.php
   ```

2. **Extend appropriate base class:**
   - Document services: `AbstractDocumentService`
   - Other services: `AbstractApplicationService`

3. **Use constructor injection:**
   ```php
   public function __construct(
       private EventDispatcherInterface $eventDispatcher,
       private ContextualLoggerInterface $logger,
       // domain-specific dependencies...
   ) {
       parent::__construct($eventDispatcher, $logger);
   }
   ```

4. **Use `getUserId()` not `auth()->id()`:**
   ```php
   $data['created_by'] = $this->getUserId();
   ```

5. **Register binding in AppServiceProvider**

6. **Update SERVICE_BINDINGS.md**

### Testing Services

Use `TestsWithOperationContext` trait for explicit user context:

```php
uses(TestsWithOperationContext::class);

it('creates with specific user', function () {
    $quotation = $this->withUserContext($service, 42)->create($data);
    expect($quotation->created_by)->toBe(42);
});
```

---

## Domain Factory Pattern

### Problem: God Models with Service Locator

Models calling `app()` to get dependencies creates:
- **Hidden dependencies**: Not visible, can't be mocked
- **Testability issues**: Relies on Laravel container
- **Mixed responsibilities**: Model does mutation logic

**Bad Pattern (avoid):**
```php
class Quotation extends Model
{
    public function calculateTotals(): self
    {
        // ❌ Service locator inside model
        $calculator = app(QuotationCalculatorInterface::class);
        $totals = $calculator->calculate(...);
        $this->subtotal = $totals->subtotal;
        $this->save();
        return $this;
    }

    public function stateMachine(): QuotationStateMachine
    {
        // ❌ Creating without event dispatcher - events won't fire!
        return QuotationStateMachine::fromQuotation($this);
    }
}
```

### Solution: Domain Factory

Create a factory class that holds dependencies and creates domain objects.

**Location:** `app/Domain/{Domain}/{Entity}/{Entity}DomainFactory.php`

**Implementation:**

```php
class QuotationDomainFactory
{
    private ?OutcomeManager $outcomeManager = null;
    private ?FollowUpManager $followUpManager = null;

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private QuotationCalculatorInterface $calculator,
    ) {}

    // State machine WITH event dispatcher (for mutations)
    public function stateMachine(Quotation $quotation): QuotationStateMachine
    {
        return QuotationStateMachine::fromQuotation($quotation, $this->eventDispatcher);
    }

    // Managers (cached within factory lifetime)
    public function outcomeManager(): OutcomeManager
    {
        return $this->outcomeManager ??= new OutcomeManager;
    }

    // Calculations
    public function calculateTotals(Quotation $quotation): QuotationTotals
    {
        return $this->calculator->calculate(...);
    }

    public function applyTotals(Quotation $quotation): Quotation
    {
        $totals = $this->calculateTotals($quotation);
        $quotation->subtotal = $totals->subtotal;
        // ... other fields
        return $quotation;  // Not saved - caller decides
    }

    // Convenience methods for state checks
    public function canSubmit(Quotation $quotation): bool
    {
        return $this->stateMachine($quotation)->canSubmit();
    }
}
```

**Service Usage:**

```php
class QuotationService
{
    public function __construct(
        private QuotationDomainFactory $domainFactory,
    ) {}

    public function submit(Quotation $quotation): Quotation
    {
        $stateMachine = $this->domainFactory->stateMachine($quotation);

        if (! $stateMachine->canSubmit()) {
            throw new InvalidArgumentException('Cannot submit');
        }

        $stateMachine->transitionTo(DocumentStatus::Submitted);
        return $quotation->fresh();
    }

    public function create(array $data): Quotation
    {
        // ...
        $this->domainFactory->applyTotals($quotation);
        $quotation->save();
        return $quotation;
    }
}
```

**Registration (singleton):**

```php
// AppServiceProvider::register()
$this->app->singleton(QuotationDomainFactory::class);
```

### What Stays in the Model

Models keep **read-only accessors** that don't require dependencies:

```php
class Quotation extends Model
{
    // ✅ Read-only state machine (for checks, not mutations)
    public function stateMachine(): QuotationStateMachine
    {
        // Note: No event dispatcher = read-only checks only
        return QuotationStateMachine::fromQuotation($this);
    }

    // ✅ Simple boolean checks
    public function isEditable(): bool
    {
        return $this->stateMachine()->canEdit();
    }

    // ✅ Computed accessors without dependencies
    public function needsFollowUp(): bool
    {
        return $this->next_follow_up_at !== null
            && $this->next_follow_up_at->isPast();
    }
}
```

### Gotcha: Decimal Cast Returns String

Laravel's `decimal` cast returns strings, but calculators expect floats:

```php
// ❌ WRONG - Will throw TypeError
$totals = $calculator->calculate($lineTotals, $quotation->tax_rate);
// TypeError: Argument #2 ($taxRate) must be of type float, string given

// ✅ CORRECT - Explicit cast
$totals = $calculator->calculate(
    $lineTotals,
    (float) ($quotation->tax_rate ?? 0),
    $quotation->discount_type,
    (float) ($quotation->discount_value ?? 0),
);
```

### Benefits

| Before (God Model) | After (Domain Factory) |
|--------------------|------------------------|
| Model has 735 lines | Model has 650 lines |
| Hidden `app()` calls | Explicit constructor injection |
| Events may not fire | Events always fire via factory |
| Hard to test mutations | Easy to mock factory |
| Mixed read/write logic | Clear separation |

---

## Coordinator Pattern for God Services

### Problem: God Services

Services with 500+ lines handling multiple responsibilities:
- **Cognitive load**: Hard to understand what the service does
- **Testing friction**: Must set up everything to test one behavior
- **Merge conflicts**: Multiple devs touching same large file
- **Constructor hell**: 8+ dependencies

**Symptoms of a God Service:**
```php
class BrandSwapService  // 627 lines
{
    public function __construct(
        // 8 dependencies...
    ) {}

    // Read-only operations
    public function previewSwapBrand(...) { }
    public function compareBrands(...) { }
    public function getItemAlternatives(...) { }

    // Write operations
    public function swapBomBrand(...) { }
    public function generateBrandVariants(...) { }
    public function quickSwapItem(...) { }
}
```

### Solution: Coordinator + Focused Services

Split into focused services, keep thin coordinator for backward compatibility.

**Directory Structure:**
```
app/Services/Manufacturing/
├── BrandSwapService.php              # 124 lines - Coordinator
└── BrandSwap/
    ├── BrandSwapPreviewService.php   # 310 lines - Read-only
    └── BrandSwapExecutionService.php # 342 lines - Write ops
```

**Coordinator (Thin Facade):**

```php
class BrandSwapService extends AbstractApplicationService
{
    public function __construct(
        private BrandSwapPreviewService $previewService,
        private BrandSwapExecutionService $executionService,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    // Delegates to focused services - maintains backward compatibility
    public function previewSwapBrand(Bom $bom, string $targetBrand, ...): array
    {
        return $this->previewService->previewSwapBrand($bom, $targetBrand, ...);
    }

    public function swapBomBrand(Bom $bom, string $targetBrand, ...): array
    {
        return $this->executionService->swapBomBrand($bom, $targetBrand, ...);
    }
}
```

**Focused Preview Service (Read-Only):**

```php
class BrandSwapPreviewService
{
    public function __construct(
        private SpecValidationService $validationService
    ) {}

    public function previewSwapBrand(Bom $bom, string $targetBrand, ...): array { }
    public function compareBrands(Bom $bom): array { }
    public function previewItemSwap(BomItem $item, string $targetBrand): array { }
    public function getItemAlternatives(BomItem $item): array { }
}
```

**Focused Execution Service (Write Operations):**

```php
class BrandSwapExecutionService extends AbstractApplicationService
{
    public function __construct(
        private ProductEquivalenceService $equivalenceService,
        private BomVariantGroupService $variantGroupService,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    public function swapBomBrand(...): array { }
    public function generateBrandVariants(...): array { }
    public function quickSwapItem(...): array { }
}
```

### When to Split vs Keep

**Split when:**
- Service has distinct responsibility groups (preview vs execute, validate vs process)
- 500+ lines with 8+ constructor dependencies
- Different parts of service have different dependency needs
- Testing requires mocking unrelated dependencies

**Keep as-is when:**
- All methods relate to single cohesive workflow
- Already uses Strategy pattern (like `YearEndCloseService`)
- Methods are genuinely interdependent
- Splitting would scatter related code

### Splitting Checklist

1. **Identify responsibility groups** - Read-only vs write, validation vs execution
2. **Create subdirectory** - `app/Services/{Domain}/{Service}/`
3. **Extract focused services** - Move methods with their dependencies
4. **Slim coordinator** - Keep public API, delegate to focused services
5. **Verify tests pass** - Existing tests should work without changes
6. **No new DI bindings needed** - Laravel auto-resolves concrete classes

### Benefits

| Before (God Service) | After (Coordinator + Focused) |
|---------------------|-------------------------------|
| 627 lines, 8 deps | 124 + 310 + 342 lines, 2-4 deps each |
| Hard to test preview logic | Test preview service in isolation |
| Changes risk breaking unrelated code | Changes scoped to specific service |
| One reason to change → many | Each service has single responsibility |

---

## Real-World Example: QuotationService Refactoring

The QuotationService was refactored from a God Service (549 lines, 12 dependencies) to a Coordinator Pattern.

### Before (God Service)

```php
class QuotationService extends AbstractDocumentService implements QuotationServiceInterface
{
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
        QuotationConversionService $conversionService  // 11 dependencies!
    ) {}

    // 549 lines of mixed responsibilities:
    // - CRUD: create, update, delete
    // - Workflow: submit, approve, reject, cancel
    // - Lifecycle: markAsSent, markExpired
    // - Statistics: getStatistics
    // - Conversion: convertToInvoice, revise, duplicate
}
```

### After (Coordinator + Focused Services)

**Directory Structure:**
```
app/Services/Sales/
├── QuotationService.php               # 197 lines - Coordinator (4 deps)
├── QuotationConversionService.php     # Already existed
└── Quotation/
    ├── QuotationCrudService.php       # 241 lines - create, update, delete (7 deps)
    ├── QuotationWorkflowService.php   # 205 lines - submit, approve, reject (4 deps)
    └── QuotationStatisticsService.php #  67 lines - statistics (1 dep)
```

**Coordinator (Thin Facade):**

```php
class QuotationService implements QuotationServiceInterface
{
    public function __construct(
        private QuotationCrudService $crud,
        private QuotationWorkflowService $workflow,
        private QuotationStatisticsService $statistics,
        private QuotationConversionService $conversion,
    ) {}

    // IMPORTANT: Propagate context to sub-services
    public function withContext(OperationContext $context): static
    {
        $clone = clone $this;
        $clone->crud = $this->crud->withContext($context);
        $clone->workflow = $this->workflow->withContext($context);
        return $clone;
    }

    // Delegates - maintains backward compatibility
    public function create(array $data, User|int|null $user = null): Quotation
    {
        return $this->crud->create($data, $user);
    }

    public function submit(Quotation $quotation, ?int $userId = null): Quotation
    {
        return $this->workflow->submit($quotation, $userId);
    }

    public function getStatistics(?string $startDate = null, ?string $endDate = null): array
    {
        return $this->statistics->get($startDate, $endDate);
    }
}
```

**Focused Services extend AbstractApplicationService:**

```php
class QuotationCrudService extends AbstractApplicationService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        private QuotationRepositoryInterface $repository,
        private QuotationNumberGeneratorInterface $quotationNumberGenerator,
        private QuotationDefaults $defaults,
        private QuotationItemCreator $itemCreator,
        private QuotationDomainFactory $domainFactory,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    // Only CRUD methods here
    public function create(array $data, User|int|null $user = null): Quotation { }
    public function update(Quotation $quotation, array $data): Quotation { }
    public function delete(Quotation $quotation): bool { }
}
```

### Results

| Metric | Before | After |
|--------|--------|-------|
| Coordinator lines | 549 | **197** |
| Coordinator dependencies | 12 | **4** |
| Max service lines | - | **241** (CrudService) |
| Max service dependencies | - | **7** (CrudService) |
| Tests passing | 321 | **321** ✅ |

### Key Learnings

1. **Coordinator doesn't extend AbstractApplicationService** - It's just a plain class that delegates
2. **withContext() must propagate** - Clone coordinator and set context on sub-services
3. **Interface unchanged** - All existing code using QuotationServiceInterface works
4. **No new bindings needed** - Laravel auto-resolves the focused services
5. **Direct use allowed** - Can inject QuotationCrudService directly when appropriate

---

## Related Documentation

| Topic | File |
|-------|------|
| All service bindings | [SERVICE_BINDINGS.md](SERVICE_BINDINGS.md) |
| Testing patterns | [TESTING_PATTERNS.md](TESTING_PATTERNS.md) |
| SOLID principles | [SOLID_PRINCIPLES.md](SOLID_PRINCIPLES.md) |
| Implementation plan | `/plans/fixing/architecture-debt-refactoring.md` |
