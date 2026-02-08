# Enter365 Architecture Skill

Architecture, patterns, and gotchas for Enter365 - Indonesian SME ERP/Accounting system for electrical panel manufacturing and Solar EPC contracting.

## Trigger

Use when:
- Developing features in Enter365
- Debugging issues in this codebase
- Understanding the architecture
- Creating new services, models, or domain components

---

## CRITICAL: Pattern Commitment (Read First)

**All new services MUST extend `BaseService` and use traits for composable features.**

This is a project-wide commitment to prevent architectural drift. Do NOT deviate from this pattern.

### The Canonical Pattern (Current Architecture)

**All services extend `BaseService` and use traits:**

```php
use App\Services\Base\BaseService;
use App\Services\Base\Traits\WithTransaction;
use App\Services\Base\Traits\WithEventDispatching;
use App\Services\Base\Traits\WithOperationContext;

class MyService extends BaseService
{
    use WithTransaction;
    use WithEventDispatching;
    use WithOperationContext;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        // Domain-specific dependencies below...
        private MyDomainFactory $domainFactory,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    public function create(array $data): Model
    {
        return $this->executeInTransaction('create', function () use ($data) {
            $data['created_by'] = $this->getUserId();  // NOT auth()->id()
            return $this->repository->create($data);
        });
    }
}
```

### Pattern Requirements

| Requirement | Do This | NOT This |
|-------------|---------|----------|
| **Base class** | `extends BaseService` | `AbstractApplicationService` (deprecated) or plain class |
| **Traits** | Use `WithTransaction`, `WithEventDispatching`, `WithOperationContext` | Don't skip traits |
| **DI** | Constructor injection (explicit) | Optional params with `??= app()` fallback |
| **Transactions** | `$this->executeInTransaction()` | Raw `DB::transaction()` |
| **User context** | `$this->getUserId()` | `auth()->id()` |
| **Logging** | Automatic via base class | Manual or none |

### When to Use Which Traits

| Feature Needed | Trait to Use |
|----------------|--------------|
| **Database transactions** | `WithTransaction` |
| **Domain events** | `WithEventDispatching` |
| **User/tenant context** | `WithOperationContext` |
| **Document management** (Invoice, Bill, PO, etc.) | `WithDocuments` |

### Document Services Pattern

**For document services (Invoice, Bill, PO, DO, etc.):**

```php
use App\Services\Base\BaseService;
use App\Services\Base\Traits\WithDocuments;

class InvoiceService extends BaseService
{
    use WithDocuments;
    use WithTransaction;
    use WithEventDispatching;
    use WithOperationContext;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        // domain deps...
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    // Required abstract methods for WithDocuments trait
    protected function getDocumentNumberField(): string
    {
        return 'invoice_number';
    }

    protected function getDocumentNumberPrefix(): string
    {
        return 'INV-'.now()->format('Ym').'-';
    }

    protected function getDocumentNumberConfig(): array
    {
        return ['table' => 'invoices', 'column' => 'invoice_number'];
    }

    protected function getItemRelation(): string
    {
        return 'items';
    }

    public function create(array $data): Invoice
    {
        return $this->createDocument($data)->getDataOrFail();
    }
}
```

**Note:** `AbstractDocumentService` is deprecated. Use `BaseService + WithDocuments` trait instead.

### Anti-Patterns to Avoid

```php
// ❌ Pattern B - Optional dependencies with fallback (AVOID)
public function __construct(
    ?EventDispatcherInterface $eventDispatcher = null,  // Hidden dep!
) {
    $eventDispatcher ??= app(EventDispatcherInterface::class);  // Service locator
}

// ❌ Pattern C - Plain class with raw transaction (AVOID)
class MyService
{
    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $data['created_by'] = auth()->id();  // Hidden dep!
            // No logging, no context tracking
        });
    }
}
```

See: `/plans/fixing/pattern-drift-prevention.md` for full enforcement rules.

---

## Skill Index

This skill has detailed reference files for specific patterns:

### Architecture & Organization

| Skill File | Purpose |
|------------|---------|
| [FILE_ORGANIZATION.md](FILE_ORGANIZATION.md) | Directory structure, naming conventions, where to put new files |
| [SOLID_PRINCIPLES.md](SOLID_PRINCIPLES.md) | How SRP, OCP, LSP, ISP, DIP are applied in this codebase |
| [SERVICE_BINDINGS.md](SERVICE_BINDINGS.md) | All interface → implementation bindings in AppServiceProvider |
| [ARCHITECTURE_PATTERNS.md](ARCHITECTURE_PATTERNS.md) | OperationContext, Domain Factory pattern, Coordinator pattern |
| [CODE_REVIEW_ANTIPATTERNS.md](CODE_REVIEW_ANTIPATTERNS.md) | Top 3 code smells, detection checklist, refactoring priority |

