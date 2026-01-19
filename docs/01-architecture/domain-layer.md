# Domain Layer Architecture

> **DDD-Lite Implementation** - Enter365 uses tactical DDD patterns (StateMachines, Value Objects, Domain Events) without full-blown strategic DDD.

---

## AI Agent Quick Reference

**Use this document when:**
- Creating new document types with workflow states
- Understanding how state transitions work
- Creating Value Objects for composite data
- Implementing domain events for side effects

**Related documents:**
- `/docs/07-code-patterns/state-machine-pattern.md` - Implementation guide
- `/docs/07-code-patterns/event-listener-pattern.md` - Event handling
- `/docs/08-adr/0003-service-layer-pattern.md` - Why services call domain layer

---

## Domain Layer Structure

```
app/Domain/
├── Core/
│   └── AbstractStateMachine.php          # Base class for all state machines
│
├── Accounting/
│   └── FiscalPeriods/
│       ├── FiscalPeriodStateMachine.php  # Fiscal period workflow
│       ├── Enums/
│       │   ├── ClosingStep.php           # Closing process steps
│       │   └── FiscalPeriodStatus.php    # Period statuses
│       ├── Events/                        # Domain events
│       │   ├── FiscalPeriodClosed.php
│       │   ├── FiscalPeriodLocked.php
│       │   └── ...
│       ├── ValueObjects/
│       │   ├── ClosingProgress.php       # Closing progress tracking
│       │   └── ClosingChecklist.php      # Items to complete before closing
│       └── Exceptions/
│           └── FiscalPeriodException.php
│
├── Sales/
│   ├── Invoices/
│   │   ├── InvoiceStateMachine.php       # Invoice workflow
│   │   ├── InvoiceCalculator.php         # Calculation logic
│   │   ├── InvoiceTotals.php             # Value object for totals
│   │   └── Events/
│   │       ├── InvoiceSent.php
│   │       ├── InvoiceFullyPaid.php
│   │       ├── InvoiceOverdue.php
│   │       └── InvoiceVoided.php
│   │
│   ├── Quotations/
│   │   ├── QuotationStateMachine.php
│   │   ├── QuotationCalculator.php
│   │   ├── QuotationTotals.php
│   │   ├── QuotationStatistics.php
│   │   ├── DiscountCalculator.php
│   │   ├── TaxCalculator.php
│   │   ├── OutcomeManager.php
│   │   ├── FollowUpManager.php
│   │   ├── QuotationItemCreator.php
│   │   ├── Enums/
│   │   │   ├── QuotationType.php
│   │   │   ├── QuotationPriority.php
│   │   │   └── QuotationOutcome.php
│   │   └── Events/
│   │       ├── QuotationSubmitted.php
│   │       ├── QuotationApproved.php
│   │       ├── QuotationWon.php
│   │       ├── QuotationLost.php
│   │       └── ...
│   │
│   ├── DeliveryOrders/
│   │   ├── DeliveryOrderStateMachine.php
│   │   └── Events/
│   │
│   └── SalesReturns/
│       ├── SalesReturnStateMachine.php
│       ├── Contracts/
│       │   └── ApprovalHandlerInterface.php
│       ├── Handlers/
│       │   ├── SalesReturnApprovalPipeline.php
│       │   ├── InventoryReturnHandler.php
│       │   └── JournalEntryHandler.php
│       └── Events/
│
├── Purchasing/
│   ├── Bills/
│   │   ├── BillStateMachine.php
│   │   └── Events/
│   │
│   ├── PurchaseOrders/
│   │   ├── PurchaseOrderStateMachine.php
│   │   ├── PurchaseOrderCalculator.php
│   │   ├── PurchaseOrderTotals.php
│   │   └── Events/
│   │
│   └── PurchaseReturns/
│       ├── PurchaseReturnStateMachine.php
│       ├── Handlers/
│       └── Events/
│
├── Projects/
│   ├── ProjectStateMachine.php
│   └── Events/
│
└── Shared/
    ├── SequenceNumberGenerator.php       # Document numbering
    └── DatabaseBackedNumberGenerator.php
```

---

## Pattern 1: State Machines

State machines enforce valid workflow transitions and fire domain events.

### Base Class

