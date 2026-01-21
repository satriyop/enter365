# Phase 4: Event-Driven Architecture

> **Goal**: Improve event handling with proper structure, auto-discovery, and better separation of concerns.

## Current Issues

1. **Event Registration Sprawl**: AppServiceProvider has 200+ lines of Event::listen() calls
2. **Inconsistent Event Structure**: Some events have factory methods, others don't
3. **Listener Duplication**: Same listener registered for multiple events
4. **Missing Events**: Many domain operations don't fire events

---

## Deliverables

- [ ] Event auto-discovery configuration
- [ ] Event subscriber pattern for related events
- [ ] Async event handling for non-critical operations
- [ ] Event sourcing preparation (optional)
- [ ] Comprehensive domain events coverage

---

## Part 1: Event Auto-Discovery

### 1.1 Configure Event Discovery

Laravel 11+ supports event auto-discovery. Configure it properly.

```php
<?php
// File: app/Providers/EventServiceProvider.php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return true;
    }

    /**
     * Get the listener directories that should be used to discover events.
     *
     * @return array<int, string>
     */
    protected function discoverEventsWithin(): array
    {
        return [
            $this->app->path('Infrastructure/Listeners'),
            $this->app->path('Listeners'),
        ];
    }

    /**
     * The subscribers to register.
     *
     * @var array<class-string>
     */
    protected $subscribe = [
        // Sales Domain
        \App\Listeners\Sales\InvoiceEventSubscriber::class,
        \App\Listeners\Sales\QuotationEventSubscriber::class,
        \App\Listeners\Sales\SalesReturnEventSubscriber::class,
        \App\Listeners\Sales\DeliveryOrderEventSubscriber::class,

        // Purchasing Domain
        \App\Listeners\Purchasing\BillEventSubscriber::class,
        \App\Listeners\Purchasing\PurchaseOrderEventSubscriber::class,
        \App\Listeners\Purchasing\PurchaseReturnEventSubscriber::class,

        // Manufacturing Domain
        \App\Listeners\Manufacturing\WorkOrderEventSubscriber::class,
        \App\Listeners\Manufacturing\MaterialRequisitionEventSubscriber::class,

        // Inventory Domain
        \App\Listeners\Inventory\InventoryMovementSubscriber::class,
        \App\Listeners\Inventory\StockOpnameSubscriber::class,

        // Projects Domain
        \App\Listeners\Projects\ProjectEventSubscriber::class,
    ];
}
```

### 1.2 Register EventServiceProvider

```php
// File: bootstrap/providers.php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\EventServiceProvider::class,  // Add this
    App\Providers\AccountingServiceProvider::class,
    App\Providers\RepositoryServiceProvider::class,
];
```

---

## Part 2: Event Subscribers

### 2.1 Invoice Event Subscriber

