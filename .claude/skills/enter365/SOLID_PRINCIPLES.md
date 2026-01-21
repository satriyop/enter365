# SOLID Principles in Enter365

How SOLID principles are applied in this codebase.

---

## Single Responsibility (SRP)

Each class has one reason to change.

### State Machines Own Status Changes

**Critical:** Status transitions are the SOLE responsibility of state machines. Services orchestrate but never directly modify status.

```php
// ❌ WRONG - Service directly changing status
class InvoiceService
{
    public function send(Invoice $invoice): void
    {
        $invoice->status = DocumentStatus::Sent;  // Violates SRP
        $invoice->save();
    }
}

// ✓ CORRECT - State machine owns status changes
class InvoiceService
{
    public function send(Invoice $invoice): Invoice
    {
        $sm = InvoiceStateMachine::fromInvoice($invoice, $this->eventDispatcher);
        $sm->transitionTo(DocumentStatus::Sent);  // State machine responsibility
        return $invoice->fresh();
    }
}
```

**Why SRP matters here:**
- State machine: validation, events, history, timestamps
- Service: orchestration, business rules, transactions

### Service Layer Separation

```php
// AbstractApplicationService - Only transaction/logging
abstract class AbstractApplicationService
{
    protected function executeInTransaction(string $operation, callable $callback): mixed
    {
        // Only handles transaction wrapping and logging
    }
}

// InvoiceService - Only invoice business logic
class InvoiceService extends AbstractDocumentService
{
    public function send(Invoice $invoice): Invoice { }
    public function void(Invoice $invoice): Invoice { }
}
```

### Strategy Implementations

Each strategy handles ONE specific algorithm:

```php
// PerpetualInventoryStrategy - Creates journals on every movement
// PeriodicInventoryStrategy - No journals on movements
// HybridInventoryStrategy - Only stock opname journals
```

---

## Open/Closed Principle (OCP)

Open for extension, closed for modification.

### Strategy Pattern

Add new strategies without modifying existing code:

```php
// AccountingPolicyManager
private array $inventoryStrategies = [
    'perpetual' => PerpetualInventoryStrategy::class,
    'periodic' => PeriodicInventoryStrategy::class,
    'hybrid' => HybridInventoryStrategy::class,
    // Add new strategy here - no code changes needed
];

public function inventory(): InventoryAccountingStrategy
{
    $method = config('accounting.policies.inventory_method');
    return $this->container->make($this->inventoryStrategies[$method]);
}
```

### To Add New Strategy

1. Create class implementing interface
2. Add entry to config
3. No changes to manager or existing strategies

---

## Liskov Substitution (LSP)

Subtypes substitutable for base types.

### All Strategies Interchangeable

```php
interface ManufacturingCostStrategy
{
    public function onWorkOrderStart(WorkOrder $workOrder): ?JournalEntry;
    public function onWorkOrderComplete(WorkOrder $workOrder): ?JournalEntry;
    public function calculateTotalCost(WorkOrder $workOrder): int;
}

// All implementations honor the contract
class ProjectBasedCostingStrategy implements ManufacturingCostStrategy { }
class JobCostingStrategy implements ManufacturingCostStrategy { }
class WIPAccountingStrategy implements ManufacturingCostStrategy { }
```

---

## Interface Segregation (ISP)

Clients don't depend on unused interfaces.

### Focused Interfaces

```php
// Minimal, focused interface
interface EventDispatcherInterface
{
    public function dispatch(object $event): void;
}

// Domain-specific repository interface
interface InvoiceRepositoryInterface extends RepositoryInterface
{
    public function findByStatus(DocumentStatus $status): Collection;
    public function findOverdue(): Collection;
    public function getOutstandingForContact(int $contactId): int;
}
```

### Services Get Only What They Need

```php
class InvoiceService
{
    public function __construct(
        private InvoiceRepositoryInterface $invoiceRepository  // Specific interface
    ) {}
}
```

---

## Dependency Inversion (DIP)

Depend on abstractions, not concretions.

### Service Provider Bindings

```php
// AppServiceProvider
$this->app->bind(
    EventDispatcherInterface::class,
    LaravelEventDispatcher::class
);

$this->app->bind(InvoiceServiceInterface::class, InvoiceService::class);
```

### Constructor Injection

```php
class WIPAccountingStrategy implements ManufacturingCostStrategy
{
    public function __construct(
        private JournalServiceInterface $journalService  // Interface, not class
    ) {}
}
```

---

## Key Patterns Supporting SOLID

| Pattern | Supports | Location |
|---------|----------|----------|
| Repository | SRP, DIP | `app/Infrastructure/Repositories/` |
| Strategy | OCP, LSP | `app/Services/*/Strategies/` |
| Factory | DIP | `AccountingPolicyManager` |
| Service Layer | SRP | `app/Services/` |

---

## Configuration Over Code

```php
// config/accounting.php
'policies' => [
    'inventory_method' => 'hybrid',
    'cogs_recognition' => 'on_invoice',
    'manufacturing_costing' => 'project_based',
]
```

Switch strategies by changing config, not code.
