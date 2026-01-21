# Domain Events Reference

Event-driven patterns in Enter365.

---

## Why Events Matter

### Audit Trail

**Every status change MUST dispatch an event for audit logging.**

```php
// In state machine afterTransition hook
protected function afterSent(): void
{
    $this->eventDispatcher->dispatch(
        InvoiceSent::fromInvoice($this->model, auth()->id())
    );
}

// Subscriber logs for audit trail
public function handleInvoiceSent(InvoiceSent $event): void
{
    $this->logger->logDomainEvent($event);  // Records who, what, when
}
```

**Audit trail captures:**
- What changed (event class name)
- Who triggered it (`userId`)
- When it happened (`timestamp`)
- Entity details (`invoiceId`, `invoiceNumber`, `status`)

### Downstream Listeners

Events enable **decoupled side effects** - the state machine doesn't need to know what happens after a transition.

| Event | Downstream Actions |
|-------|-------------------|
| `InvoiceSent` | Create journal entry, update AR aging |
| `InvoiceFullyPaid` | Update contact balance, close payment schedule |
| `PurchaseOrderApproved` | Reserve inventory, notify vendor |
| `WorkOrderCompleted` | Update finished goods stock, calculate actual cost |
| `QuotationConverted` | Create invoice, link items |

```php
// State machine only dispatches event
protected function afterPaid(): void
{
    $this->eventDispatcher->dispatch(
        InvoiceFullyPaid::fromInvoice($this->model)
    );
}

// Multiple listeners can react independently
class InvoiceEventSubscriber
{
    public function handleInvoiceFullyPaid(InvoiceFullyPaid $event): void
    {
        // Update contact balance
        // Close payment schedule
        // Send notification
        // Update dashboard metrics
    }
}
```

### Benefits

| Without Events | With Events |
|----------------|-------------|
| State machine knows about journals, inventory, notifications | State machine only knows about status |
| Adding new side effect = modify state machine | Adding new side effect = add listener |
| Hard to test | Easy to test with RecordingEventDispatcher |
| Tight coupling | Loose coupling |

---

## Event Dispatcher Architecture

### Contract

```php
interface EventDispatcherInterface
{
    public function dispatch(object $event): void;
}
```

### Implementations

| Class | Usage |
|-------|-------|
| `LaravelEventDispatcher` | Production - delegates to Laravel |
| `RecordingEventDispatcher` | Testing - records events for assertions |
| `NullEventDispatcher` | Testing - lightweight, no-op |

---

## Events by Domain

### Sales (27 Events)

| Event | Dispatched When |
|-------|-----------------|
| `QuotationSubmitted` | Quotation sent for approval |
| `QuotationApproved` | Quotation approved |
| `QuotationRejected` | Quotation rejected |
| `QuotationConverted` | Converted to invoice |
| `QuotationExpired` | Past validity date |
| `QuotationWon` | Marked as won |
| `QuotationLost` | Marked as lost |
| `InvoiceSent` | Invoice posted to customer |
| `InvoiceFullyPaid` | All payments received |
| `InvoiceOverdue` | Past due date |
| `InvoiceVoided` | Invoice voided |
| `DeliveryOrderConfirmed` | DO confirmed |
| `DeliveryOrderShipped` | Goods shipped |
| `DeliveryOrderDelivered` | Goods delivered |
| `SalesReturnSubmitted` | Return submitted |
| `SalesReturnApproved` | Return approved |
| `SalesReturnCompleted` | Return completed |
| `PaymentReceived` | Payment recorded |

### Purchasing (17 Events)

| Event | Dispatched When |
|-------|-----------------|
| `PurchaseOrderSubmitted` | PO sent for approval |
| `PurchaseOrderApproved` | PO approved |
| `PurchaseOrderRejected` | PO rejected |
| `PurchaseOrderPartial` | Partial receipt |
| `PurchaseOrderReceived` | Fully received |
| `BillReceived` | Bill posted |
| `BillFullyPaid` | Bill fully paid |
| `PurchaseReturnApproved` | Return approved |

### Manufacturing (14 Events)

| Event | Dispatched When |
|-------|-----------------|
| `WorkOrderConfirmed` | WO confirmed |
| `WorkOrderStarted` | Production started |
| `WorkOrderCompleted` | Production completed |
| `MaterialRequisitionApproved` | MR approved |
| `MaterialRequisitionIssued` | Materials issued |

### Projects (6 Events)

| Event | Dispatched When |
|-------|-----------------|
| `ProjectStarted` | Project started |
| `ProjectOnHold` | Project put on hold |
| `ProjectCompleted` | Project completed |
| `ProjectCancelled` | Project cancelled |

### Accounting (5 Events)