```php
// File: app/Domain/Core/AbstractStateMachine.php

abstract class AbstractStateMachine
{
    protected DocumentStatus $currentStatus;
    protected EventDispatcherInterface $eventDispatcher;

    // Must implement: define valid transitions
    abstract protected function getTransitions(): array;

    // Must implement: context data for events
    abstract protected function getContextData(): array;

    // Must implement: persist status change
    abstract protected function updateDocumentStatus(DocumentStatus $status): void;

    // Can transition check
    public function canTransitionTo(DocumentStatus $target): bool
    {
        $validTransitions = $this->getTransitions()[$this->currentStatus->value] ?? [];
        return in_array($target->value, $validTransitions, true);
    }

    // Execute transition with hooks
    public function transitionTo(DocumentStatus $target, array $context = []): void
    {
        if (!$this->canTransitionTo($target)) {
            throw StateTransitionException::invalidTransition(...);
        }

        $this->beforeTransition($from, $target);
        $this->performTransition($from, $target);
        $this->afterTransition($from, $target);

        // Fire domain event
        $this->eventDispatcher->dispatch(new ($this->getStatusChangedEvent())(...));
    }
}
```

### Implementation Example

```php
// File: app/Domain/Sales/Invoices/InvoiceStateMachine.php

class InvoiceStateMachine extends AbstractStateMachine
{
    private Invoice $invoice;

    public function __construct(Invoice $invoice, ?EventDispatcherInterface $eventDispatcher = null)
    {
        parent::__construct($invoice->status, $eventDispatcher);
        $this->invoice = $invoice;
    }

    public static function fromInvoice(Invoice $invoice): self
    {
        return new self($invoice);
    }

    protected function getTransitions(): array
    {
        return [
            DocumentStatus::Draft->value => [
                DocumentStatus::Sent->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Sent->value => [
                DocumentStatus::Partial->value,
                DocumentStatus::Paid->value,
                DocumentStatus::Overdue->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Partial->value => [
                DocumentStatus::Paid->value,
                DocumentStatus::Overdue->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Paid->value => [
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Overdue->value => [
                DocumentStatus::Partial->value,
                DocumentStatus::Paid->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Cancelled->value => [],  // Terminal state
        ];
    }

    // Business rule methods
    public function canPost(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft
            && $this->invoice->items()->exists();
    }

    public function canMarkAsPaid(): bool
    {
        return $this->invoice->paid_amount >= $this->invoice->total_amount;
    }

    // Lifecycle hooks
    protected function beforeSent(DocumentStatus $from, DocumentStatus $to): void
    {
        if (!$this->invoice->items()->exists()) {
            throw StateTransitionException::actionNotAvailable(
                'kirim', 'draft', 'Faktur tidak memiliki item.'
            );
        }
    }

    protected function afterSent(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(
            InvoiceSent::fromInvoice($this->invoice, $this->getContextUserId())
        );
    }
}
```

### Service Usage

```php
// File: app/Services/Sales/InvoiceService.php

public function send(Invoice $invoice, ?int $userId = null): Invoice
{
    return DB::transaction(function () use ($invoice, $userId) {
        $stateMachine = InvoiceStateMachine::fromInvoice($invoice, $this->eventDispatcher);

        $stateMachine->transitionTo(
            DocumentStatus::Sent,
            ['user_id' => $userId ?? auth()->id()]
        );

        return $invoice->fresh();
    });
}
```

---

## Pattern 2: Value Objects

Value Objects encapsulate composite data with calculation logic.

### Example: InvoiceTotals

```php
// File: app/Domain/Sales/Invoices/InvoiceTotals.php

final readonly class InvoiceTotals
{
    public function __construct(
        public int $subtotal,
        public int $discount,
        public int $taxable,
        public int $tax,
        public int $total,
        public int $paid,
        public int $balance
    ) {}

    public static function calculate(Invoice $invoice): self
    {
        $subtotal = $invoice->items->sum('line_total');
        $discount = $invoice->discount_amount ?? 0;
        $taxable = $subtotal - $discount;
        $tax = $invoice->include_tax ? (int) round($taxable * 0.11) : 0;
        $total = $taxable + $tax;
        $paid = $invoice->paid_amount ?? 0;
        $balance = $total - $paid;

        return new self($subtotal, $discount, $taxable, $tax, $total, $paid, $balance);
    }

    public function isPaid(): bool
    {
        return $this->balance <= 0;
    }

    public function isPartiallyPaid(): bool
    {
        return $this->paid > 0 && $this->balance > 0;
    }

    public function toArray(): array
    {
        return [
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'taxable' => $this->taxable,
            'tax' => $this->tax,
            'total' => $this->total,
            'paid' => $this->paid,
            'balance' => $this->balance,
        ];
    }
}
```

