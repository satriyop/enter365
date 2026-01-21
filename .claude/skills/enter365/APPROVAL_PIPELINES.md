# Approval Pipelines

Handler pipeline pattern for post-approval side effects.

---

## Architecture

```
Service.approve()
    → StateMachine.transitionTo(Approved)
    → ApprovalPipeline.process()
        → Handler1.handle() (priority 10)
        → Handler2.handle() (priority 20)
        → Handler3.handle() (priority 30)
```

---

## Handler Interface

**Location:** `app/Domain/Sales/SalesReturns/Contracts/ApprovalHandlerInterface.php`

```php
interface ApprovalHandlerInterface
{
    public function handle(SalesReturn $salesReturn): void;
    public function priority(): int;
    public function shouldHandle(SalesReturn $salesReturn): bool;
}
```

---

## Pipeline Class

**Location:** `app/Domain/Sales/SalesReturns/Handlers/SalesReturnApprovalPipeline.php`

```php
class SalesReturnApprovalPipeline
{
    private array $handlers = [];

    public function addHandler(ApprovalHandlerInterface $handler): self
    {
        $this->handlers[] = $handler;
        usort($this->handlers, fn ($a, $b) => $a->priority() <=> $b->priority());
        return $this;
    }

    public function process(SalesReturn $salesReturn): void
    {
        foreach ($this->handlers as $handler) {
            if ($handler->shouldHandle($salesReturn)) {
                $handler->handle($salesReturn);
            }
        }
    }

    public function count(): int
    {
        return count($this->handlers);
    }
}
```

---

## Current Handlers

### Sales Returns

| Handler | Priority | Purpose |
|---------|----------|---------|
| `InventoryReturnHandler` | 10 | Stock-in for returned goods |
| `JournalEntryHandler` | 20 | Create reversal journal |

### Purchase Returns

| Handler | Priority | Purpose |
|---------|----------|---------|
| `InventoryReturnHandler` | 10 | Stock-out to supplier |
| `JournalEntryHandler` | 20 | Create reversal journal |

---

## Handler Implementation

```php
class InventoryReturnHandler implements ApprovalHandlerInterface
{
    public function __construct(
        private InventoryServiceInterface $inventoryService
    ) {}

    public function handle(SalesReturn $salesReturn): void
    {
        foreach ($salesReturn->items as $item) {
            $this->inventoryService->stockIn(
                productId: $item->product_id,
                warehouseId: $salesReturn->warehouse_id,
                quantity: $item->quantity,
                reference: "SR-{$salesReturn->return_number}"
            );
        }
    }

    public function priority(): int
    {
        return 10;
    }

    public function shouldHandle(SalesReturn $salesReturn): bool
    {
        return $salesReturn->warehouse_id !== null;
    }
}
```

---

## Service Provider Registration

**Location:** `app/Providers/AppServiceProvider.php`

```php
$this->app->bind(
    SalesReturnApprovalPipeline::class,
    function ($app) {
        $pipeline = new SalesReturnApprovalPipeline;
        $pipeline->addHandler($app->make(InventoryReturnHandler::class));
        $pipeline->addHandler($app->make(JournalEntryHandler::class));
        return $pipeline;
    }
);
```

---

## Using in Service

```php
class SalesReturnService
{
    public function __construct(
        private SalesReturnApprovalPipeline $approvalPipeline
    ) {}

    public function approve(SalesReturn $salesReturn, ?int $userId = null): SalesReturn
    {
        return DB::transaction(function () use ($salesReturn, $userId) {
            $salesReturn->transitionTo(DocumentStatus::Approved, $userId);

            // Pipeline executes all handlers in order
            $this->approvalPipeline->process($salesReturn);

            return $salesReturn->fresh(['items', 'journalEntry']);
        });
    }
}
```

---

## Priority Ranges

| Range | Purpose |
|-------|---------|
| 10-19 | Inventory operations |
| 20-29 | Accounting/Journals |
| 30-39 | Notifications |
| 40-49 | External integrations |
| 50-59 | Post-processing |

---

## Creating New Handler

### Step 1: Create Handler Class

```php
namespace App\Domain\Sales\SalesReturns\Handlers;

class NotificationHandler implements ApprovalHandlerInterface
{
    public function __construct(
        private NotificationServiceInterface $notificationService
    ) {}

    public function handle(SalesReturn $salesReturn): void
    {
        $this->notificationService->notifyCustomer($salesReturn);
    }

    public function priority(): int
    {
        return 30;  // After inventory and journal
    }

    public function shouldHandle(SalesReturn $salesReturn): bool
    {
        return $salesReturn->contact->notify_on_approval ?? false;
    }
}
```

### Step 2: Register in Service Provider

```php
$this->app->bind(
    SalesReturnApprovalPipeline::class,
    function ($app) {
        $pipeline = new SalesReturnApprovalPipeline;
        $pipeline->addHandler($app->make(InventoryReturnHandler::class));
        $pipeline->addHandler($app->make(JournalEntryHandler::class));
        $pipeline->addHandler($app->make(NotificationHandler::class));  // NEW
        return $pipeline;
    }
);
```

---

## Testing Handlers

```php
it('has priority 10 (runs first)', function () {
    $handler = app(InventoryReturnHandler::class);
    expect($handler->priority())->toBe(10);
});

it('should NOT handle returns without warehouse', function () {
    $return = SalesReturn::factory()->create(['warehouse_id' => null]);
    $handler = app(InventoryReturnHandler::class);
    expect($handler->shouldHandle($return))->toBeFalse();
});

it('creates stock-in movement', function () {
    $return = SalesReturn::factory()
        ->has(SalesReturnItem::factory()->count(2))
        ->create();

    $handler = app(InventoryReturnHandler::class);
    $handler->handle($return);

    $this->assertDatabaseHas('inventory_movements', [
        'reference' => "SR-{$return->return_number}",
    ]);
});
```

---

## Error Handling

Pipeline runs inside DB transaction. If any handler throws exception:
- Transaction rolls back
- All previous handlers' changes are undone
- Exception propagates to caller

```php
// Fail-fast validation in handler
public function handle(SalesReturn $salesReturn): void
{
    // Validate accounts exist BEFORE creating journal
    $accounts = $this->accountLookup->findByCodesOrFail(
        $requiredCodes,
        "sales return journal #{$salesReturn->return_number}"
    );

    // Create journal only after validation passes
    $this->journalService->create([...]);
}
```
