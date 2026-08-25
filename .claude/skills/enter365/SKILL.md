# Enter365 Architecture Skill

Architecture, patterns, and gotchas for Enter365 — Indonesian SME ERP (Odoo-like core: Sales, Purchase, Inventory, Accounting, Manufacturing, Projects) with optional industry add-ons: solar EPC (`solar_proposals` / NEX) and electrical panel tools (`electrical_panel` / Vahana).

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
| Manufacturing (generic pack) | `app/Models/Manufacturing/` | `app/Services/Manufacturing/` | - |
| Accounting | `app/Models/Accounting/` | `app/Services/Accounting/` | `app/Domain/Accounting/` |
| Inventory | `app/Models/Inventory/` | `app/Services/Inventory/` | - |
| Projects | `app/Models/Projects/` | `app/Services/Projects/` | `app/Domain/Projects/` |
| **ElectricalPanel** (Vahana add-on) | `app/Models/ElectricalPanel/` | `app/Services/ElectricalPanel/` | - |
| **Solar** (NEX add-on) | `app/Models/Solar/` | `app/Services/Solar/` | - |
| Tax | `app/Models/Tax/` | `app/Services/Tax/` | `app/Domain/Tax/` |

**Isolation rule:** BrandSwap, ComponentStandard, SpecValidation, cost optimization, and panel meta tables live **only** under ElectricalPanel / `routes/addons/electrical_panel.php`. Core Manufacturing must not import or name those packages. Extend core BOM resources via `App\Support\AddonExtensions`.

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
5. Custom filter methods must be `public` — kebab-case query params dispatch them. See [Gotcha #30](#30-queryfilter-method_exists-is-not-an-allowlist).

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
// ❌ BAD - God Service (627 lines) — still industry add-on, NOT Manufacturing core
// namespace App\Services\ElectricalPanel;
class BrandSwapService
{
    public function __construct(/* 8 dependencies */) {}
    public function previewSwapBrand() { }  // Read-only
    public function compareBrands() { }     // Read-only
    public function swapBomBrand() { }      // Write
    public function quickSwapItem() { }     // Write
}

// ✅ GOOD - Coordinator + Focused Services under ElectricalPanel only
// app/Services/ElectricalPanel/BrandSwapService.php (124 lines) - Thin coordinator
// app/Services/ElectricalPanel/BrandSwap/BrandSwapPreviewService.php (310 lines)
// app/Services/ElectricalPanel/BrandSwap/BrandSwapExecutionService.php (342 lines)
//
// ❌ NEVER re-create under app/Services/Manufacturing/
```

**When to split:** Different dependency needs, 500+ lines, distinct responsibility groups.

**When to keep:** Single cohesive workflow, uses Strategy pattern, methods interdependent.

See: [ARCHITECTURE_PATTERNS.md](ARCHITECTURE_PATTERNS.md#coordinator-pattern-for-god-services)
See also: `tasks/artifact/feature-preset-and-industry-addons-map.md`

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

### 30. QueryFilter `method_exists` Is Not an Allowlist

**Context:** Every list endpoint runs `Model::query()->filter($filter)`. Request keys are camelCased and invoked as methods on the filter.

**Problem:** `method_exists()` is true for private/protected methods, and inside the class those are callable. Public methods on `QueryFilter` itself (`apply`, `applySorting`, getters) also match. `?apply=1` TypeErrors (500). `?keyword=` used to emit MySQL-only `REGEXP`, which 500s on both SQLite tests and PostgreSQL production; unescaped input is also a ReDoS vector. Unvalidated `direction` makes `orderBy()` throw instead of 422.

**Solution:** Do not restore `method_exists` in `QueryFilter::shouldApplyFilter()`. Keep reflection: public, non-static, ≤1 required arg, declared **below** `QueryFilter` (trait methods count as the using class). `include` is the only base-class request filter. Allowlist `direction` to `asc`/`desc`. `HasSearchFilter::keyword()` must stay driver-aware (`~*` / `REGEXP` / space-padded `LIKE`) and must `preg_quote` the term.

```php
// ❌ BAD — infrastructure methods become query params
return method_exists($this, $method) && $value !== null && $value !== '';

// ✅ GOOD — already in QueryFilter::shouldApplyFilter()
$reflection = new ReflectionMethod($this, $method);
if (! $reflection->isPublic() || $reflection->isStatic()) {
    return false;
}
if ($reflection->getDeclaringClass()->getName() === self::class && $method !== 'include') {
    return false;
}
```

**When adding a filter method:** `public function foo($value)` is reachable as `?foo=`. Keep helpers `protected`. Do not add public methods that are not filters.

**Tests:** `tests/Unit/Filters/QueryFilterTest.php`, `tests/Feature/Filters/QueryFilterDispatchTest.php`

### 31. Stock Opname Applies Frozen Variance as a Delta Through Costing

**Context:** Approving a stock opname while sales/receipts continue (POS-first). ADR-0049: all quantity/cost writes go through `InventoryServiceInterface` + `CostingStrategy`.

**Problem:** `approve()` passed `counted_quantity` into `adjust()` as an absolute target. Intervening sales were overwritten (system 40 → sold 15 → approve count 38 set stock to 38 instead of 23). `adjust()` wrote `product_stocks` directly and skipped FIFO layers. The JE summed `items.variance_value` (count-time worksheet), so Inventory GL and stock valuation drifted.

**Solution:** Apply the frozen snapshot variance: `InventoryService::adjustByDelta($varianceQuantity, $systemCost)`. `CostingStrategy::recordAdjustment()` owns quantity and layers. `HybridInventoryStrategy::onStockAdjustment()` journals from the movements just written, not from `variance_value`. Worksheet `variance_value` is a preview only.

```php
// ❌ BAD — absolute counted qty; bypasses costing; JE from worksheet
$this->inventoryService->adjust($product, $warehouse, $item->counted_quantity, ...);
$this->inventoryStrategy->onStockAdjustment($opname); // sums items.variance_value

// ✅ GOOD
$this->inventoryService->adjustByDelta($product, $warehouse, $item->variance_quantity, $item->system_cost, ...);
$this->inventoryStrategy->onStockAdjustment($opname); // sums adjustment movements
```

**Do not** restore a silent FIFO fallback when layers cannot cover quantity — that hides a desync. Throw.

**Tests:** `tests/Feature/Services/Inventory/StockOpnameServiceTest.php`, `tests/Feature/Services/Inventory/Costing/CostingStrategyTest.php`

### 32. The Ledger Is Journal Lines — Never `accounts.opening_balance` or `is_active`

**Context:** Trial balance, balance sheet, income statement, equity statement, general ledger, bank book balance.

**Problem:** Balances were `opening_balance` (a fillable API field with no offsetting entry) plus posted movements. `PATCH /accounts/{id}` with `opening_balance` invented money and unbalanced the TB. Reports also loaded `where('is_active', true)`, so deactivating an account with posted movements dropped one side of the TB.

**Solution:** Posted journal lines are the only ledger. Remove `opening_balance` from `$fillable` and FormRequests. Do not add the column into `getBalance()`, trial balance, or reports. Opening capital is a balanced `source_type = opening` JE (year-end close already posts these). `is_active` hides accounts from pickers, never from the ledger. Compare `entry_date` with `<= $asOfDate.' 23:59:59'` so datetime values on the as-of day are included.

```php
// ❌ BAD
$accounts = Account::where('is_active', true)->get();
$balance = $account->opening_balance + $netMovement;

// ✅ GOOD
$accounts = Account::query()->whereIn('id', $postedAccountIds)->get();
$balance = $netMovement; // posted JE lines only
```

**Tests:** `tests/Feature/Services/Accounting/AccountBalanceServiceTest.php`

### 33. `reverseEntry()` Is a State Transition — Lock, Honour the Reason, Post Into an Open Period

**Context:** Every void (POS, invoice, payment, DO, SR) calls `JournalEntryService::reverseEntry($entry, $reason)`.

**Problem:** The `$description` argument was overwritten with a restatement of the original JE, so the GL never stored *why*. Guards ran outside the transaction with no `lockForUpdate()`, so two concurrent voids could reverse twice. The reversal reused the original `entry_date`, so a void after period close failed entirely.

**Solution:** Inside `executeInTransaction()`, lock the original row, keep a non-empty caller reason, and post the reversal on the original date only if that fiscal period is still Open; otherwise post today into `FiscalPeriod::current()`. Missing/closed current period is an error.

```php
// ❌ BAD
$description = 'Reversal of '.$entry->entry_number.': '.$entry->description;

// ✅ GOOD
$description = filled($description) ? trim($description) : 'Reversal of '.$entry->entry_number.': '.$entry->description;
```

**Tests:** `tests/Feature/Services/Accounting/JournalServiceTest.php` (reverseEntry)

### 34. POS Session Close Must Journal Cash Over/Short

**Context:** `PosService::closeSession()`. Sales already debit Kas for every cash tender. `cash_difference_amount = counted - expected` is stored on the session.

**Problem:** The difference never posted. A short drawer left GL Kas at the expected amount — books claimed cash the till did not have.

**Solution:** When the difference is non-zero, post a balanced JE: shortage `Dr Selisih Kas (5-2910) / Cr Kas`; overage the reverse. `source_type` is `PosSession`. Skip the JE when counted equals expected. Opening float and end-of-shift deposit are not extra JEs while till cash is the same `Kas` account as sales — they need a till-vs-safe split first.

**Tests:** `tests/Feature/Pos/PosServiceTest.php`

### 35. Credit Notes Are Not Cash — Use `credited_amount`

**Context:** Approving a sales return credits AR. Invoice `paid_amount` is cash collected.

**Problem:** The SR journal handler did `$invoice->paid_amount += $sr->total_amount` with no lock, no cap, and no status transition. Collection reports treated credit notes as cash. State-machine Paid guards also checked `paid_amount` only, so a fully returned invoice stayed Sent/Overdue and kept getting dunned.

**Solution:** `invoices.credited_amount`. Outstanding = total − paid − credited. `InvoicePaymentStatusService::applyCreditNote()` / `reverseCreditNote()` lock the invoice, cap at outstanding, then `updatePaymentStatus()`. InvoiceStateMachine Paid/Partial/Sent guards use `getSettledAmount()` / `getOutstandingAmount()`, not `paid_amount` alone.

**Tests:** `tests/Feature/Services/Sales/SalesReturnServiceTest.php`

### 36. Completing a Receipt Locks the Document; Inventory Cost Is Net of Discount, Exclusive of Tax

**Context:** `GoodsReceiptNoteService::complete()` writes stock and PO `quantity_received`. GRN `unit_price` is tax-exclusive; `calculateLineTotal()` adds PPN after the line discount.

**Problem:** `canComplete()` ran outside the transaction with no `lockForUpdate()` on the GRN, so a retried Complete posted `stockIn()` twice. `PurchaseOrderItem::receive()` did `$this->quantity_received += $qty` on a stale model. `stockIn(..., $item->unit_price)` ignored the discount and, if anyone treated list price as tax-inclusive, would capitalise PPN into inventory. The perpetual GRNI journal summed `qty * unit_price` the same way, so Inventory GL diverged from stock valuation.

**Solution:** Lock the GRN inside `executeInTransaction()`, then re-check `canComplete()`. `receive()` locks the PO line and `increment()`s in SQL. Capitalise with `GoodsReceiptNoteItem::inventoryTotalCost()` / `inventoryUnitCost()` (after discount, no tax). Perpetual `onGoodsReceived()` journals that same net amount.

```php
// ❌ BAD
if (! $grn->stateMachine()->canComplete()) { throw ...; }
DB::transaction(function () use ($grn) {
    $this->inventoryService->stockIn($product, $wh, $qty, $item->unit_price, ...);
    $poItem->quantity_received += $qty; // stale in-memory
});

// ✅ GOOD
return $this->executeInTransaction('complete', function () use ($grn, $userId) {
    $grn = GoodsReceiptNote::query()->lockForUpdate()->findOrFail($grn->id);
    if (! $grn->stateMachine()->canComplete()) { throw ...; }
    $this->inventoryService->stockIn($product, $wh, $qty, $item->inventoryUnitCost(), ...);
    $poItem->receive($qty); // lock + increment
});
```

**Tests:** `tests/Feature/Services/Purchasing/GoodsReceiptNoteServiceTest.php` (Completion integrity, PurchaseOrderItem::receive)

### 37. `recordStockOut()` Returns Exact Total Cost — Never `qty × round(total/qty)`

**Context:** FIFO layers store integer `unit_cost`. An issue that spans layers (or uneven sen) has a true total that does not divide evenly.

**Problem:** `FIFOCostingStrategy::recordStockOut()` returned `round($totalCost / $quantity)`. Callers then wrote `total_cost = quantity * unitCost`. 3 units costing Rp 1,000 became unit 333 and COGS 999 — Inventory GL and the FIFO sub-ledger permanently diverge. POS COGS reads `abs($movement->total_cost)`.

**Solution:** The costing contract returns the exact integer `$totalCost`. Movements persist that total. Display `unit_cost` may be `round(total / qty)`. Negative `recordAdjustment()` is `-recordStockOut()`, not `-(qty * roundedUnit)`. **Inbound is the same seam:** `stockIn(..., totalCost:)` / `recordStockIn(..., $totalCost)` split the remainder onto the last FIFO unit so 3 units costing 1000 restock as 2×333+1×334, not 3×333. Void a sale with `abs($movement->total_cost)`, never `$movement->unit_cost`. Transfers pass the outbound total into the destination layers.

```php
// ❌ BAD
return (int) round($totalCost / $quantity);
$movement->total_cost = $quantity * $unitCost;
$this->inventoryService->stockIn($product, $wh, $qty, $movement->unit_cost, ...);

// ✅ GOOD
return $totalCost;
$movement->total_cost = $totalCost;
$this->inventoryService->stockIn($product, $wh, $qty, $unitCost, totalCost: abs($movement->total_cost));
```

**Tests:** `tests/Feature/Services/Inventory/Costing/CostingStrategyTest.php`, `tests/Feature/Pos/PosServiceTest.php` (uneven FIFO void)

### 38. Inventory Locks: Product Cache, Warehouse Order, Unique Retry, Free Stock

**Context:** Every stock mutation goes through `ProductStock::lockForStock()` and `Product::syncCurrentStock()`.

**Problem:** `lockForStock()` ended in `first()` (nullable vs `: self`) and `firstOrCreate` raced the unique `(product_id, warehouse_id)` index. `transfer()` locked from-warehouse then to-warehouse (AB-BA deadlock) and checked `quantity` instead of `getAvailableQuantity()`, so reserved stock could be transferred away. `syncCurrentStock()` summed unlocked warehouse rows — two outlets selling the same SKU both wrote a stale SUM.

**Solution:** `lockForStock()` retries unique collisions and never returns null. Transfers lock warehouses by ascending `id` and validate free stock. `syncCurrentStock()` `lockForUpdate()`s the product row then writes `SUM(product_stocks.quantity)`. Deadlock-prone transfers pass `DB::transaction($callback, 3)`.

**Tests:** `tests/Feature/Services/Inventory/InventoryServiceTest.php`, `tests/Feature/Services/Inventory/InventoryServiceReservationTest.php`

`getMovementSummary()` must aggregate in SQL (`COUNT`/`SUM` by type). Do not `get()` every movement and filter in PHP. Count transfers as `TYPE_TRANSFER_OUT` globally, and as the matching IN or OUT leg when filtered by warehouse — never `count / 2` (warehouse filters yield 0.5). Reverse shipment / sales-return restock must pass `totalCost: abs($outMovement->total_cost)` into `stockIn()`, same as POS void.

### 39. Journals Need an Open Period; Lines Are Signed and Exclusive

**Context:** `JournalEntryService::createEntry()` / `postEntry()`. POS already refused a missing period; the rest of the app did not.

**Problem:** No matching fiscal period stored `fiscal_period_id = null`. Period-scoped reports and year-end skipped the entry while the trial balance included it. Lines allowed `debit` and `credit` both non-zero, or negatives, so `isBalanced()` could pass while `SUM(debit)` on the TB was garbage.

**Solution:** `FiscalPeriod::assertOpenForPosting($date)` — missing / closed / locked is an error. Use it from journal create/post and POS tills. `JournalEntryLine::saving` rejects negatives and both-sides lines. PostgreSQL CHECK `journal_entry_lines_signed_exclusive` matches the model guard (SQLite has no ALTER CHECK — the model is the suite's net).

```php
// ❌ BAD
$period = FiscalPeriod::forDate($date);
$entry->fiscal_period_id = $period?->id; // null is fine

// ✅ GOOD
$period = FiscalPeriod::assertOpenForPosting($date);
$entry->fiscal_period_id = $period->id;
```

**Tests:** `tests/Feature/Services/Accounting/JournalServiceTest.php`

### 40. POS Session Methods Are Owner-Scoped; Holds Are Taken, Not Copied; QRIS Is Clearing

**Context:** Every session-scoped POS HTTP method. `takeHold()`. `openSession()`. QRIS tender.

**Problem:** `show()` and `catalog()` checked permission only — any cashier could read another till's sales and stock. `takeHold()` `replicate()`d then deleted, so retries returned `id: null` and a lost response destroyed the cart. Two open sessions per user were allowed (no partial unique index), and reopening silently reused the wrong warehouse. QRIS defaulted to the bank/kas-kecil code, so T+1 QRIS receipts inflated Bank on sale date.

**Solution:** `assertOwnSession()` on every session-scoped endpoint (admin may inspect). Mark `taken_at` instead of deleting holds; retries return the same id. Lock the cashier's open row; unique index `pos_sessions_one_open_per_user` where `status = 'open'`. If an open session exists at another warehouse, throw. Snapshot `qris` from `1-1112` Piutang QRIS — settlement/MDR is a later JE, not a sale-time Bank debit.

**Tests:** `tests/Feature/Pos/PosServiceTest.php`, `tests/Feature/Pos/PosOwnerJourneyTest.php`

### 41. POS Till Polls Must Not Dump the Shift; Zero Price Is Not Free Stock; SKU Is Not a Path

**Context:** `GET /pos/sessions/current` (till poll), `buildLines()`, `catalogImageUrl()`.

**Problem:** `current()` / `show()` eager-loaded every `sales.items` and `sales.tenders` for the shift — hundreds of receipts on a poll. `buildLines()` allowed `$unit <= 0`, so a tracked SKU with no selling price stocked out and posted Rp 0 revenue. Catalog concatenated the raw SKU into `public_path('pos/kopitiam/'.$sku.'.jpg')`, so `../` left that directory.

**Solution:** Session resources load warehouse + holds only. List receipts from `GET /pos/sessions/{id}/sales` (paginated). Throw `BusinessRuleException` naming the product when unit price is not positive — no movement. `catalogImageUrl()` uses `basename()`, allowlists `[A-Za-z0-9._-]`, and requires the resolved file to stay under `public/pos/kopitiam`.

**Tests:** `tests/Feature/Pos/PosCheckoutHttpTest.php`, `tests/Feature/Pos/PosServiceTest.php` (zero-priced checkout)

### 42. POS Inclusive Button Is What the Customer Pays; Catalogue Exclusive Is GL Revenue

**Context:** Inclusive kopitiam prices. `selling_price` is exclusive. `selling_price_with_tax` is `round(exclusive × 1.11)`. Checkout used to extract DPP from that rounded button (`round(payable / 1.11)`).

**Problem:** Exclusive 9,091 → button 10,091 → extracted DPP 9,090. The JE still balanced (`tax = total − base`), but a sales-at-list-price report never tied to GL revenue.

**Solution:** Payable stays the rounded button. DPP is `selling_price × qty`. PPN is the residual (`payable − dpp`) so the JE still balances and revenue equals the catalogue exclusive. Do not extract DPP from the button. Test warehouses are `is_test`, not `code LIKE 'WH-E2E-%'`. Checkout idempotency rows are `MassPrunable` after 24h.

```php
// ❌ BAD
$dpp = round($payable / 1.11);
$ppn = $payable - $dpp; // 9091 list becomes 9090 revenue

// ✅ GOOD
$dpp = (int) $product->selling_price * $qty;
$ppn = $payable - $dpp;
```

**Tests:** `tests/Feature/Pos/PosServiceTest.php` (catalogue DPP, prune idempotency), `tests/Feature/Pos/PosCheckoutHttpTest.php` (is_test outlets)

### 43. Fiscal Period `status` Is Canonical — Booleans Are Derived

**Context:** `fiscal_periods.status` (Open / Locked / Closing / Closed) plus legacy `is_closed` / `is_locked`. Payments, journals, and `FiscalPeriod::current()` used to pick one or the other.

**Problem:** `StorePaymentRequest` only rejected a period that *existed* and had `is_closed` / `is_locked`. A missing period was a silent pass — the HTTP layer re-opened [ACC-01](#39-journals-need-an-open-period-lines-are-signed-and-exclusive). `current()` queried `is_closed = false`, so a **Locked** year still counted as current. Direct updates to only the booleans desynced from the enum.

**Solution:** `status` is the source of truth. A `saving` hook rewrites the booleans from the enum. `current()` is `status = Open`. HTTP payment validation calls `FiscalPeriod::assertOpenForPosting()` — missing / closed / locked is a 422 on `payment_date`.

```php
// ❌ BAD — missing period is fine; locked looks "current"
if ($period && $period->is_closed) { ... }
FiscalPeriod::query()->where('is_closed', false)->first();

// ✅ GOOD
FiscalPeriod::assertOpenForPosting($date);
FiscalPeriod::query()->where('status', FiscalPeriodStatus::Open)->first();
```

**Tests:** `tests/Feature/Api/V1/PaymentApiTest.php`, `tests/Feature/Services/Accounting/FiscalPeriodServiceTest.php`

### 44. `removeStock()` Must Not Clamp — Valuation Shows Every Non-Zero Row

**Context:** FIFO/WA costing writes on-hand through `ProductStock::removeStock()`. `getStockValuation()` is the stock-vs-GL report.

**Problem:** `max(0, qty - requested)` swallowed an oversell and Weighted Average still returned `requested × average_cost` for units that never left. Valuation hid `quantity <= 0`, so negative on-hand (the rows an accountant needs) disappeared.

**Solution:** Throw when requested > on-hand. Show any row with non-zero quantity **or** non-zero `total_value`. `startCounting()` batch-loads `product_stocks` for the opname — do not query per item.

**Tests:** `tests/Feature/Services/Inventory/InventoryServiceTest.php`, `tests/Feature/Services/Inventory/Costing/CostingStrategyTest.php`

### 45. Invoice Void Collects Child Ids First; Audit Counts This Operation

**Context:** `InvoiceVoidService::void()` cancels DOs and SRs, then writes an `AuditLog`.

**Problem:** `Builder::each()` chunks by **offset**. Each callback changes `status`, the filtered page shrinks, and later children are skipped. The audit then re-queried `status = Cancelled`, counting cancellations that already existed.

**Solution:** `pluck('id')` in id order, then `lockForUpdate()->findOrFail()` each. The audit stores the count of ids this void processed.

**Tests:** `tests/Feature/Services/Sales/InvoiceServiceTest.php`

### 46. Journal Entries Use `SoftDeletes`; Posted Rows Still Cannot Be Deleted

**Context:** `journal_entries.deleted_at` existed; reports filtered `whereNull('je.deleted_at')`; the model did not use `SoftDeletes`. Hard deletes left the filter a no-op.

**Solution:** `SoftDeletes` on `JournalEntry`. Unposted drafts are recoverable. The existing `deleting` guard still throws on posted entries — reverse, do not delete, posted money.

**Tests:** `tests/Feature/Services/Accounting/JournalServiceTest.php`

### 47. All Document Numbers Go Through `DocumentNumbers` Sequences

**Context:** `DocumentNumbers` (F-02) owns `document_sequences`. `SequentialNumberStrategy` / `ProjectBasedNumberStrategy` were a parallel, unused subsystem. Project-based `orderByRaw` used MySQL `SUBSTRING_INDEX` + `CAST AS UNSIGNED` — fatal on PostgreSQL.

**Solution:** Both strategies delegate to `DocumentNumbers::generate()`. Do not add a third numbering path. Do not string-sort formatted numbers.

**Tests:** `tests/Feature/Domain/Shared/DocumentNumbersTest.php`

### 48. Movement `total_cost` Is Unsigned Magnitude; `quantity` Carries the Sign

**Context:** `stockOut` / transfer-out write `quantity` negative and `total_cost` positive. POS COGS uses `abs($movement->total_cost)`.

**Problem:** `SUM(total_cost)` across mixed types is meaningless. That is convention, not a leak — do not flip outbound `total_cost` negative without updating every consumer.

**Solution:** Filter by `type` (or `abs(quantity)`). Outbound value in reports is `SUM(total_cost)` of `TYPE_OUT` rows, not a signed mix.

### 49. Do Not Query Per Row in a `foreach`

**Context:** MRP explode/shortage, task reorder, BOM variant attach, work-order consumption.

**Problem:** `Bom::where('product_id', $id)->first()` / `Product::find($id)` / `Task::where('id', $id)->update()` inside a loop is an N+1. It is also the same lost-update shape as unbatched status updates.

**Solution:** Load with `whereIn`, `keyBy`, then map. Reorder with one `CASE id ... END` update scoped to the parent project. `converted` on solar proposals is `DocumentStatus::Converted`; PostgreSQL CHECK matches the enum — SQLite cannot ALTER CHECK, so the enum is the suite's net (same as journal line exclusivity).

### 50. `lockForUpdate()` Is a No-Op on SQLite — Prove Locks on PostgreSQL

**Context:** Production is PostgreSQL. `phpunit.xml` uses SQLite `:memory:`. `SELECT … FOR UPDATE` does nothing there, so every concurrency test that runs sequentially on SQLite is theatre.

**Problem:** POS checkout, FIFO `lockForStock()`, payment allocation, GRN complete, and `reverseEntry()` all serialize on row locks. None of that is exercised by the default suite.

**Solution:** Keep SQLite for the fast inner loop. The F-14 lane is `tests/Pgsql` + `phpunit.pgsql.xml` (`DB_DATABASE=enter365_test`). Tests use a **second PDO session** and `lock_timeout = 500ms`. If `FOR UPDATE` is missing, the second session takes the row and the test fails. Do not use `RefreshDatabase` for these tests — its wrapping transaction hides fixture rows from the peer connection. Use `DatabaseTruncation`.

```bash
./scripts/test-pgsql-locks.sh
# or: composer test:pgsql
# CI: .github/workflows/pgsql-locks.yml (Postgres 16)
```

Do not add `tests/Pgsql` to the default `phpunit.xml` suite.

**Tests:** `tests/Pgsql/`

### 51. `FEATURE_PRESET=pos` Hides the Trading Documents

**Context:** Live Valet API (`enter365.test`) is what Pest browser tests hit via `skipUnlessLiveFeature()` / `liveApiModules()`. `phpunit.xml` `FEATURE_PRESET=full` only affects the in-process SQLite suite.

**Problem:** Preset `pos` sets `$posAcquisition`, which defaults invoices, payments, quotations, PO, DO, GRN, and returns **off**. Browser tests then skip (`Live API feature 'invoices' is off`). Bill create still works (bills are core) but `/payments/new` is gated and the SPA dumps the session back to `/login` — a fail, not a skip. That is not a broken invoice form.

**Solution:** A trading pilot uses `FEATURE_PRESET=general` (core documents on). Keep `FEATURE_POS=true` if the Kopitiam till must stay. `pos` is kasir-only acquisition, not “POS plus ERP”. After changing `.env`, `php artisan optimize:clear` so PHP-FPM sees it.

Ship the audit migrations onto the **live** Valet DB (`akuntansi`), not only sqlite tests. `visibleToPos()` filters `is_test = false`; if the column is missing the outlets endpoint 500s and the till opens with an empty gudang list. Backfill E2E codes (`WH-E2E-%`, `WH-OP-%`) to `is_test`.

```
# ❌ BAD — trading E2E against a kasir-only tenant
FEATURE_PRESET=pos

# ✅ GOOD — Phase 1 trading; till remains if FEATURE_POS=true
FEATURE_PRESET=general
FEATURE_POS=true
```

**Tests:** `tests/Browser/InvoiceTest.php` (and Payment/DO/GRN/PO/Quotation/Bill) against SPA_URL + live API.

### 52. Manufacturing Pack Overrides, Not a Preset Flip

**Context:** Phase 2 shop floor (BOM → WO → MR issue → WO complete) on a tenant that already trades.

**Problem:** `FEATURE_PRESET=manufacturing` turns the pack on but defaults `pos` off. `FEATURE_PRESET=pos` turns manufacturing **and** trading documents off. Browser `ManufacturingChainTest` skipped when `bom` / `work_orders` / `material_requisitions` were false on a `general` tenant — that is the preset, not a broken WO form.

**Solution:** Keep `FEATURE_PRESET=general`. Turn the pack on with explicit `FEATURE_BOM`, `FEATURE_WORK_ORDERS`, `FEATURE_MATERIAL_REQUISITIONS` (`FEATURE_MANUFACTURING` for the family flag). `php artisan optimize:clear`. Raw stock must drop on **MR issue**, not again on WO complete. FG stock and `TYPE_PRODUCTION` movement land on complete via `FinishedGoodsHandler`. Job costing posts Inventory→WIP on consume and WIP→FG on complete; default `project_based` may skip those JEs — do not assert a JE that the strategy does not own.

```
FEATURE_PRESET=general
FEATURE_POS=true
FEATURE_BOM=true
FEATURE_WORK_ORDERS=true
FEATURE_MATERIAL_REQUISITIONS=true
```

**Tests:** `tests/Browser/ManufacturingChainTest.php`, `tests/Feature/Services/Manufacturing/WorkOrderFinishedGoodsReceiptTest.php`, `WorkOrderManufacturingCostStrategyWiringTest.php`

### 53. Budget Comparison Is `data.comparison` With SPA Field Names

**Context:** Budget detail tab **Budget vs Actual**. `useBudgetComparison` reads `response.data.data`.

**Problem:** `GET /budgets/{id}/comparison` returned an unwrapped `{ comparison: [{ budget_amount, actual_amount, variance_percent }] }` with no `totals`. The SPA looked at `data.comparison[].budgeted` and `data.totals.total_budgeted`, so the table stayed on “Loading comparison data…” or threw on `variance_percentage.toFixed`. Service objects can keep `budget_amount`; HTTP must match the Vue types.

**Solution:** Wrap in `data`. Map rows to `budgeted` / `actual` / `variance` / `variance_percentage`. Add `totals.total_budgeted|total_actual|total_variance`. Do not restore the unwrapped payload.

**Tests:** `tests/Feature/Api/V1/BudgetApiTest.php` (comparison), `tests/Browser/BudgetCompareTest.php`

### 54. Nested Addon API Modules Import `@/api/client`, Not `./client`

**Context:** Vue files under `src/api/addons/solar/` and `electrical-panel/`. Vite resolves `./client` next to the file.

**Problem:** Those modules copied core-api imports (`from './client'`, `'./factory'`, `'./types'`). There is no `addons/solar/client.ts`. Opening `/solar-proposals/new` threw a Vite overlay: failed to resolve `./client`. Electrical-panel hooks had the same break.

**Solution:** Import `@/api/client`, `@/api/factory`, `@/api/types`. Sibling `useBomTemplatePanel.ts` already does this.

Vee-validate: `v-model="values.description"` does **not** register the field. Playwright can fill the DOM and submit still sees `""`. Use `defineField('description')`.

Convert solar→quotation JSON must expose `quotation.id` at the top of `quotation` (resolve the resource). Nested `JsonResource` wrapping makes `quotation.id` undefined and the SPA navigates to `/quotations/undefined`.

**Tests:** `tests/Browser/SolarConvertTest.php`, `tests/Browser/ProjectLifecycleTest.php`

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

# PostgreSQL lock suite (F-14; DB enter365_test)
./scripts/test-pgsql-locks.sh
# composer test:pgsql

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
