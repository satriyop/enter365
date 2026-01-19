---
pattern: event-listener
title: "Event-Listener Pattern"
location: app/Domain/*/Events/, app/Infrastructure/Listeners/
tags: [ddd, events, decoupling]
updated: 2026-01-19
---

# Event-Listener Pattern

## AI Agent Quick Reference

**Use this pattern when:**
- Decoupling side effects from core business logic
- Creating audit trails or notifications
- Triggering follow-up actions (journal entries, inventory updates)
- Enabling pluggable behavior

**Key locations:**
- Domain Events: `/app/Domain/{Domain}/{Entity}/Events/`
- Listeners: `/app/Infrastructure/Listeners/{Domain}/`
- Event Dispatcher Interface: `/app/Contracts/Events/EventDispatcherInterface.php`

---

## Architecture Overview

```
Domain Layer (fires events)         Infrastructure Layer (handles events)
┌──────────────────────────┐        ┌─────────────────────────────────┐
│ InvoiceStateMachine      │        │ Listeners/Sales/                │
│   ├── InvoiceSent        │───────>│   ├── PostInvoiceToJournal     │
│   ├── InvoiceFullyPaid   │        │   ├── NotifyCustomer           │
│   └── InvoiceVoided      │        │   └── UpdateCustomerBalance    │
└──────────────────────────┘        └─────────────────────────────────┘
```

---

## Existing Domain Events

### Accounting Events

| Event | Trigger | Typical Listeners |
|-------|---------|-------------------|
| FiscalPeriodClosed | Period closing complete | Lock journal entries |
| FiscalPeriodLocked | Period locked | Prevent modifications |
| FiscalPeriodReopened | Period reopened | Allow modifications |

### Sales Events

| Event | Trigger | Typical Listeners |
|-------|---------|-------------------|
| InvoiceSent | Invoice posted | Create AR journal, notify customer |
| InvoiceFullyPaid | Balance reaches zero | Update customer status |
| InvoiceOverdue | Past due date | Send reminder |
| InvoiceVoided | Invoice cancelled | Reverse journal entries |
| QuotationSubmitted | Quote sent for approval | Notify approver |
| QuotationApproved | Quote approved | Log activity |
| QuotationWon | Quote converted to order | Create invoice |
| QuotationLost | Quote rejected/expired | Update statistics |
| DeliveryOrderConfirmed | DO confirmed | - |
| DeliveryOrderShipped | DO shipped | Deduct inventory |
| DeliveryOrderDelivered | DO delivered | Complete delivery |
| SalesReturnApproved | Return approved | Process return |
| SalesReturnCompleted | Return processed | Update inventory, journal |
| PaymentReceived | Payment recorded | Update invoice balance |

### Purchasing Events

| Event | Trigger | Typical Listeners |
|-------|---------|-------------------|
| BillReceived | Bill entered | Create AP journal |
| BillFullyPaid | Bill paid in full | Update vendor status |
| BillVoided | Bill cancelled | Reverse journal entries |
| PurchaseOrderSubmitted | PO sent for approval | Notify approver |
| PurchaseOrderApproved | PO approved | Log activity |
| PurchaseOrderReceived | All items received | Complete PO |
| PurchaseReturnApproved | Return approved | Process return |
| PurchaseReturnCompleted | Return processed | Update inventory, journal |

### Project Events

| Event | Trigger | Typical Listeners |
|-------|---------|-------------------|
| ProjectStarted | Project activated | Initialize cost tracking |
| ProjectCompleted | Project finished | Finalize costs |
| ProjectOnHold | Project paused | Pause cost accrual |
| ProjectCancelled | Project cancelled | Close out costs |

---

## Event Structure

### Domain Event Class

```php
<?php
// File: app/Domain/Sales/Invoices/Events/InvoiceSent.php

declare(strict_types=1);

namespace App\Domain\Sales\Invoices\Events;

use App\Models\Sales\Invoice;
use DateTimeImmutable;

/**
 * Fired when an invoice is sent to the customer.
 *
 * This event triggers:
 * - Journal entry creation (AR debit, Revenue credit)
 * - Customer notification
 * - Activity logging
 */
final readonly class InvoiceSent
{
    public function __construct(
        public int $invoiceId,
        public string $invoiceNumber,
        public int $totalAmount,
        public string $currency,
        public ?int $customerId,
        public ?int $userId,
        public DateTimeImmutable $occurredAt
    ) {}

    /**
     * Factory method from Invoice model.
     */
    public static function fromInvoice(Invoice $invoice, ?int $userId = null): self
    {
        return new self(
            invoiceId: $invoice->id,
            invoiceNumber: $invoice->invoice_number,
            totalAmount: $invoice->total_amount,
            currency: $invoice->currency,
            customerId: $invoice->contact_id,
            userId: $userId,
            occurredAt: new DateTimeImmutable()
        );
    }
}
```

### Event with Status Change

```php
<?php
// File: app/Domain/Sales/Invoices/Events/InvoiceStatusChanged.php