| Event | Dispatched When |
|-------|-----------------|
| `FiscalPeriodLocked` | Period locked |
| `FiscalPeriodClosing` | Closing started |
| `FiscalPeriodClosed` | Period closed |
| `FiscalPeriodReopened` | Period reopened |

---

## Event Class Pattern

```php
<?php

namespace App\Domain\Sales\Invoices\Events;

readonly class InvoiceSent
{
    public function __construct(
        public int $invoiceId,
        public string $invoiceNumber,
        public DocumentStatus $status,
        public Carbon $timestamp,
        public ?int $userId = null
    ) {}

    public static function fromInvoice(Invoice $invoice, ?int $userId = null): self
    {
        return new self(
            invoiceId: $invoice->id,
            invoiceNumber: $invoice->invoice_number,
            status: $invoice->status,
            timestamp: now(),
            userId: $userId
        );
    }

    public function toArray(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'invoice_number' => $this->invoiceNumber,
            'status' => $this->status->value,
            'timestamp' => $this->timestamp->toIso8601String(),
            'user_id' => $this->userId,
        ];
    }
}
```

---

## Listener Registration

### EventServiceProvider

```php
// app/Providers/EventServiceProvider.php
protected $subscribe = [
    InvoiceEventSubscriber::class,
    QuotationEventSubscriber::class,
    WorkOrderEventSubscriber::class,
    ProjectEventSubscriber::class,
    // ...
];
```

### Subscriber Pattern

```php
class InvoiceEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function subscribe(Dispatcher $events): array
    {
        return [
            InvoiceSent::class => 'handleInvoiceSent',
            InvoiceFullyPaid::class => 'handleInvoiceFullyPaid',
            InvoiceVoided::class => 'handleInvoiceVoided',
        ];
    }

    public function handleInvoiceSent(InvoiceSent $event): void
    {
        $this->logger->logDomainEvent($event);
        // Additional side effects...
    }
}
```

---

## Dispatching Events

### From State Machine

```php
protected function afterSent(): void
{
    $this->model->sent_at = now();
    $this->model->save();

    $this->eventDispatcher->dispatch(
        InvoiceSent::fromInvoice($this->model, auth()->id())
    );
}
```

### From Service

```php
public function send(Invoice $invoice): Invoice
{
    // Business logic...

    $this->eventDispatcher->dispatch(
        InvoiceSent::fromInvoice($invoice, auth()->id())
    );

    return $invoice;
}
```

---

## Testing Events

### With RecordingEventDispatcher

```php
use App\Infrastructure\Events\RecordingEventDispatcher;

it('dispatches InvoiceSent event', function () {
    $dispatcher = new RecordingEventDispatcher();
    $service = new InvoiceService($dispatcher, ...);

    $service->send($invoice);

    $dispatcher->assertDispatched(InvoiceSent::class);
    $dispatcher->assertDispatched(InvoiceSent::class, fn ($e) =>
        $e->invoiceId === $invoice->id
    );
});
```

### Assertion Methods

```php
$dispatcher->assertDispatched(EventClass::class);
$dispatcher->assertDispatched(EventClass::class, $callback);
$dispatcher->assertNotDispatched(EventClass::class);
$dispatcher->assertNothingDispatched();
$dispatcher->getDispatchedCount(EventClass::class);
$dispatcher->getFirstEvent(EventClass::class);
$dispatcher->dispatched(EventClass::class); // Returns Collection
```

---

## Creating New Event

### Step 1: Create Event Class

```php
// app/Domain/Sales/YourFeature/Events/YourFeatureApproved.php

readonly class YourFeatureApproved
{
    public function __construct(
        public int $featureId,
        public string $featureNumber,
        public DocumentStatus $status,
        public Carbon $timestamp,
        public ?int $userId = null
    ) {}

    public static function fromModel(YourModel $model, ?int $userId = null): self
    {
        return new self(
            featureId: $model->id,
            featureNumber: $model->number,
            status: $model->status,
            timestamp: now(),
            userId: $userId ?? auth()->id()
        );
    }
}
```

### Step 2: Create/Update Subscriber

```php
// app/Listeners/Sales/YourFeatureEventSubscriber.php

class YourFeatureEventSubscriber
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            YourFeatureApproved::class => 'handleApproved',
        ];
    }

    public function handleApproved(YourFeatureApproved $event): void
    {
        // Log, notify, trigger side effects
    }
}
```

### Step 3: Register Subscriber

```php
// app/Providers/EventServiceProvider.php
protected $subscribe = [
    // ...
    YourFeatureEventSubscriber::class,
];
```

---

## Event Naming Conventions