```php
<?php
// File: app/Listeners/Sales/InvoiceEventSubscriber.php

declare(strict_types=1);

namespace App\Listeners\Sales;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Sales\Invoices\Events\InvoiceFullyPaid;
use App\Domain\Sales\Invoices\Events\InvoiceOverdue;
use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Domain\Sales\Invoices\Events\InvoiceStatusChanged;
use App\Domain\Sales\Invoices\Events\InvoiceVoided;
use App\Models\Core\AuditLog;
use Illuminate\Events\Dispatcher;

/**
 * Handles all invoice-related events.
 */
class InvoiceEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    /**
     * Handle invoice status changes - log to audit.
     */
    public function handleStatusChanged(InvoiceStatusChanged $event): void
    {
        AuditLog::create([
            'auditable_type' => 'invoice',
            'auditable_id' => $event->invoiceId,
            'event' => 'status_changed',
            'old_values' => ['status' => $event->from->value],
            'new_values' => ['status' => $event->to->value],
            'user_id' => $event->userId,
        ]);

        $this->logger->logOperation('invoice.status_changed', [
            'invoice_id' => $event->invoiceId,
            'from' => $event->from->value,
            'to' => $event->to->value,
        ]);
    }

    /**
     * Handle invoice sent - notify customer.
     */
    public function handleSent(InvoiceSent $event): void
    {
        // Log activity
        AuditLog::create([
            'auditable_type' => 'invoice',
            'auditable_id' => $event->invoiceId,
            'event' => 'sent',
            'new_values' => [
                'invoice_number' => $event->invoiceNumber,
                'total_amount' => $event->totalAmount,
            ],
            'user_id' => $event->userId,
        ]);

        // Queue notification to customer
        // NotifyCustomerJob::dispatch($event->invoiceId)->onQueue('notifications');

        $this->logger->logOperation('invoice.sent', [
            'invoice_id' => $event->invoiceId,
            'invoice_number' => $event->invoiceNumber,
        ]);
    }

    /**
     * Handle invoice fully paid.
     */
    public function handleFullyPaid(InvoiceFullyPaid $event): void
    {
        AuditLog::create([
            'auditable_type' => 'invoice',
            'auditable_id' => $event->invoiceId,
            'event' => 'fully_paid',
            'new_values' => [
                'total_amount' => $event->totalAmount,
                'paid_amount' => $event->paidAmount,
            ],
            'user_id' => $event->userId,
        ]);

        // Queue thank you notification
        // ThankYouEmailJob::dispatch($event->invoiceId)->onQueue('notifications');

        $this->logger->logOperation('invoice.fully_paid', [
            'invoice_id' => $event->invoiceId,
        ]);
    }

    /**
     * Handle invoice overdue.
     */
    public function handleOverdue(InvoiceOverdue $event): void
    {
        AuditLog::create([
            'auditable_type' => 'invoice',
            'auditable_id' => $event->invoiceId,
            'event' => 'overdue',
            'new_values' => [
                'days_overdue' => $event->daysOverdue,
                'outstanding' => $event->outstandingAmount,
            ],
            'user_id' => null,
        ]);

        // Queue overdue notification
        // OverdueReminderJob::dispatch($event->invoiceId)->onQueue('notifications');

        $this->logger->logOperation('invoice.overdue', [
            'invoice_id' => $event->invoiceId,
            'days_overdue' => $event->daysOverdue,
        ]);
    }

    /**
     * Handle invoice voided.
     */
    public function handleVoided(InvoiceVoided $event): void
    {
        AuditLog::create([
            'auditable_type' => 'invoice',
            'auditable_id' => $event->invoiceId,
            'event' => 'voided',
            'new_values' => [
                'reason' => $event->reason,
            ],
            'user_id' => $event->userId,
        ]);

        $this->logger->logOperation('invoice.voided', [
            'invoice_id' => $event->invoiceId,
            'reason' => $event->reason,
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            InvoiceStatusChanged::class => 'handleStatusChanged',
            InvoiceSent::class => 'handleSent',
            InvoiceFullyPaid::class => 'handleFullyPaid',
            InvoiceOverdue::class => 'handleOverdue',
            InvoiceVoided::class => 'handleVoided',
        ];
    }
}
```

### 2.2 Work Order Event Subscriber

