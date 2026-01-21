# Phase 6: State Machine Enhancement

> **Goal**: Strengthen state machines with guards, actions, and better workflow visualization.

## Current State

State machines exist for:
- Invoice, Bill, Quotation, DeliveryOrder
- PurchaseOrder, PurchaseReturn, SalesReturn
- WorkOrder, MaterialRequisition, SubcontractorWorkOrder
- Project, FiscalPeriod, StockOpname

---

## Deliverables

- [ ] Transition guards (pre-conditions)
- [ ] Transition actions (post-conditions)
- [ ] Workflow metadata for UI
- [ ] History tracking
- [ ] Batch transitions

---

## Part 1: Enhanced State Machine Base

### 1.1 Update Abstract State Machine

```php
<?php
// File: app/Domain/Core/AbstractStateMachine.php (enhanced)

declare(strict_types=1);

namespace App\Domain\Core;

use App\Contracts\Events\EventDispatcherInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Infrastructure\Events\LaravelEventDispatcher;

abstract class AbstractStateMachine
{
    protected DocumentStatus $currentStatus;
    protected EventDispatcherInterface $eventDispatcher;
    protected array $transitionContext = [];

    /** @var array<string, array<string, callable>> Guards by transition */
    protected array $guards = [];

    /** @var array<string, array<string, callable>> Actions by transition */
    protected array $actions = [];

    public function __construct(
        DocumentStatus $currentStatus,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->currentStatus = $currentStatus;
        $this->eventDispatcher = $eventDispatcher ?? app(LaravelEventDispatcher::class);
        $this->registerGuards();
        $this->registerActions();
    }

    /**
     * Register transition guards.
     * Override in subclasses to add guards.
     */
    protected function registerGuards(): void
    {
        // Default: no guards
    }

    /**
     * Register transition actions.
     * Override in subclasses to add actions.
     */
    protected function registerActions(): void
    {
        // Default: no actions
    }

    /**
     * Add guard for specific transition.
     *
     * @param string $from Source status value
     * @param string $to Target status value
     * @param callable $guard Function returning bool
     */
    protected function addGuard(string $from, string $to, callable $guard): void
    {
        $key = "{$from}->{$to}";
        $this->guards[$key][] = $guard;
    }

    /**
     * Add action for specific transition.
     *
     * @param string $from Source status value
     * @param string $to Target status value
     * @param callable $action Function to execute after transition
     */
    protected function addAction(string $from, string $to, callable $action): void
    {
        $key = "{$from}->{$to}";
        $this->actions[$key][] = $action;
    }

    /**
     * Define valid state transitions.
     *
     * @return array<string, array<string>>
     */
    abstract protected function getTransitions(): array;

    /**
     * Get context data for events.
     *
     * @return array<string, mixed>
     */
    abstract protected function getContextData(): array;

    /**
     * Persist status change.
     */
    abstract protected function updateDocumentStatus(DocumentStatus $status): void;

    /**
     * Get document type name.
     */
    abstract protected function getDocumentType(): string;

    /**
     * Get document ID.
     */
    abstract protected function getDocumentId(): int;

    /**
     * Get event class for status changes.
     */
    abstract protected function getStatusChangedEvent(): string;

    /**
     * Get current status.
     */
    public function getCurrentStatus(): DocumentStatus
    {
        return $this->currentStatus;
    }

    /**
     * Check if transition to target status is valid.
     */
    public function canTransitionTo(DocumentStatus $target): bool
    {
        $transitions = $this->getTransitions();
        $currentValue = $this->currentStatus->value;

        if (! isset($transitions[$currentValue])) {
            return false;
        }

        if (! in_array($target->value, $transitions[$currentValue])) {
            return false;
        }

        // Check guards
        return $this->passesGuards($currentValue, $target->value);
    }

    /**
     * Get all valid next statuses.
     *
     * @return array<DocumentStatus>
     */
    public function getNextValidStatuses(): array
    {
        $transitions = $this->getTransitions();
        $currentValue = $this->currentStatus->value;

        if (! isset($transitions[$currentValue])) {
            return [];
        }

        $valid = [];
        foreach ($transitions[$currentValue] as $targetValue) {
            if ($this->passesGuards($currentValue, $targetValue)) {
                $valid[] = DocumentStatus::from($targetValue);
            }
        }

        return $valid;
    }

    /**
     * Execute transition to new status.
     *
     * @param array<string, mixed> $context
     * @throws StateTransitionException
     */
    public function transitionTo(DocumentStatus $target, array $context = []): void
    {
        $this->transitionContext = $context;

        if (! $this->canTransitionTo($target)) {
            throw StateTransitionException::invalidTransition(
                $this->getDocumentType(),
                $this->currentStatus->value,
                $target->value
            );
        }

        $from = $this->currentStatus;
        $to = $target;

        // Execute lifecycle hooks
        $this->executeBeforeHooks($from, $to);

        // Persist
        $this->updateDocumentStatus($to);

        // Record history
        $this->recordHistory($from, $to);

        // Execute actions
        $this->executeActions($from->value, $to->value);

        // Execute lifecycle hooks
        $this->executeAfterHooks($from, $to);

        // Dispatch event
        $this->dispatchStatusChangedEvent($from, $to);

        // Update current status
        $this->currentStatus = $to;
    }

    /**
     * Check if all guards pass for transition.
     */
    protected function passesGuards(string $from, string $to): bool
    {
        $key = "{$from}->{$to}";

        if (! isset($this->guards[$key])) {
            return true;
        }

        foreach ($this->guards[$key] as $guard) {
            if (! $guard()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Execute all actions for transition.
     */
    protected function executeActions(string $from, string $to): void
    {
        $key = "{$from}->{$to}";

        if (! isset($this->actions[$key])) {
            return;
        }

        foreach ($this->actions[$key] as $action) {
            $action();
        }
    }

    /**
     * Record transition in history.
     */
    protected function recordHistory(DocumentStatus $from, DocumentStatus $to): void
    {
        // Override to record in status_history table
    }

    /**
     * Execute before hooks.
     */
    protected function executeBeforeHooks(DocumentStatus $from, DocumentStatus $to): void
    {
        // General before hook
        $method = 'beforeTransition';
        if (method_exists($this, $method)) {
            $this->$method($from, $to);
        }

        // Specific before hook (beforeSent, beforeApproved, etc.)
        $specificMethod = 'before' . ucfirst($to->name);
        if (method_exists($this, $specificMethod)) {
            $this->$specificMethod($from, $to);
        }
    }

    /**
     * Execute after hooks.
     */
    protected function executeAfterHooks(DocumentStatus $from, DocumentStatus $to): void
    {
        // Specific after hook (afterSent, afterApproved, etc.)
        $specificMethod = 'after' . ucfirst($to->name);
        if (method_exists($this, $specificMethod)) {
            $this->$specificMethod($from, $to);
        }

        // General after hook
        $method = 'afterTransition';
        if (method_exists($this, $method)) {
            $this->$method($from, $to);
        }
    }

    /**
     * Dispatch status changed event.
     */
    protected function dispatchStatusChangedEvent(DocumentStatus $from, DocumentStatus $to): void
    {
        $eventClass = $this->getStatusChangedEvent();

        $event = new $eventClass(
            documentId: $this->getDocumentId(),
            from: $from,
            to: $to,
            userId: $this->getContextUserId(),
            context: $this->getContextData()
        );

        $this->eventDispatcher->dispatch($event);
    }

    /**
     * Get user ID from transition context.
     */
    protected function getContextUserId(): ?int
    {
        return $this->transitionContext['user_id'] ?? auth()->id();
    }

    /**
     * Get workflow metadata for UI.
     *
     * @return array<string, mixed>
     */
    public function getWorkflowMetadata(): array
    {
        $transitions = $this->getTransitions();
        $validStatuses = $this->getAllValidStatuses();

        return [
            'current_status' => [
                'value' => $this->currentStatus->value,
                'label' => $this->currentStatus->label(),
                'color' => $this->currentStatus->color(),
                'is_terminal' => $this->currentStatus->isTerminal(),
            ],
            'next_statuses' => array_map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ], $this->getNextValidStatuses()),
            'all_statuses' => array_map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ], $validStatuses),
            'transitions' => $this->getTransitionMap(),
        ];
    }

    /**
     * Get all valid statuses for this document type.
     *
     * @return array<DocumentStatus>
     */
    protected function getAllValidStatuses(): array
    {
        $transitions = $this->getTransitions();
        $statuses = [];

        foreach ($transitions as $from => $targets) {
            $statuses[$from] = true;
            foreach ($targets as $to) {
                $statuses[$to] = true;
            }
        }

        return array_map(fn ($v) => DocumentStatus::from($v), array_keys($statuses));
    }

    /**
     * Get transition map for visualization.
     *
     * @return array<array{from: string, to: string, available: bool}>
     */
    protected function getTransitionMap(): array
    {
        $map = [];
        $transitions = $this->getTransitions();

        foreach ($transitions as $from => $targets) {
            foreach ($targets as $to) {
                $map[] = [
                    'from' => $from,
                    'to' => $to,
                    'available' => $from === $this->currentStatus->value
                        && $this->passesGuards($from, $to),
                ];
            }
        }

        return $map;
    }
}
```