| Pattern | Example |
|---------|---------|
| Status Change | `{Entity}StatusChanged` |
| Specific Action | `{Entity}{ActionPastTense}` |
| Process Stage | `{Entity}{Stage}` |
| Outcome | `{Entity}{Result}` |

**Examples:** `InvoiceSent`, `QuotationApproved`, `ProjectStarted`, `QuotationWon`

---

## When NOT to Use Events ⚠️

### Events Are for Side Effects, Not Core Operations

Events are designed for **decoupled side effects** that can fail independently. They are NOT suitable for **core operations** that must succeed or fail atomically.

### ❌ WRONG: Event-Driven Inventory

```php
// DON'T DO THIS - Breaks transactional consistency
public function complete(GoodsReceiptNote $grn): GoodsReceiptNote
{
    return DB::transaction(function () use ($grn) {
        $grn->transitionTo(DocumentStatus::Completed);

        // ❌ WRONG - Event listener runs OUTSIDE transaction
        $this->eventDispatcher->dispatch(new GoodsReceived($grn));

        return $grn;
    });
}

// Listener runs async or after transaction commits
class InventoryListener
{
    public function handle(GoodsReceived $event): void
    {
        // If this fails, GRN is already "Completed" but stock not updated!
        $this->inventoryService->stockIn(...);
    }
}
```

**Problems:**
- If listener fails, GRN shows "Completed" but inventory not updated
- No rollback possible - transaction already committed
- Data inconsistency between documents and stock levels

### ✅ CORRECT: Direct Call Within Transaction

```php
// DO THIS - Inventory within same transaction
public function complete(GoodsReceiptNote $grn): GoodsReceiptNote
{
    return DB::transaction(function () use ($grn) {
        // Stock update within transaction
        foreach ($grn->items as $item) {
            $this->inventoryService->stockIn(
                $item->product,
                $grn->warehouse,
                $item->quantity_received,
                $item->unit_price,
                "GRN: {$grn->grn_number}"
            );
        }

        // Status change after inventory succeeds
        $grn->transitionTo(DocumentStatus::Completed);

        // Event for side effects (audit, notifications) - OK to fail
        $this->eventDispatcher->dispatch(new GoodsReceiptCompleted($grn));

        return $grn;
    });
}
```

### Decision Matrix: Events vs Direct Calls

| Operation Type | Use Events? | Why |
|----------------|-------------|-----|
| **Audit logging** | ✅ Yes | Failure shouldn't block business operation |
| **Email notifications** | ✅ Yes | Can retry independently |
| **Dashboard metrics** | ✅ Yes | Eventually consistent is acceptable |
| **Inventory movements** | ❌ No | Must be atomic with document status |
| **Journal entries** | ❌ No | Financial data requires consistency |
| **Stock reservations** | ❌ No | Must match document state |

### Core Operations That Need Transactions

These operations must happen **within the same transaction** as the triggering action:

| Trigger | Core Operation | Why |
|---------|----------------|-----|
| GRN Completed | Stock In | Stock level must match receipt status |
| Delivery Order Shipped | Stock Out | Inventory must reflect shipment |
| Work Order Completed | Finished Goods In | Production must match output |
| Material Requisition Issued | Raw Materials Out | Consumption must be atomic |
| Sales Return Approved | Stock In + Credit Note | Return must be complete |

### Acceptable Event-Based Side Effects

These can safely use events because eventual consistency is acceptable:

| Event | Side Effect | Why OK to Use Events |
|-------|-------------|---------------------|
| `InvoiceSent` | Send email to customer | Email can be retried |
| `QuotationApproved` | Notify sales manager | Notification not critical |
| `WorkOrderCompleted` | Update dashboard stats | Dashboard can catch up |
| `PaymentReceived` | Log audit trail | Audit can happen async |

### Pattern: Synchronous Core + Async Side Effects

```php
public function ship(DeliveryOrder $do): DeliveryOrder
{
    return DB::transaction(function () use ($do) {
        // SYNCHRONOUS: Core operation (must succeed together)
        $this->inventoryService->stockOut(...);  // Direct call
        $do->transitionTo(DocumentStatus::Shipped);

        return $do;
    });

    // ASYNC: Side effects (outside transaction, OK to fail)
    $this->eventDispatcher->dispatch(new DeliveryOrderShipped($do));
}
```

### Lesson Learned (Jan 2026)

Original architecture plan proposed event-driven inventory decoupling. Analysis revealed this would **break data consistency** for financial/inventory systems where:

1. Failed inventory update should fail the entire operation
2. Stock levels must match document status exactly
3. Audit trail requires atomic operations
4. Rollback must restore complete state

**Result:** Inventory operations remain direct service calls within transactions. Events are used only for non-critical side effects.