### Domain Patterns

| Skill File | Purpose |
|------------|---------|
| [STATE_MACHINES.md](STATE_MACHINES.md) | 16 state machines with transitions, events, templates |
| [EVENTS.md](EVENTS.md) | 95 domain events, event dispatcher pattern, testing |
| [STRATEGIES.md](STRATEGIES.md) | Accounting strategies (COGS, Inventory, Manufacturing) |
| [VALUE_OBJECTS.md](VALUE_OBJECTS.md) | Money, Quantity, Percentage; Calculator patterns |
| [APPROVAL_PIPELINES.md](APPROVAL_PIPELINES.md) | Chain of responsibility for approvals |

### Data Layer

| Skill File | Purpose |
|------------|---------|
| [MODELS.md](MODELS.md) | 81 models, relationships, casts, scopes, templates |
| [REPOSITORIES.md](REPOSITORIES.md) | Repository pattern, domain queries, DB::table() for stats |
| [ENUMS.md](ENUMS.md) | DocumentStatus and domain-specific enums |
| [FACTORIES.md](FACTORIES.md) | Factory patterns and states for testing |

### API Layer

| Skill File | Purpose |
|------------|---------|
| [FORM_REQUESTS.md](FORM_REQUESTS.md) | Validation patterns, Indonesian messages |
| [RESOURCES.md](RESOURCES.md) | API resource transformation patterns |
| [NUMBER_GENERATION.md](NUMBER_GENERATION.md) | Document number formats (INV-202601-0001) |

### Development

| Skill File | Purpose |
|------------|---------|
| [TESTING_PATTERNS.md](TESTING_PATTERNS.md) | Pest testing patterns, service tests |
| [CONFIGURATION.md](CONFIGURATION.md) | Config files and environment variables |
| [EXCEPTION_CODES.md](EXCEPTION_CODES.md) | Error codes and exception handling |

---

## Architecture Overview

### Layer Structure

```
HTTP Layer (thin)
    ↓
Service Layer (business logic) ← 98 services
    ↓
Domain Layer (DDD patterns) ← StateMachines, Events, ValueObjects
    ↓
Contracts (interfaces) ← 52 interfaces
    ↓
Model Layer (Eloquent) ← 81 models
```

### Key Patterns

| Pattern | When to Use | Location |
|---------|-------------|----------|
| **StateMachine** | Document workflows with states | `app/Domain/{Domain}/{Entity}/` |
| **Service Layer** | All business logic | `app/Services/{Domain}/` |
| **Strategy** | Pluggable algorithms | `app/Contracts/*/Strategies/` |
| **Domain Events** | Decoupled side effects | `app/Domain/*/Events/` |
| **Query Filters** | API list endpoints | `app/Filters/` |
| **Form Requests** | Validation | `app/Http/Requests/Api/V1/` |

---

## Code Organization by Domain

| Domain | Models Location | Services Location | Domain Layer |
|--------|-----------------|-------------------|--------------|
| Sales | `app/Models/Sales/` | `app/Services/Sales/` | `app/Domain/Sales/` |
| Purchasing | `app/Models/Purchasing/` | `app/Services/Purchasing/` | `app/Domain/Purchasing/` |
| Manufacturing | `app/Models/Manufacturing/` | `app/Services/Manufacturing/` | - |
| Accounting | `app/Models/Accounting/` | `app/Services/Accounting/` | `app/Domain/Accounting/` |
| Inventory | `app/Models/Inventory/` | `app/Services/Inventory/` | - |
| Projects | `app/Models/Projects/` | `app/Services/Projects/` | `app/Domain/Projects/` |
| Solar | `app/Models/Solar/` | `app/Services/Solar/` | - |
| Tax | `app/Models/Tax/` | `app/Services/Tax/` | `app/Domain/Tax/` |

---

## Creating New Components

### New Service

1. Create interface: `app/Contracts/{Domain}/{Model}ServiceInterface.php`
2. Create service: `app/Services/{Domain}/{Model}Service.php`
3. Add binding in `AppServiceProvider::register()`
4. Create tests: `tests/Feature/{Domain}/{Model}ServiceTest.php`