declare(strict_types=1);

namespace App\Domain\Sales\Invoices\Events;

use App\Enums\DocumentStatus;
use DateTimeImmutable;

/**
 * Generic status change event for invoices.
 *
 * Useful for audit logging and activity tracking.
 */
final readonly class InvoiceStatusChanged
{
    public function __construct(
        public int $invoiceId,
        public DocumentStatus $from,
        public DocumentStatus $to,
        public ?int $userId,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable()
    ) {}
}
```

---

## Listener Structure

### Infrastructure Listener

```php
<?php
// File: app/Infrastructure/Listeners/Sales/PostInvoiceToJournal.php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use App\Contracts\Services\Domains\JournalServiceInterface;
use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Models\Sales\Invoice;

class PostInvoiceToJournal
{
    public function __construct(
        private JournalServiceInterface $journalService
    ) {}

    public function handle(InvoiceSent $event): void
    {
        $invoice = Invoice::findOrFail($event->invoiceId);

        $this->journalService->createFromInvoice($invoice);
    }
}
```

### Conditional Listener

```php
<?php
// File: app/Infrastructure/Listeners/Sales/NotifyCustomerOfInvoice.php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Models\Sales\Invoice;
use App\Notifications\InvoiceSentNotification;

class NotifyCustomerOfInvoice
{
    public function handle(InvoiceSent $event): void
    {
        $invoice = Invoice::with('contact')->findOrFail($event->invoiceId);

        // Only notify if customer has email
        if ($invoice->contact?->email) {
            $invoice->contact->notify(new InvoiceSentNotification($invoice));
        }
    }

    /**
     * Determine if listener should be queued.
     */
    public function shouldQueue(InvoiceSent $event): bool
    {
        return true;  // Run asynchronously
    }
}
```

---

## Event Dispatcher Interface

For testability, events go through an interface:

```php
<?php
// File: app/Contracts/Events/EventDispatcherInterface.php

declare(strict_types=1);

namespace App\Contracts\Events;

interface EventDispatcherInterface
{
    /**
     * Dispatch a domain event.
     */
    public function dispatch(object $event): void;
}
```

### Production Implementation

```php
<?php
// File: app/Infrastructure/Events/LaravelEventDispatcher.php

declare(strict_types=1);

namespace App\Infrastructure\Events;

use App\Contracts\Events\EventDispatcherInterface;

class LaravelEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): void
    {
        event($event);
    }
}
```

### Testing Implementation

```php
<?php
// File: app/Infrastructure/Events/NullEventDispatcher.php

declare(strict_types=1);

namespace App\Infrastructure\Events;

use App\Contracts\Events\EventDispatcherInterface;

/**
 * Null dispatcher for unit tests where events should be suppressed.
 */
class NullEventDispatcher implements EventDispatcherInterface
{
    public array $dispatchedEvents = [];

    public function dispatch(object $event): void
    {
        $this->dispatchedEvents[] = $event;
    }

    public function getDispatchedEvents(): array
    {
        return $this->dispatchedEvents;
    }

    public function assertDispatched(string $eventClass): void
    {
        $found = collect($this->dispatchedEvents)
            ->contains(fn ($event) => $event instanceof $eventClass);

        if (!$found) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Event {$eventClass} was not dispatched."
            );
        }
    }
}
```

### Service Provider Binding

```php
<?php
// File: app/Providers/AppServiceProvider.php

public function register(): void
{
    $this->app->bind(
        EventDispatcherInterface::class,
        LaravelEventDispatcher::class
    );
}
```

---

## Registering Listeners

### Via EventServiceProvider

```php
<?php
// File: app/Providers/EventServiceProvider.php

use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Domain\Sales\Invoices\Events\InvoiceFullyPaid;
use App\Domain\Sales\Invoices\Events\InvoiceVoided;
use App\Infrastructure\Listeners\Sales\PostInvoiceToJournal;
use App\Infrastructure\Listeners\Sales\NotifyCustomerOfInvoice;
use App\Infrastructure\Listeners\Sales\UpdateCustomerBalance;
use App\Infrastructure\Listeners\Sales\ReverseInvoiceJournal;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Invoice Events
        InvoiceSent::class => [
            PostInvoiceToJournal::class,
            NotifyCustomerOfInvoice::class,
        ],
        InvoiceFullyPaid::class => [
            UpdateCustomerBalance::class,
        ],
        InvoiceVoided::class => [
            ReverseInvoiceJournal::class,
        ],

        // Quotation Events
        QuotationApproved::class => [
            LogQuotationActivity::class,
        ],
        QuotationWon::class => [
            CreateInvoiceFromQuotation::class,
        ],

        // Purchasing Events
        BillReceived::class => [
            PostBillToJournal::class,
        ],
        PurchaseOrderApproved::class => [
            LogPurchaseOrderActivity::class,
        ],
    ];
}
```

### Via Attribute (Laravel 11+)

```php
<?php
// File: app/Infrastructure/Listeners/Sales/PostInvoiceToJournal.php