```php
<?php
// File: app/Listeners/Manufacturing/WorkOrderEventSubscriber.php

declare(strict_types=1);

namespace App\Listeners\Manufacturing;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Manufacturing\WorkOrders\Events\MaterialConsumed;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderCancelled;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderCompleted;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderConfirmed;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderStarted;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderStatusChanged;
use App\Models\Core\AuditLog;
use Illuminate\Events\Dispatcher;

class WorkOrderEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function handleStatusChanged(WorkOrderStatusChanged $event): void
    {
        AuditLog::create([
            'auditable_type' => 'work_order',
            'auditable_id' => $event->workOrderId,
            'event' => 'status_changed',
            'old_values' => ['status' => $event->from->value],
            'new_values' => ['status' => $event->to->value],
            'user_id' => $event->userId,
        ]);
    }

    public function handleConfirmed(WorkOrderConfirmed $event): void
    {
        AuditLog::create([
            'auditable_type' => 'work_order',
            'auditable_id' => $event->workOrderId,
            'event' => 'confirmed',
            'new_values' => [
                'wo_number' => $event->woNumber,
                'quantity_ordered' => $event->quantityOrdered,
            ],
            'user_id' => $event->userId,
        ]);

        // Notify production team
        $this->logger->logOperation('work_order.confirmed', [
            'work_order_id' => $event->workOrderId,
        ]);
    }

    public function handleStarted(WorkOrderStarted $event): void
    {
        AuditLog::create([
            'auditable_type' => 'work_order',
            'auditable_id' => $event->workOrderId,
            'event' => 'started',
            'user_id' => $event->userId,
        ]);

        $this->logger->logOperation('work_order.started', [
            'work_order_id' => $event->workOrderId,
        ]);
    }

    public function handleCompleted(WorkOrderCompleted $event): void
    {
        AuditLog::create([
            'auditable_type' => 'work_order',
            'auditable_id' => $event->workOrderId,
            'event' => 'completed',
            'new_values' => [
                'quantity_completed' => $event->quantityCompleted,
                'quantity_scrapped' => $event->quantityScrapped,
            ],
            'user_id' => $event->userId,
        ]);

        $this->logger->logOperation('work_order.completed', [
            'work_order_id' => $event->workOrderId,
            'quantity_completed' => $event->quantityCompleted,
        ]);
    }

    public function handleMaterialConsumed(MaterialConsumed $event): void
    {
        $this->logger->logOperation('work_order.material_consumed', [
            'work_order_id' => $event->workOrderId,
            'product_id' => $event->productId,
            'quantity' => $event->quantity,
        ]);
    }

    public function handleCancelled(WorkOrderCancelled $event): void
    {
        AuditLog::create([
            'auditable_type' => 'work_order',
            'auditable_id' => $event->workOrderId,
            'event' => 'cancelled',
            'new_values' => ['reason' => $event->reason],
            'user_id' => $event->userId,
        ]);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            WorkOrderStatusChanged::class => 'handleStatusChanged',
            WorkOrderConfirmed::class => 'handleConfirmed',
            WorkOrderStarted::class => 'handleStarted',
            WorkOrderCompleted::class => 'handleCompleted',
            WorkOrderCancelled::class => 'handleCancelled',
            MaterialConsumed::class => 'handleMaterialConsumed',
        ];
    }
}
```

### 2.3 Inventory Movement Subscriber

```php
<?php
// File: app/Listeners/Inventory/InventoryMovementSubscriber.php

declare(strict_types=1);

namespace App\Listeners\Inventory;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Inventory\Movements\Events\InventoryAdjusted;
use App\Domain\Inventory\Movements\Events\InventoryIssued;
use App\Domain\Inventory\Movements\Events\InventoryReceived;
use App\Domain\Inventory\Movements\Events\InventoryTransferred;
use App\Models\Inventory\ProductStock;
use Illuminate\Events\Dispatcher;

class InventoryMovementSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function handleReceived(InventoryReceived $event): void
    {
        $this->checkStockLevels($event->productId, $event->warehouseId);

        $this->logger->logOperation('inventory.received', [
            'product_id' => $event->productId,
            'warehouse_id' => $event->warehouseId,
            'quantity' => $event->quantity,
            'movement_id' => $event->movementId,
        ]);
    }

    public function handleIssued(InventoryIssued $event): void
    {
        $this->checkLowStock($event->productId, $event->warehouseId);

        $this->logger->logOperation('inventory.issued', [
            'product_id' => $event->productId,
            'warehouse_id' => $event->warehouseId,
            'quantity' => $event->quantity,
            'movement_id' => $event->movementId,
        ]);
    }

    public function handleAdjusted(InventoryAdjusted $event): void
    {
        $this->logger->logOperation('inventory.adjusted', [
            'product_id' => $event->productId,
            'warehouse_id' => $event->warehouseId,
            'adjustment' => $event->adjustmentQuantity,
            'previous' => $event->previousQuantity,
            'new' => $event->newQuantity,
            'movement_id' => $event->movementId,
        ]);
    }

    public function handleTransferred(InventoryTransferred $event): void
    {
        $this->checkLowStock($event->productId, $event->fromWarehouseId);

        $this->logger->logOperation('inventory.transferred', [
            'product_id' => $event->productId,
            'from_warehouse_id' => $event->fromWarehouseId,
            'to_warehouse_id' => $event->toWarehouseId,
            'quantity' => $event->quantity,
        ]);
    }

    /**
     * Check and log if stock is low after movement.
     */
    private function checkLowStock(int $productId, int $warehouseId): void
    {
        $stock = ProductStock::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if ($stock && $stock->isLow()) {
            // Queue low stock alert
            // LowStockAlertJob::dispatch($productId, $warehouseId)->onQueue('alerts');

            $this->logger->logOperation('inventory.low_stock_alert', [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity' => $stock->quantity,
                'reorder_level' => $stock->product->reorder_level ?? 0,
            ]);
        }
    }

    /**
     * Check stock levels and update product status.
     */
    private function checkStockLevels(int $productId, int $warehouseId): void
    {
        // Could update product availability status, cache stock levels, etc.
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            InventoryReceived::class => 'handleReceived',
            InventoryIssued::class => 'handleIssued',
            InventoryAdjusted::class => 'handleAdjusted',
            InventoryTransferred::class => 'handleTransferred',
        ];
    }
}
```