---

## Part 2: Status History Tracking

### 2.1 Status History Model

```php
<?php
// File: app/Models/Core/StatusHistory.php

declare(strict_types=1);

namespace App\Models\Core;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StatusHistory extends Model
{
    protected $table = 'status_histories';

    public $timestamps = false;

    protected $fillable = [
        'statusable_type',
        'statusable_id',
        'from_status',
        'to_status',
        'user_id',
        'context',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => DocumentStatus::class,
            'to_status' => DocumentStatus::class,
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function statusable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

### 2.2 Migration

```php
<?php
// File: database/migrations/xxxx_create_status_histories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_histories', function (Blueprint $table) {
            $table->id();
            $table->string('statusable_type');
            $table->unsignedBigInteger('statusable_id');
            $table->string('from_status');
            $table->string('to_status');
            $table->foreignId('user_id')->nullable()->constrained();
            $table->json('context')->nullable();
            $table->timestamp('created_at');

            $table->index(['statusable_type', 'statusable_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_histories');
    }
};
```

### 2.3 Trait for Models

```php
<?php
// File: app/Traits/HasStatusHistory.php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Core\StatusHistory;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasStatusHistory
{
    public function statusHistories(): MorphMany
    {
        return $this->morphMany(StatusHistory::class, 'statusable')
            ->orderBy('created_at', 'desc');
    }

    public function recordStatusChange(string $from, string $to, ?int $userId = null, array $context = []): StatusHistory
    {
        return $this->statusHistories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $userId ?? auth()->id(),
            'context' => $context,
            'created_at' => now(),
        ]);
    }

    public function getStatusTimeline(): array
    {
        return $this->statusHistories()
            ->with('user:id,name')
            ->get()
            ->map(fn ($h) => [
                'from' => $h->from_status->label(),
                'to' => $h->to_status->label(),
                'user' => $h->user?->name,
                'at' => $h->created_at->format('d M Y H:i'),
            ])
            ->toArray();
    }
}
```

---

## Part 3: State Machine Example with Guards

### 3.1 Invoice State Machine with Guards

```php
<?php
// File: app/Domain/Sales/Invoices/InvoiceStateMachine.php (enhanced)