### Usage in API Resource

```php
// File: app/Http/Resources/Api/V1/Sales/InvoiceResource.php

public function toArray($request): array
{
    $totals = InvoiceTotals::calculate($this->resource);

    return [
        'id' => $this->id,
        'invoice_number' => $this->invoice_number,
        // ... other fields
        'totals' => $totals->toArray(),
        'is_paid' => $totals->isPaid(),
    ];
}
```

---

## Pattern 3: Calculators

Calculators contain complex business logic, separate from services.

### Example: InvoiceCalculator

```php
// File: app/Domain/Sales/Invoices/InvoiceCalculator.php

class InvoiceCalculator
{
    public function calculateLineTotal(InvoiceItem $item): int
    {
        $gross = $item->quantity * $item->unit_price;
        $discount = $this->calculateItemDiscount($item, $gross);
        return $gross - $discount;
    }

    public function calculateSubtotal(Invoice $invoice): int
    {
        return $invoice->items->sum(fn($item) => $this->calculateLineTotal($item));
    }

    public function calculateTax(Invoice $invoice): int
    {
        if (!$invoice->include_tax) {
            return 0;
        }

        $taxableAmount = $this->calculateSubtotal($invoice) - ($invoice->discount_amount ?? 0);
        return (int) round($taxableAmount * config('accounting.ppn_rate', 0.11));
    }

    public function recalculateTotals(Invoice $invoice): void
    {
        $invoice->update([
            'subtotal' => $this->calculateSubtotal($invoice),
            'tax_amount' => $this->calculateTax($invoice),
            'total_amount' => $this->calculateTotal($invoice),
        ]);
    }
}
```

---

## Pattern 4: Domain Events

Domain events decouple side effects from core business logic.

### Event Structure

```php
// File: app/Domain/Sales/Invoices/Events/InvoiceSent.php

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

### Listeners in Infrastructure

```php
// File: app/Infrastructure/Listeners/Sales/PostInvoiceToJournal.php

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

### Event Registration

```php
// File: app/Providers/EventServiceProvider.php (or bootstrap/app.php)

protected $listen = [
    InvoiceSent::class => [
        PostInvoiceToJournal::class,
        NotifyCustomerOfInvoice::class,
        UpdateCustomerBalance::class,
    ],
    InvoiceFullyPaid::class => [
        MarkProjectMilestoneComplete::class,
    ],
];
```

---

## Pattern 5: Approval Pipelines

Complex workflows use handler pipelines.

### Sales Return Approval Pipeline

```php
// File: app/Domain/Sales/SalesReturns/Handlers/SalesReturnApprovalPipeline.php

class SalesReturnApprovalPipeline
{
    private array $handlers = [];

    public function addHandler(ApprovalHandlerInterface $handler): self
    {
        $this->handlers[] = $handler;
        return $this;
    }

    public function process(SalesReturn $return): void
    {
        foreach ($this->handlers as $handler) {
            $handler->handle($return);
        }
    }
}

// Usage in Service
public function approve(SalesReturn $return): SalesReturn
{
    return DB::transaction(function () use ($return) {
        $pipeline = (new SalesReturnApprovalPipeline())
            ->addHandler(new InventoryReturnHandler($this->inventoryService))
            ->addHandler(new JournalEntryHandler($this->journalService));

        $pipeline->process($return);

        // ... state machine transition
    });
}
```

---

## Existing State Machines