Use existing skill: `/scaffold-service`

### New StateMachine

1. Create class extending `AbstractStateMachine`: `app/Domain/{Domain}/{Entity}/{Model}StateMachine.php`
2. Define `getTransitions()` - valid state flows
3. Create domain events: `app/Domain/{Domain}/{Entity}/Events/`
4. Register event listeners in `EventServiceProvider`
5. Use in service within `executeInTransaction()`

See: `/docs/07-code-patterns/state-machine-pattern.md`

### New Filter

1. Create filter extending `QueryFilter`: `app/Filters/{Model}Filter.php`
2. Add `Filterable` trait to model
3. Use traits: `HasDateRangeFilter`, `HasSearchFilter`, `HasStatusFilter`
4. Inject filter in controller

See: `/docs/07-code-patterns/filter-pattern.md`

### New API Endpoint

1. Create controller: `app/Http/Controllers/Api/V1/{Domain}/{Model}Controller.php`
2. Create form requests: `app/Http/Requests/Api/V1/{Domain}/Store{Model}Request.php`
3. Create resource: `app/Http/Resources/Api/V1/{Domain}/{Model}Resource.php`
4. Add routes in `routes/api.php`
5. Run `php artisan scramble:export --path=api.json`

Use existing skill: `/scaffold-api`

---

## Existing StateMachines

| StateMachine | States | Location |
|--------------|--------|----------|
| InvoiceStateMachine | Draft → Sent → Partial/Paid/Overdue → Cancelled | `app/Domain/Sales/Invoices/` |
| QuotationStateMachine | Draft → Submitted → Approved → Won/Lost/Expired | `app/Domain/Sales/Quotations/` |
| DeliveryOrderStateMachine | Draft → Confirmed → Shipped → Delivered | `app/Domain/Sales/DeliveryOrders/` |
| SalesReturnStateMachine | Draft → Submitted → Approved → Completed | `app/Domain/Sales/SalesReturns/` |
| BillStateMachine | Draft → Received → Partial/Paid | `app/Domain/Purchasing/Bills/` |
| PurchaseOrderStateMachine | Draft → Submitted → Approved → Partial/Received | `app/Domain/Purchasing/PurchaseOrders/` |
| PurchaseReturnStateMachine | Draft → Submitted → Approved → Completed | `app/Domain/Purchasing/PurchaseReturns/` |
| FiscalPeriodStateMachine | Open → Closing → Closed → Locked | `app/Domain/Accounting/FiscalPeriods/` |
| ProjectStateMachine | Draft → Active → OnHold → Completed | `app/Domain/Projects/` |
| WorkOrderStateMachine | Draft → Released → InProgress → Completed | `app/Domain/Manufacturing/WorkOrders/` |
| GoodsReceiptNoteStateMachine | Draft → Confirmed → Completed | `app/Domain/Purchasing/GoodsReceiptNotes/` |
| MaterialRequisitionStateMachine | Draft → Submitted → Approved → Issued | `app/Domain/Manufacturing/MaterialRequisitions/` |
| SubcontractorWorkOrderStateMachine | Draft → Submitted → Approved → InProgress → Completed | `app/Domain/Manufacturing/SubcontractorWorkOrders/` |
| StockOpnameStateMachine | Draft → Counting → Reviewed → Approved | `app/Domain/Inventory/StockOpnames/` |
| SolarProposalStateMachine | Draft → Submitted → Approved → Won/Lost | `app/Domain/Solar/Proposals/` |
| TaskStateMachine | Todo → InProgress → Done/Cancelled | `app/Domain/Projects/Tasks/` |

---

## Common Gotchas

### 0. Status Changes ONLY Through State Machines

**NEVER update status directly in database. ALWAYS use state machines.**

```php
// ❌ WRONG - Direct update
$invoice->status = DocumentStatus::Sent;
$invoice->save();

// ✓ CORRECT - Through state machine
$sm = InvoiceStateMachine::fromInvoice($invoice, $this->eventDispatcher);
$sm->transitionTo(DocumentStatus::Sent);
```

State machines ensure: validation, events, history, timestamps, user tracking.

See: [STATE_MACHINES.md](STATE_MACHINES.md) for full details.

### 1. Currency is Stored as Integer

All monetary values are stored as integers (cents/sen):
- `100_00` = Rp 100.00
- Use config `accounting.ppn_rate` (0.11) for tax calculations