declare(strict_types=1);

namespace App\Domain\Sales\Invoices;

use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Core\AbstractStateMachine;
use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Domain\Sales\Invoices\Events\InvoiceStatusChanged;
use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;

class InvoiceStateMachine extends AbstractStateMachine
{
    private Invoice $invoice;

    public function __construct(Invoice $invoice, ?EventDispatcherInterface $eventDispatcher = null)
    {
        $this->invoice = $invoice;
        parent::__construct($invoice->status, $eventDispatcher);
    }

    public static function fromInvoice(Invoice $invoice, ?EventDispatcherInterface $eventDispatcher = null): self
    {
        return new self($invoice, $eventDispatcher);
    }

    protected function registerGuards(): void
    {
        // Guard: Can only post if has items
        $this->addGuard(
            DocumentStatus::Draft->value,
            DocumentStatus::Sent->value,
            fn () => $this->invoice->items()->exists()
        );

        // Guard: Can only mark paid if paid_amount >= total_amount
        $this->addGuard(
            DocumentStatus::Sent->value,
            DocumentStatus::Paid->value,
            fn () => $this->invoice->paid_amount >= $this->invoice->total_amount
        );

        $this->addGuard(
            DocumentStatus::Partial->value,
            DocumentStatus::Paid->value,
            fn () => $this->invoice->paid_amount >= $this->invoice->total_amount
        );

        // Guard: Can only mark overdue if past due date
        $this->addGuard(
            DocumentStatus::Sent->value,
            DocumentStatus::Overdue->value,
            fn () => $this->invoice->due_date->isPast()
        );
    }

