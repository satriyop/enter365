---
pattern: state-machine
title: "State Machine Pattern"
location: app/Domain/*/
tags: [ddd, domain, workflow]
updated: 2026-01-19
---

# State Machine Pattern

## AI Agent Quick Reference

**Use this pattern when:**
- Creating document types with workflow states (Invoice, PO, Work Order)
- Enforcing valid state transitions
- Firing events on state changes
- Adding lifecycle hooks (before/after transitions)

**Key files:**
- Base class: `/app/Domain/Core/AbstractStateMachine.php`
- Example: `/app/Domain/Sales/Invoices/InvoiceStateMachine.php`

---

## Existing State Machines

| Domain | StateMachine | Location |
|--------|--------------|----------|
| Accounting | FiscalPeriodStateMachine | `app/Domain/Accounting/FiscalPeriods/` |
| Sales | InvoiceStateMachine | `app/Domain/Sales/Invoices/` |
| Sales | QuotationStateMachine | `app/Domain/Sales/Quotations/` |
| Sales | DeliveryOrderStateMachine | `app/Domain/Sales/DeliveryOrders/` |
| Sales | SalesReturnStateMachine | `app/Domain/Sales/SalesReturns/` |
| Purchasing | BillStateMachine | `app/Domain/Purchasing/Bills/` |
| Purchasing | PurchaseOrderStateMachine | `app/Domain/Purchasing/PurchaseOrders/` |
| Purchasing | PurchaseReturnStateMachine | `app/Domain/Purchasing/PurchaseReturns/` |
| Projects | ProjectStateMachine | `app/Domain/Projects/` |

---

## Creating a New State Machine

### Step 1: Create the StateMachine Class

```php
<?php
// File: app/Domain/{Domain}/{Entity}/{Entity}StateMachine.php

declare(strict_types=1);

namespace App\Domain\{Domain}\{Entities};

use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Core\AbstractStateMachine;
use App\Domain\{Domain}\{Entities}\Events\{Entity}StatusChanged;
use App\Enums\DocumentStatus;
use App\Models\{Domain}\{Entity};

class {Entity}StateMachine extends AbstractStateMachine
{
    private {Entity} ${entity};

    public function __construct({Entity} ${entity}, ?EventDispatcherInterface $eventDispatcher = null)
    {
        parent::__construct(${entity}->status, $eventDispatcher);
        $this->{entity} = ${entity};
    }

    /**
     * Factory method for cleaner instantiation.
     */
    public static function from{Entity}(
        {Entity} ${entity},
        ?EventDispatcherInterface $eventDispatcher = null
    ): self {
        return new self(${entity}, $eventDispatcher);
    }

    /**
     * Define valid state transitions.
     *
     * Format: 'current_state' => ['valid_target_1', 'valid_target_2']
     */
    protected function getTransitions(): array
    {
        return [
            DocumentStatus::Draft->value => [
                DocumentStatus::Submitted->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Submitted->value => [
                DocumentStatus::Approved->value,
                DocumentStatus::Rejected->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Approved->value => [
                DocumentStatus::Completed->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Completed->value => [], // Terminal state
            DocumentStatus::Rejected->value => [],  // Terminal state
            DocumentStatus::Cancelled->value => [], // Terminal state
        ];
    }

    /**
     * Context data included in status change events.
     */
    protected function getContextData(): array
    {
        return [
            '{entity}_id' => $this->{entity}->id,
            '{entity}_number' => $this->{entity}->{entity}_number,
            'total_amount' => $this->{entity}->total_amount,
        ];
    }

    /**
     * Persist status change to database.
     */
    protected function updateDocumentStatus(DocumentStatus $status): void
    {
        $this->{entity}->status = $status;
        $this->{entity}->save();
    }

    /**
     * Document type name for error messages.
     */
    protected function getDocumentType(): string
    {
        return '{Entity}';  // Or Indonesian: 'Faktur', 'Pesanan Pembelian'
    }

    /**
     * Document ID for events.
     */
    protected function getDocumentId(): int
    {
        return $this->{entity}->id;
    }

    /**
     * Event class for status changes.
     */
    protected function getStatusChangedEvent(): string
    {
        return {Entity}StatusChanged::class;
    }

    // ─────────────────────────────────────────────────────────────
    // Business Rule Methods
    // ─────────────────────────────────────────────────────────────

    public function canSubmit(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft
            && $this->{entity}->items()->exists();
    }

    public function canApprove(): bool
    {
        return $this->currentStatus === DocumentStatus::Submitted;
    }

    public function canComplete(): bool
    {
        return $this->currentStatus === DocumentStatus::Approved;
    }

    public function canEdit(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft;
    }

    public function canDelete(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft;
    }

    // ─────────────────────────────────────────────────────────────
    // Lifecycle Hooks (before/after each state)
    // ─────────────────────────────────────────────────────────────

    protected function beforeSubmitted(DocumentStatus $from, DocumentStatus $to): void
    {
        // Validate before transitioning to Submitted
        if (!$this->{entity}->items()->exists()) {
            throw StateTransitionException::actionNotAvailable(
                'submit',
                $from->value,
                'Document has no items.'
            );
        }
    }

    protected function afterSubmitted(DocumentStatus $from, DocumentStatus $to): void
    {
        // Fire specific event after transitioning to Submitted
        $this->eventDispatcher->dispatch(
            {Entity}Submitted::from{Entity}($this->{entity}, $this->getContextUserId())
        );
    }

    protected function afterApproved(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(
            {Entity}Approved::from{Entity}($this->{entity}, $this->getContextUserId())
        );
    }
}
```

### Step 2: Create Domain Events

```php
<?php
// File: app/Domain/{Domain}/{Entities}/Events/{Entity}StatusChanged.php

declare(strict_types=1);

namespace App\Domain\{Domain}\{Entities}\Events;

use App\Enums\DocumentStatus;
use DateTimeImmutable;

final readonly class {Entity}StatusChanged
{
    public function __construct(
        public int ${entity}Id,
        public DocumentStatus $from,
        public DocumentStatus $to,
        public ?int $userId,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable()
    ) {}
}
```

```php
<?php
// File: app/Domain/{Domain}/{Entities}/Events/{Entity}Submitted.php

declare(strict_types=1);

namespace App\Domain\{Domain}\{Entities}\Events;

use App\Models\{Domain}\{Entity};
use DateTimeImmutable;

final readonly class {Entity}Submitted
{
    public function __construct(
        public int ${entity}Id,
        public string ${entity}Number,
        public int $totalAmount,
        public ?int $userId,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable()
    ) {}

    public static function from{Entity}({Entity} ${entity}, ?int $userId = null): self
    {
        return new self(
            {entity}Id: ${entity}->id,
            {entity}Number: ${entity}->{entity}_number,
            totalAmount: ${entity}->total_amount,
            userId: $userId,
            occurredAt: new DateTimeImmutable()
        );
    }
}
```

### Step 3: Use in Service

```php
<?php
// File: app/Services/{Domain}/{Entity}Service.php

public function submit({Entity} ${entity}, ?int $userId = null): {Entity}
{
    return DB::transaction(function () use (${entity}, $userId) {
        $stateMachine = {Entity}StateMachine::from{Entity}(
            ${entity},
            $this->eventDispatcher
        );

        $stateMachine->transitionTo(
            DocumentStatus::Submitted,
            ['user_id' => $userId ?? auth()->id()]
        );

        return ${entity}->fresh();
    });
}

public function approve({Entity} ${entity}, ?int $userId = null): {Entity}
{
    return DB::transaction(function () use (${entity}, $userId) {
        $stateMachine = {Entity}StateMachine::from{Entity}(
            ${entity},
            $this->eventDispatcher
        );

        $stateMachine->transitionTo(
            DocumentStatus::Approved,
            ['user_id' => $userId ?? auth()->id()]
        );

        return ${entity}->fresh();
    });
}
```

### Step 4: Register Event Listeners

```php
// File: app/Providers/EventServiceProvider.php

use App\Domain\{Domain}\{Entities}\Events\{Entity}Submitted;
use App\Domain\{Domain}\{Entities}\Events\{Entity}Approved;
use App\Infrastructure\Listeners\{Domain}\Notify{Entity}Submitted;
use App\Infrastructure\Listeners\{Domain}\Post{Entity}ToJournal;

protected $listen = [
    {Entity}Submitted::class => [
        Notify{Entity}Submitted::class,
    ],
    {Entity}Approved::class => [
        Post{Entity}ToJournal::class,
    ],
];
```

---

## Base Class Methods

### Required Abstract Methods

| Method | Purpose |
|--------|---------|
| `getTransitions()` | Define valid state transitions |
| `getContextData()` | Data to include in events |
| `updateDocumentStatus()` | Persist status to DB |
| `getDocumentType()` | Name for error messages |
| `getDocumentId()` | ID for events |
| `getStatusChangedEvent()` | Event class name |

### Optional Lifecycle Hooks

| Hook | Timing |
|------|--------|
| `beforeTransition($from, $to)` | Before any transition |
| `afterTransition($from, $to)` | After any transition |
| `before{Status}($from, $to)` | Before specific status (e.g., `beforeSent`) |
| `after{Status}($from, $to)` | After specific status (e.g., `afterSent`) |

### Public Methods

| Method | Returns | Purpose |
|--------|---------|---------|
| `getCurrentStatus()` | DocumentStatus | Get current status |
| `canTransitionTo($target)` | bool | Check if transition is valid |
| `getNextValidStatuses()` | array | Get valid next states |
| `transitionTo($target, $context)` | void | Execute transition |

---

## Testing State Machines

```php
<?php

use App\Domain\Sales\Invoices\InvoiceStateMachine;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Infrastructure\Events\NullEventDispatcher;
use App\Models\Sales\Invoice;

describe('InvoiceStateMachine', function () {

    it('allows draft to sent transition', function () {
        $invoice = Invoice::factory()->draft()->create();
        $sm = new InvoiceStateMachine($invoice, new NullEventDispatcher());

        expect($sm->canTransitionTo(DocumentStatus::Sent))->toBeTrue();
    });

    it('prevents cancelled to sent transition', function () {
        $invoice = Invoice::factory()->cancelled()->create();
        $sm = new InvoiceStateMachine($invoice, new NullEventDispatcher());

        expect($sm->canTransitionTo(DocumentStatus::Sent))->toBeFalse();
    });

    it('transitions and updates status', function () {
        $invoice = Invoice::factory()->draft()->has(
            InvoiceItem::factory()
        )->create();

        $sm = new InvoiceStateMachine($invoice, new NullEventDispatcher());
        $sm->transitionTo(DocumentStatus::Sent);

        expect($invoice->fresh()->status)->toBe(DocumentStatus::Sent);
    });

    it('throws on invalid transition', function () {
        $invoice = Invoice::factory()->cancelled()->create();
        $sm = new InvoiceStateMachine($invoice, new NullEventDispatcher());

        expect(fn() => $sm->transitionTo(DocumentStatus::Sent))
            ->toThrow(StateTransitionException::class);
    });

    it('validates business rules in beforeSent hook', function () {
        $invoice = Invoice::factory()->draft()->create(); // No items
        $sm = new InvoiceStateMachine($invoice, new NullEventDispatcher());

        expect(fn() => $sm->transitionTo(DocumentStatus::Sent))
            ->toThrow(StateTransitionException::class, 'tidak memiliki item');
    });
});
```

---

## Common Patterns

### Conditional Transitions

```php
protected function getTransitions(): array
{
    $transitions = [
        DocumentStatus::Draft->value => [
            DocumentStatus::Submitted->value,
            DocumentStatus::Cancelled->value,
        ],
    ];

    // Add conditional transitions
    if ($this->invoice->requires_approval) {
        $transitions[DocumentStatus::Submitted->value] = [
            DocumentStatus::Approved->value,
            DocumentStatus::Rejected->value,
        ];
    } else {
        $transitions[DocumentStatus::Submitted->value] = [
            DocumentStatus::Completed->value,
        ];
    }

    return $transitions;
}
```

### Workflow with Partial States

```php
// Invoice example with Partial payment state
protected function getTransitions(): array
{
    return [
        DocumentStatus::Draft->value => [
            DocumentStatus::Sent->value,
            DocumentStatus::Cancelled->value,
        ],
        DocumentStatus::Sent->value => [
            DocumentStatus::Partial->value,   // Partial payment received
            DocumentStatus::Paid->value,      // Full payment received
            DocumentStatus::Overdue->value,   // Due date passed
            DocumentStatus::Cancelled->value,
        ],
        DocumentStatus::Partial->value => [
            DocumentStatus::Paid->value,      // Remaining paid
            DocumentStatus::Overdue->value,
            DocumentStatus::Cancelled->value,
        ],
        DocumentStatus::Overdue->value => [
            DocumentStatus::Partial->value,   // Payment received while overdue
            DocumentStatus::Paid->value,
            DocumentStatus::Cancelled->value,
        ],
        DocumentStatus::Paid->value => [
            DocumentStatus::Cancelled->value, // Void paid invoice
        ],
        DocumentStatus::Cancelled->value => [],
    ];
}
```

---

## Related Documents

- [Domain Layer Architecture](../01-architecture/domain-layer.md)
- [Event-Listener Pattern](./event-listener-pattern.md)
- [Service Pattern](./service-pattern.md)
