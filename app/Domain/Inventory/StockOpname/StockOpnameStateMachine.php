<?php

declare(strict_types=1);

namespace App\Domain\Inventory\StockOpname;

use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Inventory\StockOpname\Events\StockOpnameStatusChanged;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Inventory\StockOpname;

/**
 * State machine for Stock Opname workflow.
 *
 * Workflow: Draft → Counting → Reviewed → Approved → Completed
 *           ↓         ↓           ↓
 *       Cancelled  Cancelled   Cancelled
 *
 * Rejected from Reviewed returns to Counting.
 */
class StockOpnameStateMachine
{
    private DocumentStatus $currentStatus;

    private array $context = [];

    public function __construct(
        private StockOpname $stockOpname,
        private ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->currentStatus = $stockOpname->status;
        $this->eventDispatcher = $eventDispatcher ?? app(EventDispatcherInterface::class);
    }

    public static function fromStockOpname(
        StockOpname $stockOpname,
        ?EventDispatcherInterface $eventDispatcher = null
    ): self {
        return new self($stockOpname, $eventDispatcher);
    }

    /**
     * Get valid state transitions.
     *
     * @return array<string, array<string>>
     */
    protected function getTransitions(): array
    {
        return [
            DocumentStatus::Draft->value => [
                DocumentStatus::Counting->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Counting->value => [
                DocumentStatus::Reviewed->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Reviewed->value => [
                DocumentStatus::Approved->value,
                DocumentStatus::Counting->value, // Reject goes back to counting
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Approved->value => [
                DocumentStatus::Completed->value,
            ],
            DocumentStatus::Completed->value => [], // Terminal
            DocumentStatus::Cancelled->value => [], // Terminal
        ];
    }

    public function getCurrentStatus(): DocumentStatus
    {
        return $this->currentStatus;
    }

    public function canTransitionTo(DocumentStatus|string $target): bool
    {
        $targetEnum = $target instanceof DocumentStatus ? $target : DocumentStatus::from($target);

        // Same status is not a transition
        if ($this->currentStatus === $targetEnum) {
            return false;
        }

        $validTransitions = $this->getTransitions()[$this->currentStatus->value] ?? [];

        if (! in_array($targetEnum->value, $validTransitions, true)) {
            return false;
        }

        // Check business rules
        try {
            $this->validateTransition($this->currentStatus, $targetEnum);

            return true;
        } catch (StateTransitionException) {
            return false;
        }
    }

    /**
     * @return array<string>
     */
    public function getNextValidStatuses(): array
    {
        $statuses = $this->getTransitions()[$this->currentStatus->value] ?? [];

        return array_values(array_filter($statuses, function ($status) {
            return $this->canTransitionTo(DocumentStatus::from($status));
        }));
    }

    /**
     * Transition to a new status.
     *
     * @param  array<string, mixed>  $context
     */
    public function transitionTo(DocumentStatus|string $target, array $context = []): void
    {
        $from = $this->currentStatus;

        // Ensure target is a DocumentStatus enum
        $targetEnum = $target instanceof DocumentStatus ? $target : DocumentStatus::from($target);

        if (! $this->canTransitionTo($targetEnum)) {
            throw StateTransitionException::invalidTransition(
                'Stock Opname',
                $from->label(),
                $targetEnum->label()
            );
        }

        $this->context = array_merge($this->getContextData(), $context);

        $this->validateTransition($from, $targetEnum);

        // Update status - Eloquent handles the Enum casting
        $this->stockOpname->status = $targetEnum;
        $this->updateTimestamps($targetEnum);
        $this->stockOpname->save();

        $this->currentStatus = $targetEnum;

        // Dispatch event - explicitly pass the value strings for stability
        $this->eventDispatcher->dispatch(
            new StockOpnameStatusChanged(
                stockOpnameId: $this->stockOpname->id,
                opnameNumber: $this->stockOpname->opname_number,
                fromStatus: $from->value,
                toStatus: $targetEnum->value,
                userId: $this->context['user_id'] ?? null
            )
        );
    }

    // Convenience methods for common transitions

    public function startCounting(?int $userId = null): void
    {
        if (! $this->stockOpname->items()->exists()) {
            throw StateTransitionException::actionNotAvailable(
                'start counting',
                $this->currentStatus->value,
                'Stock opname harus memiliki minimal satu item.'
            );
        }

        $this->transitionTo(DocumentStatus::Counting, ['user_id' => $userId]);
    }

    public function submitForReview(?int $userId = null): void
    {
        $uncounted = $this->stockOpname->items()->whereNull('counted_quantity')->count();
        if ($uncounted > 0) {
            throw StateTransitionException::actionNotAvailable(
                'submit for review',
                $this->currentStatus->value,
                "Masih ada {$uncounted} item yang belum dihitung."
            );
        }

        $this->transitionTo(DocumentStatus::Reviewed, ['user_id' => $userId]);
    }

    public function approve(?int $userId = null): void
    {
        $this->transitionTo(DocumentStatus::Approved, ['user_id' => $userId]);
    }

    public function reject(?int $userId = null, ?string $reason = null): void
    {
        $this->transitionTo(DocumentStatus::Counting, [
            'user_id' => $userId,
            'rejection_reason' => $reason,
        ]);
    }

    public function complete(?int $userId = null): void
    {
        $this->transitionTo(DocumentStatus::Completed, ['user_id' => $userId]);
    }

    public function cancel(?int $userId = null, ?string $reason = null): void
    {
        $this->transitionTo(DocumentStatus::Cancelled, [
            'user_id' => $userId,
            'cancellation_reason' => $reason,
        ]);
    }

    // Business rule checks

    public function canStartCounting(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft
            && $this->stockOpname->items()->exists();
    }

    public function canSubmitForReview(): bool
    {
        return $this->currentStatus === DocumentStatus::Counting
            && $this->stockOpname->items()->whereNull('counted_quantity')->doesntExist();
    }

    public function canApprove(): bool
    {
        return $this->currentStatus === DocumentStatus::Reviewed;
    }

    public function canReject(): bool
    {
        return $this->currentStatus === DocumentStatus::Reviewed;
    }

    public function canComplete(): bool
    {
        return $this->currentStatus === DocumentStatus::Approved;
    }

    public function canCancel(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Draft,
            DocumentStatus::Counting,
            DocumentStatus::Reviewed,
        ], true);
    }

    public function canEdit(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Draft,
            DocumentStatus::Counting,
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function getContextData(): array
    {
        return [
            'stock_opname_id' => $this->stockOpname->id,
            'opname_number' => $this->stockOpname->opname_number,
            'warehouse_id' => $this->stockOpname->warehouse_id,
        ];
    }

    private function validateTransition(DocumentStatus $from, DocumentStatus $to): void
    {
        if ($to === DocumentStatus::Counting && $from === DocumentStatus::Draft) {
            if (! $this->stockOpname->items()->exists()) {
                throw StateTransitionException::actionNotAvailable(
                    'start counting',
                    $from->value,
                    'Stock opname harus memiliki minimal satu item.'
                );
            }
        }

        if ($to === DocumentStatus::Reviewed) {
            if (! $this->stockOpname->items()->exists()) {
                throw StateTransitionException::actionNotAvailable(
                    'submit for review',
                    $from->value,
                    'Stock opname harus memiliki minimal satu item.'
                );
            }

            $uncounted = $this->stockOpname->items()->whereNull('counted_quantity')->count();
            if ($uncounted > 0) {
                throw StateTransitionException::actionNotAvailable(
                    'submit for review',
                    $from->value,
                    "Masih ada {$uncounted} item yang belum dihitung."
                );
            }
        }
    }

    private function updateTimestamps(DocumentStatus $status): void
    {
        $userId = $this->context['user_id'] ?? auth()->id();

        match ($status) {
            DocumentStatus::Counting => $this->stockOpname->fill([
                'counting_started_at' => now(),
                'counted_by' => $userId,
            ]),
            DocumentStatus::Reviewed => $this->stockOpname->fill([
                'reviewed_at' => now(),
                'reviewed_by' => $userId,
            ]),
            DocumentStatus::Approved => $this->stockOpname->fill([
                'approved_at' => now(),
                'approved_by' => $userId,
            ]),
            DocumentStatus::Completed => $this->stockOpname->fill([
                'completed_at' => now(),
            ]),
            DocumentStatus::Cancelled => $this->stockOpname->fill([
                'cancelled_at' => now(),
            ]),
            default => null,
        };
    }

    private function getStatusLabel(DocumentStatus|string $status): string
    {
        if ($status instanceof DocumentStatus) {
            return $status->label();
        }

        return $status;
    }
}