use App\Domain\Sales\Invoices\Events\InvoiceSent;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

#[ListensTo(InvoiceSent::class)]
class PostInvoiceToJournal implements ShouldHandleEventsAfterCommit
{
    // ...
}
```

---

## Testing Events

### Testing Event Dispatch

```php
<?php

use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Domain\Sales\Invoices\Events\InvoiceStatusChanged;
use App\Services\Sales\InvoiceService;
use Illuminate\Support\Facades\Event;

describe('Invoice Events', function () {

    it('dispatches InvoiceSent when invoice is posted', function () {
        Event::fake();

        $invoice = Invoice::factory()->draft()->create();
        $service = app(InvoiceService::class);

        $service->send($invoice);

        Event::assertDispatched(InvoiceSent::class, function ($event) use ($invoice) {
            return $event->invoiceId === $invoice->id;
        });
    });

    it('dispatches InvoiceStatusChanged on any status change', function () {
        Event::fake();

        $invoice = Invoice::factory()->draft()->create();
        $service = app(InvoiceService::class);

        $service->send($invoice);

        Event::assertDispatched(InvoiceStatusChanged::class, function ($event) {
            return $event->from === DocumentStatus::Draft
                && $event->to === DocumentStatus::Sent;
        });
    });
});
```

### Testing Listeners

```php
<?php

use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Infrastructure\Listeners\Sales\PostInvoiceToJournal;
use App\Models\Accounting\JournalEntry;
use App\Models\Sales\Invoice;

describe('PostInvoiceToJournal', function () {

    it('creates journal entry when invoice is sent', function () {
        $invoice = Invoice::factory()->create([
            'total_amount' => 100_00,
            'tax_amount' => 11_00,
        ]);

        $event = InvoiceSent::fromInvoice($invoice);
        $listener = app(PostInvoiceToJournal::class);

        $listener->handle($event);

        $journal = JournalEntry::where('source_id', $invoice->id)
            ->where('source_type', Invoice::class)
            ->first();

        expect($journal)->not->toBeNull();
        expect($journal->lines)->toHaveCount(3); // AR, Revenue, Tax
    });
});
```

### Testing with Null Dispatcher

```php
<?php

use App\Domain\Sales\Invoices\InvoiceStateMachine;
use App\Infrastructure\Events\NullEventDispatcher;
use App\Enums\DocumentStatus;

describe('InvoiceStateMachine with NullEventDispatcher', function () {

    it('records dispatched events', function () {
        $dispatcher = new NullEventDispatcher();
        $invoice = Invoice::factory()->draft()->create();

        $sm = new InvoiceStateMachine($invoice, $dispatcher);
        $sm->transitionTo(DocumentStatus::Sent);

        $dispatcher->assertDispatched(InvoiceStatusChanged::class);
    });
});
```

---

## Queued Listeners

For long-running operations, queue listeners:

```php
<?php

use Illuminate\Contracts\Queue\ShouldQueue;

class SendInvoiceEmail implements ShouldQueue
{
    public $queue = 'emails';

    public function handle(InvoiceSent $event): void
    {
        // Long-running email operation
    }
}
```

---

## Event Subscriber

For related events, use a subscriber:

```php
<?php
// File: app/Infrastructure/Listeners/Sales/InvoiceEventSubscriber.php

namespace App\Infrastructure\Listeners\Sales;

use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Domain\Sales\Invoices\Events\InvoiceFullyPaid;
use App\Domain\Sales\Invoices\Events\InvoiceVoided;
use Illuminate\Events\Dispatcher;

class InvoiceEventSubscriber
{
    public function handleInvoiceSent(InvoiceSent $event): void
    {
        // ...
    }

    public function handleInvoicePaid(InvoiceFullyPaid $event): void
    {
        // ...
    }

    public function handleInvoiceVoided(InvoiceVoided $event): void
    {
        // ...
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            InvoiceSent::class => 'handleInvoiceSent',
            InvoiceFullyPaid::class => 'handleInvoicePaid',
            InvoiceVoided::class => 'handleInvoiceVoided',
        ];
    }
}
```

Register in EventServiceProvider:

```php
protected $subscribe = [
    InvoiceEventSubscriber::class,
];
```

---

## Related Documents

- [Domain Layer Architecture](../01-architecture/domain-layer.md)
- [State Machine Pattern](./state-machine-pattern.md)
- [Service Pattern](./service-pattern.md)