### 2. Status Field Uses DocumentStatus Enum

```php
use App\Enums\DocumentStatus;

// Correct
$invoice->status = DocumentStatus::Draft;

// Wrong
$invoice->status = 'draft';
```

### 3. Services Must Use Interfaces

Controllers inject interfaces, not concrete services:

```php
// Correct
public function __construct(InvoiceServiceInterface $service)

// Wrong
public function __construct(InvoiceService $service)
```

### 4. All Business Logic in Services

Controllers only handle HTTP concerns:
- Validation (via Form Requests)
- Authorization (via Policies)
- Response transformation (via Resources)

### 5. StateMachine Transitions Fire Events

Always wrap state transitions in `executeInTransaction()`:

```php
return $this->executeInTransaction('send_invoice', function () use ($invoice) {
    $sm = InvoiceStateMachine::fromInvoice($invoice, $this->eventDispatcher);
    $sm->transitionTo(DocumentStatus::Sent);
    return $invoice->fresh();
}, ['invoice_id' => $invoice->id]);
```

### 6. Event Dispatcher for Testability

Inject `EventDispatcherInterface`, not direct `event()` calls:

```php
public function __construct(
    private EventDispatcherInterface $eventDispatcher
) {}

// Use NullEventDispatcher in unit tests
```

### 6b. Use getUserId() Not auth()->id() in Services ✅ ENFORCED

**Status:** All 54 violations eliminated. CI script prevents regression.

Services extending `BaseService` must use `$this->getUserId()`:

```php
// ❌ WRONG - Direct auth call (CI will fail)
$data['created_by'] = auth()->id();

// ✓ CORRECT - Via base class method (supports OperationContext)
$data['created_by'] = $this->getUserId();
```

**Verification:** `scripts/check-auth-id-usage.sh` runs in CI.

See: [ARCHITECTURE_PATTERNS.md](ARCHITECTURE_PATTERNS.md) for full OperationContext documentation.

### 7. Indonesian Error Messages

Use Indonesian for user-facing messages:

```php
throw StateTransitionException::actionNotAvailable(
    'kirim',  // Indonesian verb
    'draft',
    'Faktur tidak memiliki item.'  // Indonesian message
);
```

### 8. Always Run Pint Before Commit

```bash
vendor/bin/pint --dirty
```

### 9. Tests Use Pest with describe/it

```php
describe('InvoiceService', function () {
    describe('create', function () {
        it('creates invoice with draft status', function () {
            // ...
        });
    });
});
```

### 10. API Responses Use Resources

Never return models directly:

```php
// Correct
return InvoiceResource::make($invoice);

// Wrong
return response()->json($invoice);
```

### 11. Enum Status After Model Create Needs Fresh

When services set status using `DocumentStatus::Draft` enum but models check with string constants, the in-memory model won't match after `create()`:

```php
// Problem: Service creates with enum
$opname = StockOpname::create([
    'status' => DocumentStatus::Draft,  // Enum object
]);

// Model checks with string
public function isDraft(): bool {
    return $this->status === self::STATUS_DRAFT;  // 'draft' string
}

// Fix: Refresh to load string value from DB
$opname = $opname->fresh();
$opname->isDraft();  // Now works ✓
```

**Affected models:** `StockOpname`, potentially others without enum cast on status.

### 12. Always Verify Schema Before Writing Seeders

Use `mcp__laravel-boost__database-schema` to check actual column names:

| Don't Assume | Verify First |
|--------------|--------------|
| `project_costs.amount` | Actually: `quantity`, `unit`, `unit_cost` |
| `boms.is_active` | Actually: `status = 'approved'` |
| `product_stocks.quantity_available` | Actually: `quantity` |

### 13. Service Method Data Contracts

Always check what data structure service methods expect by reading the model's `$fillable`:

```php
// Wrong - assumed 'amount' field
$projectService->addCost($project, [
    'cost_type' => 'material',
    'amount' => 200_000_000,  // ✗ Column doesn't exist
]);

// Correct - matches ProjectCost::$fillable
$projectService->addCost($project, [
    'cost_type' => 'material',
    'quantity' => 1,
    'unit' => 'lot',
    'unit_cost' => 200_000_000,  // ✓
]);
```

### 14. COGS Strategy: Manufacturing vs Trading

The `COGSOnInvoiceStrategy` uses `product.purchase_price` for cost basis:

- **Trading business:** COGS works because products have purchase prices ✓
- **Manufacturing business:** COGS = 0 because finished goods have `purchase_price = 0`

For manufacturing, COGS comes from:
- Work Order material consumption (via `MaterialRequisition`)
- BOM cost calculation
- NOT from invoice posting

```php
// In COGSOnInvoiceStrategy::calculateCOGS()
$unitCost = $product->purchase_price ?? 0;  // 0 for finished goods!
```

### 15. Decimal Cast Returns String, Not Float

Laravel's `decimal:N` cast returns **strings**, not floats. This causes TypeErrors when passing to typed methods:

```php
// Model cast
protected function casts(): array
{
    return [
        'tax_rate' => 'decimal:2',      // Returns "11.00" string
        'discount_value' => 'decimal:2', // Returns "100.00" string
    ];
}

// ❌ WRONG - TypeError: must be of type float, string given
$calculator->calculate($lineTotals, $quotation->tax_rate);

// ✅ CORRECT - Explicit cast
$calculator->calculate(
    $lineTotals,
    (float) ($quotation->tax_rate ?? 0),
    (float) ($quotation->discount_value ?? 0),
);
```

**Affected fields:** Any model field with `decimal:N` cast passed to typed calculator methods.

See: [ARCHITECTURE_PATTERNS.md](ARCHITECTURE_PATTERNS.md#gotcha-decimal-cast-returns-string)

### 16. Domain Factory for Mutations, Model for Reads

Use **Domain Factory** for mutations (state transitions, calculations), keep **Model** for read-only checks:

```php
// ❌ WRONG - Model mutation (events may not fire)
$quotation->calculateTotals();  // Removed

// ✅ CORRECT - Factory mutation (proper DI)
$this->domainFactory->applyTotals($quotation);
$quotation->save();

// ✅ OK - Model read-only check (no mutation needed)
if ($quotation->isEditable()) { ... }
```

See: [ARCHITECTURE_PATTERNS.md](ARCHITECTURE_PATTERNS.md#domain-factory-pattern)

### 17. Use DB::table() for Dashboard/Report Aggregations

In dashboard and report services, use `DB::table()` for statistics and aggregations to avoid Eloquent hydration overhead:

```php
// ❌ SLOW - Hydrates all models just to count
$stats = Quotation::where('status', 'approved')
    ->get()
    ->groupBy('outcome')
    ->map->count();

// ✅ FAST - Direct SQL aggregation
$stats = DB::table('quotations')
    ->select('status')
    ->selectRaw('COUNT(*) as count')
    ->selectRaw('SUM(total) as total_value')
    ->groupBy('status')
    ->get();
```

**Use DB::table() for:** Aggregations (SUM, COUNT, AVG), dashboards, reports with 100+ rows.

**Use Eloquent for:** CRUD operations needing events, casts, mutators.

### 18. Model Field Naming - Don't Guess

Always verify actual field names via schema or model `$fillable`. Common mistakes:

| Wrong (Assumed) | Correct (Actual) | Model |
|-----------------|------------------|-------|
| `revision_number` | `revision` | Quotation |
| `bom_id` | `source_bom_id` | Quotation |
| `amount` | `quantity * unit_cost` | ProjectCost |
| `is_active` | `status = 'active'` | Bom |

**Always verify with:**
```bash
# Check schema
mcp__laravel-boost__database-schema --filter=quotations

# Or read model $fillable
grep -A 30 'fillable' app/Models/Sales/Quotation.php
```

### 19. Pre-Prod: Don't Overcomplicate with Backward Compatibility

For fresh development apps without production data:

| Don't Do | Do Instead |
|----------|------------|
| Feature flags for service migration | Direct replacement |
| Conditional service bindings | Single binding |
| Old + new service coexisting | Delete old, rename new |
| Backward compatibility shims | Clean refactor |

Feature flags and gradual rollout only make sense when:
- Production data needs migration
- Multiple teams need coordination
- Rollback strategy is critical

For pre-prod, just replace directly—it's cleaner and simpler.

### 20. EntityNotFoundException - Use Constructor, Not Static Factory

`EntityNotFoundException` uses a constructor, not a static factory method:

```php
// ❌ WRONG - Static method doesn't exist
throw EntityNotFoundException::forEntity('Quotation', $id);

// ✅ CORRECT - Use constructor
throw new EntityNotFoundException('Quotation', $id);
```

### 21. DateRange Value Object Expects String, Not Carbon

`DateRange::of()` and `DateRange::contains()` expect `CarbonImmutable|string`, not Laravel's `Carbon`:

```php
use App\Domain\Shared\ValueObjects\DateRange;

// ❌ WRONG - Carbon instance
$range = DateRange::of(now()->subDays(7), now());

// ✅ CORRECT - String dates
$range = DateRange::of(
    now()->subDays(7)->toDateString(),
    now()->toDateString()
);

// ❌ WRONG - Passing Carbon to contains()
$range->contains($model->created_at);  // Carbon instance

// ✅ CORRECT - Convert to string first
$dateString = $model->created_at instanceof \DateTimeInterface
    ? $model->created_at->format('Y-m-d')
    : (string) $model->created_at;
$range->contains($dateString);
```

### 22. God Services - Split Using Coordinator Pattern

Services with 500+ lines and 8+ dependencies should be split. Use Coordinator Pattern:

```php
// ❌ BAD - God Service (627 lines)
class BrandSwapService
{
    public function __construct(/* 8 dependencies */) {}
    public function previewSwapBrand() { }  // Read-only
    public function compareBrands() { }     // Read-only
    public function swapBomBrand() { }      // Write
    public function quickSwapItem() { }     // Write
}

// ✅ GOOD - Coordinator + Focused Services
// BrandSwapService.php (124 lines) - Thin coordinator
// BrandSwap/BrandSwapPreviewService.php (310 lines) - Read-only
// BrandSwap/BrandSwapExecutionService.php (342 lines) - Write ops
```

**When to split:** Different dependency needs, 500+ lines, distinct responsibility groups.

**When to keep:** Single cohesive workflow, uses Strategy pattern, methods interdependent.

See: [ARCHITECTURE_PATTERNS.md](ARCHITECTURE_PATTERNS.md#coordinator-pattern-for-god-services)

### 23. Events Are for Side Effects, NOT Core Operations

**Never use events for operations that must be transactionally consistent.**

```php
// ❌ WRONG - Event-driven inventory (breaks consistency)
public function complete(GoodsReceiptNote $grn): GoodsReceiptNote
{
    return DB::transaction(function () use ($grn) {
        $grn->transitionTo(DocumentStatus::Completed);
        // Event listener runs OUTSIDE transaction - if it fails, GRN is "Completed" but stock unchanged!
        $this->eventDispatcher->dispatch(new GoodsReceived($grn));
        return $grn;
    });
}

// ✅ CORRECT - Direct call within transaction
public function complete(GoodsReceiptNote $grn): GoodsReceiptNote
{
    return DB::transaction(function () use ($grn) {
        // Core operation - within transaction
        $this->inventoryService->stockIn(...);
        $grn->transitionTo(DocumentStatus::Completed);

        // Side effect - OK if it fails (audit, notification)
        $this->eventDispatcher->dispatch(new GoodsReceiptCompleted($grn));
        return $grn;
    });
}
```

**Decision matrix:**

| Operation | Use Events? | Why |
|-----------|-------------|-----|
| Audit logging | ✅ Yes | Failure shouldn't block business |
| Notifications | ✅ Yes | Can retry independently |
| **Inventory movements** | ❌ No | Must be atomic with document |
| **Journal entries** | ❌ No | Financial data needs consistency |

See: [EVENTS.md](EVENTS.md#when-not-to-use-events-️)

### 24. OperationContext Uses Middleware - Controllers Do Nothing

**OperationContext is auto-bound via middleware.** Controllers should NOT call `withContext()`.

```php
// ❌ WRONG - Unnecessary ceremony
public function store(Request $request): JsonResponse
{
    $invoice = $this->invoiceService
        ->withContext(OperationContext::fromAuth())  // Not needed!
        ->create($request->validated());
}

// ✅ CORRECT - Middleware handles it
public function store(Request $request): JsonResponse
{
    $invoice = $this->invoiceService->create($request->validated());
    return new InvoiceResource($invoice);
}
```

**How it works:**
1. `BindOperationContext` middleware binds context to container
2. `BaseService::getContext()` resolves from container
3. Controllers don't need to do anything

**When to use explicit `withContext()`:**
- Queue jobs (no middleware in queue context)
- Artisan commands
- Tests (or bind to container instead)

See: [ARCHITECTURE_PATTERNS.md](ARCHITECTURE_PATTERNS.md#operationcontext-pattern)

### 25. Top 3 Code Smells to Avoid

From code review analysis - these are the most damaging patterns:

| Smell | Symptom | Fix |
|-------|---------|-----|
| **God Service** | 500+ lines, 8+ deps, multiple responsibilities | Split using Coordinator Pattern |
| **Business Logic in Repository** | Calculations, transformations in repo | Move to Service or Analytics class |
| **Business Logic in Controller** | More than validation/auth/response | Extract to Service layer |

**Quick test for God Service:**
- Can you describe what it does in one sentence?
- Does it have methods that don't share dependencies?
- Would splitting reduce test setup complexity?

If any answer is "yes" → consider splitting.

See: [CODE_REVIEW_ANTIPATTERNS.md](CODE_REVIEW_ANTIPATTERNS.md)

### 26. Proxy Methods Add No Value - Use State Machine Directly

**Don't create proxy methods that just delegate.** They add indirection without benefit.

```php
// ❌ BAD - QuotationDomainFactory had 9 proxy methods like this:
class QuotationDomainFactory
{
    public function canEdit(Quotation $quotation): bool
    {
        return $this->stateMachine($quotation)->canEdit();  // Just forwarding!
    }
    // 8 more identical proxy methods...
}

// Then called via:
$this->domainFactory->canEdit($quotation);

// ✅ GOOD - Use state machine directly:
$this->domainFactory->stateMachine($quotation)->canEdit();
```

**Why proxy methods are bad:**
- Extra indirection without value
- Bloats the class (195 lines → 119 lines after removal)
- Creates maintenance burden - update in two places
- Hides the actual API from developers

**When proxy methods ARE useful:**
- When adding behavior (logging, caching, validation)
- When providing a simpler API for complex operations
- When adapter pattern is needed

**Fix applied:** Removed 9 proxy methods from `QuotationDomainFactory.php`, updated callers to use `stateMachine()` directly.

### 27. Service Locator in Models - Replace with Inline Logic

**Models should NOT call `app()` to get services.** This creates hidden dependencies.

```php
// ❌ BAD - Quotation model had this:
public function getFullNumber(): string
{
    return app(QuotationNumberGeneratorInterface::class)->getFullNumber($this);
}

// Problems:
// - Hidden dependency (not in constructor)
// - Hard to mock in tests
// - Model shouldn't know about services
// - Breaks dependency injection principle

// ✅ GOOD - Inline logic if simple:
public function getFullNumber(): string
{
    if ($this->revision > 0) {
        return "{$this->quotation_number}-R{$this->revision}";
    }
    return $this->quotation_number;
}

// ✅ GOOD - Use Domain Factory if complex:
// In service:
$fullNumber = $this->domainFactory->getFullNumber($quotation);

// ✅ GOOD - Use API Resource if only for API:
// In QuotationResource.php:
'full_number' => $this->numberGenerator->getFullNumber($this->resource),
```

**Decision matrix:**

| Logic Complexity | Where to Put |
|-----------------|--------------|
| Simple formatting | Inline in model accessor |
| Needs dependencies | Domain Factory or Service |
| Only for API output | API Resource |

**Fix applied:** Replaced `app()` call in `Quotation::getFullNumber()` with inline logic.

### 28. Coordinator Pattern - withContext() Must Propagate

**When using Coordinator Pattern, withContext() must propagate to sub-services.**

```php
// ❌ BAD - Context not propagated to sub-services
class QuotationService implements QuotationServiceInterface
{
    public function __construct(
        private QuotationCrudService $crud,
        private QuotationWorkflowService $workflow,
    ) {}

    // Missing withContext()! Tests using context will fail.
}

// ✅ GOOD - Context propagated to sub-services
class QuotationService implements QuotationServiceInterface
{
    public function __construct(
        private QuotationCrudService $crud,
        private QuotationWorkflowService $workflow,
    ) {}

    public function withContext(OperationContext $context): static
    {
        $clone = clone $this;
        $clone->crud = $this->crud->withContext($context);
        $clone->workflow = $this->workflow->withContext($context);
        return $clone;
    }
}
```

**Why it matters:**
- Tests using `$service->withContext($context)->create(...)` expect context to be used
- Sub-services extend `BaseService` which has `withContext()`
- Without propagation, sub-services use container-resolved context (may be different)

**Coordinator pattern checklist:**
- [ ] Coordinator implements interface (backward compatibility)
- [ ] Coordinator is plain class (no BaseService extension)
- [ ] withContext() clones and propagates to sub-services
- [ ] No new DI bindings needed (Laravel auto-resolves)

See: [ARCHITECTURE_PATTERNS.md](ARCHITECTURE_PATTERNS.md#real-world-example-quotationservice-refactoring)

### 29. All Services Must Use executeInTransaction() ✅ COMPLETE

**Status:** 100% compliance achieved (Jan 2026). All 29 services now use Pattern A.

All write operations in services MUST use `executeInTransaction()` - never raw `DB::transaction()`:

```php
// ❌ WRONG - Raw transaction (no logging, no context)
public function create(array $data): Model
{
    return DB::transaction(function () use ($data) {
        return Model::create($data);
    });
}

// ✅ CORRECT - executeInTransaction (automatic logging, context)
public function create(array $data): Model
{
    return $this->executeInTransaction('create', function () use ($data) {
        $data['created_by'] = $this->getUserId();
        return Model::create($data);
    }, ['model_type' => 'SomeModel']);
}
```

**Benefits of executeInTransaction():**

| Aspect | Raw `DB::transaction()` | `executeInTransaction()` |
|--------|------------------------|--------------------------|
| **Logging** | None | Automatic operation name + context |
| **Timing** | None | Records execution duration |
| **User context** | Manual `auth()->id()` | Automatic via `$this->getUserId()` |
| **Error handling** | Basic | Standardized with context |
| **Debugging** | Hard | Easy (logs show what ran) |

**Verification:**
```bash
# Should return 0 results (only BaseService allowed)
grep -rn "DB::transaction" app/Services/ | grep -v "BaseService"
```

See: [REFACTORING_HISTORY.md](REFACTORING_HISTORY.md#p4-complete-pattern-a-migration-jan-2026)

---

## Indonesian Business Context

| Term | English | Notes |
|------|---------|-------|
| Faktur | Invoice | Sales invoice |
| Penawaran | Quotation | Sales quote |
| Tagihan | Bill | Vendor/purchase bill |
| Surat Jalan | Delivery Order | DO |
| Pesanan Pembelian | Purchase Order | PO |
| Perintah Kerja | Work Order | WO |
| PPN | VAT | 11% rate |
| NPWP | Tax ID | 15-digit format |

See: `/docs/GLOSSARY.md`

---

## Documentation Reference

| Need | Document |
|------|----------|
| Quick start | `/docs/00-getting-started/quick-start.md` |
| Architecture | `/docs/01-architecture/domain-layer.md` |
| Sales domain | `/docs/02-domain/sales-cycle.md` |
| StateMachine pattern | `/docs/07-code-patterns/state-machine-pattern.md` |
| Strategy pattern | `/docs/07-code-patterns/strategy-pattern.md` |
| Event pattern | `/docs/07-code-patterns/event-listener-pattern.md` |
| Filter pattern | `/docs/07-code-patterns/filter-pattern.md` |
| Service pattern | `/docs/07-code-patterns/service-pattern.md` |
| ADRs (why decisions) | `/docs/08-adr/` |

---

## Useful Commands

```bash
# Run specific tests
php artisan test --filter=InvoiceService

# Format code
vendor/bin/pint --dirty

# Type checking
./scripts/phpstan-check.sh app/Services/YourService.php

# API contract validation (after modifying API Resources)
./scripts/check-api-integration.sh

# Generate API docs (automated in check-api-integration.sh)
php artisan scramble:export --path=api.json

# Check API contract mismatches
php check-api-mismatches.php

# List routes
php artisan route:list --path=api/v1

# Database schema
php artisan db:show

# Tinker
php artisan tinker
```

---

## API Contract Validation Workflow

**IMPORTANT:** After modifying API Resources or Controllers:

1. **Run automated integration check:**
   ```bash
   ./scripts/check-api-integration.sh
   ```
   This validates:
   - OpenAPI schema generation
   - Resource vs Schema mismatches
   - PHPStan type checking
   - API tests

2. **Pre-commit hook** automatically validates API contracts when API files are modified

3. **CI/CD** validates on every PR (GitHub Actions)

**Field Naming Standards:**
- Use `_amount` suffix for monetary values: `total_amount`, `discount_amount`, `tax_amount`
- Be consistent across all Resources
- Database column names should match Resource field names

**Documentation:**
- See `docs/04-api/integration-check/` for complete workflow
- See `docs/04-api/tools/` for Scramble and PHPStan guides