    protected function registerActions(): void
    {
        // Action: Fire InvoiceSent event when posted
        $this->addAction(
            DocumentStatus::Draft->value,
            DocumentStatus::Sent->value,
            fn () => $this->eventDispatcher->dispatch(
                InvoiceSent::fromInvoice($this->invoice, $this->getContextUserId())
            )
        );
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
            DocumentStatus::Overdue->value => [
                DocumentStatus::Partial->value,
                DocumentStatus::Paid->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Paid->value => [
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Cancelled->value => [],
        ];
    }

    protected function getContextData(): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'total_amount' => $this->invoice->total_amount,
            'paid_amount' => $this->invoice->paid_amount,
        ];
    }

    protected function updateDocumentStatus(DocumentStatus $status): void
    {
        $this->invoice->status = $status;
        $this->invoice->save();
    }

    protected function recordHistory(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->invoice->recordStatusChange(
            $from->value,
            $to->value,
            $this->getContextUserId(),
            $this->transitionContext
        );
    }

    protected function getDocumentType(): string
    {
        return 'Faktur';
    }

    protected function getDocumentId(): int
    {
        return $this->invoice->id;
    }

    protected function getStatusChangedEvent(): string
    {
        return InvoiceStatusChanged::class;
    }

    // Business rule helpers
    public function canPost(): bool
    {
        return $this->canTransitionTo(DocumentStatus::Sent);
    }

    public function canCancel(): bool
    {
        return $this->canTransitionTo(DocumentStatus::Cancelled);
    }

    public function canEdit(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft;
    }
}
```

---

## Part 4: API Resource with Workflow

```php
<?php
// File: app/Http/Resources/Api/V1/InvoiceResource.php (with workflow)

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'invoice_date' => $this->invoice_date->format('Y-m-d'),
            'due_date' => $this->due_date->format('Y-m-d'),
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'outstanding' => $this->getOutstandingAmount(),

            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],

            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),

            // Workflow information
            'workflow' => $this->when($request->has('include_workflow'), function () {
                return $this->stateMachine()->getWorkflowMetadata();
            }),

            // Status history
            'status_history' => $this->when($request->has('include_history'), function () {
                return $this->getStatusTimeline();
            }),

            // Actions available
            'actions' => [
                'can_edit' => $this->stateMachine()->canEdit(),
                'can_post' => $this->stateMachine()->canPost(),
                'can_cancel' => $this->stateMachine()->canCancel(),
            ],

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
```

---

## Verification Checklist

- [ ] AbstractStateMachine enhanced with guards and actions
- [ ] StatusHistory model and migration created
- [ ] HasStatusHistory trait added to models
- [ ] State machines updated to record history
- [ ] API resources include workflow metadata
- [ ] All tests pass

---

## Next Phase

Proceed to [Phase 7: Testing Infrastructure](./08-phase-7-testing.md).