| Domain | StateMachine | States | Key Transitions |
|--------|--------------|--------|-----------------|
| **Accounting** | FiscalPeriodStateMachine | Open → Closing → Closed → Locked | Cannot edit closed periods |
| **Sales** | InvoiceStateMachine | Draft → Sent → Partial/Paid/Overdue → Cancelled | Auto-overdue detection |
| **Sales** | QuotationStateMachine | Draft → Submitted → Approved → Won/Lost/Expired | Outcome tracking |
| **Sales** | DeliveryOrderStateMachine | Draft → Confirmed → Shipped → Delivered → Cancelled | Inventory deduction |
| **Sales** | SalesReturnStateMachine | Draft → Submitted → Approved → Completed/Rejected | Approval pipeline |
| **Purchasing** | BillStateMachine | Draft → Received → Partial/Paid → Cancelled | Payment tracking |
| **Purchasing** | PurchaseOrderStateMachine | Draft → Submitted → Approved → Partial/Received | GRN integration |
| **Purchasing** | PurchaseReturnStateMachine | Draft → Submitted → Approved → Completed/Rejected | Approval pipeline |
| **Projects** | ProjectStateMachine | Draft → Active → OnHold → Completed/Cancelled | Cost tracking gate |

---

## When to Use Domain Layer vs Service Layer

| Scenario | Use | Why |
|----------|-----|-----|
| Document state transitions | Domain (StateMachine) | Enforces valid transitions, fires events |
| Complex calculations | Domain (Calculator) | Reusable, testable in isolation |
| Composite data | Domain (Value Object) | Immutable, self-validating |
| Side effects | Domain (Events) + Infrastructure (Listeners) | Decoupled, replaceable |
| Orchestration | Service Layer | Coordinates domain objects |
| Transaction management | Service Layer | DB::transaction() wrapper |
| Authorization checks | Service/Controller | Policy enforcement |

---

## Creating New Domain Components

### New StateMachine Checklist

1. Create class extending `AbstractStateMachine`
2. Implement `getTransitions()` - define state flow
3. Implement `getContextData()` - data for events
4. Implement `updateDocumentStatus()` - persistence
5. Create domain events in `Events/` folder
6. Add business rule methods (`canPost()`, `canApprove()`, etc.)
7. Register event listeners in `EventServiceProvider`
8. Use in service via `DB::transaction()`

### New Value Object Checklist

1. Use `readonly class` with `public function __construct()`
2. Add static factory method (e.g., `calculate()`, `from()`)
3. Add behavior methods (e.g., `isPaid()`, `isOverdue()`)
4. Add `toArray()` for API serialization
5. Keep immutable - no setters

### New Calculator Checklist

1. Single responsibility - one aspect of calculation
2. Pure functions where possible
3. Accept models, return primitives or Value Objects
4. Inject via dependency injection in services

---

## Testing Domain Components

### StateMachine Tests

```php
it('transitions from draft to sent', function () {
    $invoice = Invoice::factory()->draft()->create();
    $stateMachine = InvoiceStateMachine::fromInvoice($invoice);

    $stateMachine->transitionTo(DocumentStatus::Sent);

    expect($invoice->fresh()->status)->toBe(DocumentStatus::Sent);
});

it('cannot transition from cancelled', function () {
    $invoice = Invoice::factory()->cancelled()->create();
    $stateMachine = InvoiceStateMachine::fromInvoice($invoice);

    expect($stateMachine->canTransitionTo(DocumentStatus::Sent))->toBeFalse();
});
```

### Value Object Tests

```php
it('calculates invoice totals correctly', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory()->count(2)->state(['unit_price' => 100_00, 'quantity' => 2]))
        ->create(['include_tax' => true]);

    $totals = InvoiceTotals::calculate($invoice);

    expect($totals->subtotal)->toBe(400_00);
    expect($totals->tax)->toBe(44_00);  // 11% of 400
    expect($totals->total)->toBe(444_00);
});
```

---

## Event Dispatcher Interface

For testability, events go through an interface:

```php
// File: app/Contracts/Events/EventDispatcherInterface.php

interface EventDispatcherInterface
{
    public function dispatch(object $event): void;
}

// Production: app/Infrastructure/Events/LaravelEventDispatcher.php
class LaravelEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): void
    {
        event($event);
    }
}

// Testing: app/Infrastructure/Events/NullEventDispatcher.php
class NullEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): void
    {
        // Do nothing - for isolated unit tests
    }
}
```

Usage in tests:

```php
it('fires InvoiceSent event on transition', function () {
    Event::fake();

    $invoice = Invoice::factory()->draft()->create();
    $service = app(InvoiceServiceInterface::class);

    $service->send($invoice);

    Event::assertDispatched(InvoiceSent::class);
});
```
