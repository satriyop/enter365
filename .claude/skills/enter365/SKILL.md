# Enter365 Architecture Skill

Architecture, patterns, and gotchas for Enter365 - Indonesian SME ERP/Accounting system for electrical panel manufacturing and Solar EPC contracting.

## Trigger

Use when:
- Developing features in Enter365
- Debugging issues in this codebase
- Understanding the architecture
- Creating new services, models, or domain components

---

## Skill Index

This skill has detailed reference files for specific patterns:

### Architecture & Organization

| Skill File | Purpose |
|------------|---------|
| [FILE_ORGANIZATION.md](FILE_ORGANIZATION.md) | Directory structure, naming conventions, where to put new files |
| [SOLID_PRINCIPLES.md](SOLID_PRINCIPLES.md) | How SRP, OCP, LSP, ISP, DIP are applied in this codebase |
| [SERVICE_BINDINGS.md](SERVICE_BINDINGS.md) | All interface → implementation bindings in AppServiceProvider |

### Domain Patterns

| Skill File | Purpose |
|------------|---------|
| [STATE_MACHINES.md](STATE_MACHINES.md) | 7 state machines with transitions, events, templates |
| [EVENTS.md](EVENTS.md) | 74 domain events, event dispatcher pattern, testing |
| [STRATEGIES.md](STRATEGIES.md) | Accounting strategies (COGS, Inventory, Manufacturing) |
| [VALUE_OBJECTS.md](VALUE_OBJECTS.md) | Money, Quantity, Percentage; Calculator patterns |
| [APPROVAL_PIPELINES.md](APPROVAL_PIPELINES.md) | Chain of responsibility for approvals |

### Data Layer

| Skill File | Purpose |
|------------|---------|
| [MODELS.md](MODELS.md) | 74 models, relationships, casts, scopes, templates |
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
Service Layer (business logic) ← 77 services
    ↓
Domain Layer (DDD patterns) ← StateMachines, Events, ValueObjects
    ↓
Contracts (interfaces) ← 40+ interfaces
    ↓
Model Layer (Eloquent) ← 71 models
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
5. Use in service within `DB::transaction()`

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

Always wrap state transitions in `DB::transaction()`:

```php
return DB::transaction(function () use ($invoice) {
    $sm = InvoiceStateMachine::fromInvoice($invoice, $this->eventDispatcher);
    $sm->transitionTo(DocumentStatus::Sent);
    return $invoice->fresh();
});
```

### 6. Event Dispatcher for Testability

Inject `EventDispatcherInterface`, not direct `event()` calls:

```php
public function __construct(
    private EventDispatcherInterface $eventDispatcher
) {}

// Use NullEventDispatcher in unit tests
```

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

# Generate API docs
php artisan scramble:export --path=api.json

# List routes
php artisan route:list --path=api/v1

# Database schema
php artisan db:show

# Tinker
php artisan tinker
```