---

## Part 3: Async Event Handling

### 3.1 Create Queueable Listeners for Non-Critical Operations

```php
<?php
// File: app/Listeners/Sales/SendInvoiceNotification.php

declare(strict_types=1);

namespace App\Listeners\Sales;

use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Models\Sales\Invoice;
use App\Notifications\InvoiceSentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendInvoiceNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The queue connection that should handle the job.
     */
    public string $connection = 'redis';

    /**
     * The queue that should handle the job.
     */
    public string $queue = 'notifications';

    /**
     * The number of times the queued listener may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 60;

    public function handle(InvoiceSent $event): void
    {
        $invoice = Invoice::with('contact')->find($event->invoiceId);

        if (! $invoice || ! $invoice->contact?->email) {
            return;
        }

        $invoice->contact->notify(new InvoiceSentNotification($invoice));
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(InvoiceSent $event): bool
    {
        // Don't queue if contact has no email
        $invoice = Invoice::with('contact')->find($event->invoiceId);

        return $invoice?->contact?->email !== null;
    }
}
```

### 3.2 Event Broadcasting Configuration

```php
<?php
// File: config/broadcasting.php - Add channels

'channels' => [
    'invoices.{invoiceId}' => App\Broadcasting\InvoiceChannel::class,
    'work-orders.{workOrderId}' => App\Broadcasting\WorkOrderChannel::class,
    'inventory.{warehouseId}' => App\Broadcasting\InventoryChannel::class,
],
```

```php
<?php
// File: app/Broadcasting/InvoiceChannel.php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\Sales\Invoice;
use App\Models\User;

class InvoiceChannel
{
    public function join(User $user, int $invoiceId): bool
    {
        $invoice = Invoice::find($invoiceId);

        // User can listen if they created the invoice or are admin
        return $invoice
            && ($invoice->created_by === $user->id || $user->hasRole('admin'));
    }
}
```

---

## Part 4: Remove Event Registration from AppServiceProvider

### 4.1 Clean Up AppServiceProvider

Remove all Event::listen() calls from AppServiceProvider and move them to subscribers or rely on auto-discovery.

```php
<?php
// File: app/Providers/AppServiceProvider.php (cleaned up)

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Events\EventDispatcherInterface;
// ... other imports

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FeatureManager::class, ConfigFeatureManager::class);

        $this->registerInfrastructureServices();
        $this->registerAccountingServices();
        $this->registerDomainServices();
    }

    private function registerInfrastructureServices(): void
    {
        $this->app->bind(
            EventDispatcherInterface::class,
            \App\Infrastructure\Events\LaravelEventDispatcher::class
        );
    }

    // ... accounting and domain services registration

    public function boot(): void
    {
        $this->configureMorphMap();

        // Events are now handled by EventServiceProvider
        // No more Event::listen() calls here!

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer', 'JWT')
                        ->setDescription('Sanctum Bearer Token')
                );
            });
    }

    // ... morph map configuration
}
```

---

## Part 5: Enhanced Event Dispatcher

### 5.1 Add Event Recording for Testing

```php
<?php
// File: app/Infrastructure/Events/RecordingEventDispatcher.php

declare(strict_types=1);

namespace App\Infrastructure\Events;

use App\Contracts\Events\EventDispatcherInterface;
use Illuminate\Support\Collection;

/**
 * Event dispatcher that records events for testing.
 *
 * Use in tests to verify events were dispatched without
 * actually triggering listeners.
 */
class RecordingEventDispatcher implements EventDispatcherInterface
{
    private Collection $events;
    private bool $shouldDispatch;

    public function __construct(bool $shouldDispatch = false)
    {
        $this->events = collect();
        $this->shouldDispatch = $shouldDispatch;
    }

    public function dispatch(object $event): void
    {
        $this->events->push([
            'event' => $event,
            'class' => get_class($event),
            'time' => now(),
        ]);

        if ($this->shouldDispatch) {
            event($event);
        }
    }

    /**
     * Get all recorded events.
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    /**
     * Get events of specific type.
     */
    public function getEventsOfType(string $eventClass): Collection
    {
        return $this->events->filter(fn ($e) => $e['class'] === $eventClass);
    }

    /**
     * Assert event was dispatched.
     */
    public function assertDispatched(string $eventClass, ?callable $callback = null): void
    {
        $events = $this->getEventsOfType($eventClass);

        if ($events->isEmpty()) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Event [{$eventClass}] was not dispatched."
            );
        }

        if ($callback !== null) {
            $matching = $events->filter(fn ($e) => $callback($e['event']));

            if ($matching->isEmpty()) {
                throw new \PHPUnit\Framework\AssertionFailedError(
                    "Event [{$eventClass}] was dispatched but callback returned false."
                );
            }
        }
    }

    /**
     * Assert event was not dispatched.
     */
    public function assertNotDispatched(string $eventClass): void
    {
        $events = $this->getEventsOfType($eventClass);

        if ($events->isNotEmpty()) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Event [{$eventClass}] was dispatched but should not have been."
            );
        }
    }

    /**
     * Assert no events were dispatched.
     */
    public function assertNothingDispatched(): void
    {
        if ($this->events->isNotEmpty()) {
            $classes = $this->events->pluck('class')->unique()->implode(', ');

            throw new \PHPUnit\Framework\AssertionFailedError(
                "Events were dispatched: [{$classes}]"
            );
        }
    }

    /**
     * Clear recorded events.
     */
    public function clear(): void
    {
        $this->events = collect();
    }
}
```

### 5.2 Use in Tests

```php
<?php
// File: tests/Feature/Services/InvoiceServiceTest.php

use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Infrastructure\Events\RecordingEventDispatcher;
use App\Services\Sales\InvoiceService;

describe('InvoiceService Events', function () {

    beforeEach(function () {
        $this->eventDispatcher = new RecordingEventDispatcher();
        $this->app->instance(EventDispatcherInterface::class, $this->eventDispatcher);
    });

    it('dispatches InvoiceSent when posting invoice', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory()->count(2))
            ->draft()
            ->create();

        $service = app(InvoiceService::class);
        $service->post($invoice);

        $this->eventDispatcher->assertDispatched(InvoiceSent::class, function ($event) use ($invoice) {
            return $event->invoiceId === $invoice->id;
        });
    });

    it('does not dispatch events on validation failure', function () {
        $invoice = Invoice::factory()->sent()->create(); // Already sent

        $service = app(InvoiceService::class);

        expect(fn () => $service->post($invoice))->toThrow(StateTransitionException::class);

        $this->eventDispatcher->assertNothingDispatched();
    });
});
```

---

## Verification Checklist

After completing this phase, verify:

- [ ] EventServiceProvider created and registered
- [ ] Event auto-discovery configured
- [ ] All event listeners moved to subscribers
- [ ] AppServiceProvider cleaned of Event::listen() calls
- [ ] Async listeners configured for notifications
- [ ] RecordingEventDispatcher available for testing
- [ ] All existing tests still pass
- [ ] New event tests written

---

## Next Phase

Once Phase 4 is complete and verified, proceed to [Phase 5: Strategy Pattern Expansion](./06-phase-5-strategy-expansion.md).
