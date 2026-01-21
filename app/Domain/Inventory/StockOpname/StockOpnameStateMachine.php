<?php

declare(strict_types=1);

namespace App\Domain\Inventory\StockOpname;

use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Inventory\StockOpname\Events\StockOpnameStatusChanged;
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
    private string $currentStatus;

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
            StockOpname::STATUS_DRAFT => [
                StockOpname::STATUS_COUNTING,
                StockOpname::STATUS_CANCELLED,
            ],
            StockOpname::STATUS_COUNTING => [
                StockOpname::STATUS_REVIEWED,
                StockOpname::STATUS_CANCELLED,
            ],
            StockOpname::STATUS_REVIEWED => [
                StockOpname::STATUS_APPROVED,
                StockOpname::STATUS_COUNTING, // Reject goes back to counting
                StockOpname::STATUS_CANCELLED,
            ],
            StockOpname::STATUS_APPROVED => [
                StockOpname::STATUS_COMPLETED,
            ],
            StockOpname::STATUS_COMPLETED => [], // Terminal
            StockOpname::STATUS_CANCELLED => [], // Terminal
        ];
    }

    public function getCurrentStatus(): string
    {
        return $this->currentStatus;
    }

    public function canTransitionTo(string $target): bool
    {
        // Same status is not a transition - cannot "transition" to current state
        if ($this->currentStatus === $target) {
            return false;
        }

        $validTransitions = $this->getTransitions()[$this->currentStatus] ?? [];

        return in_array($target, $validTransitions, true);
    }

    /**
     * @return array<string>
     */
    public function getNextValidStatuses(): array
    {
        return $this->getTransitions()[$this->currentStatus] ?? [];
    }

    /**
     * Transition to a new status.
     *
     * @param  array<string, mixed>  $context
     */
    public function transitionTo(string $target, array $context = []): void
    {
        $from = $this->currentStatus;

        if (! $this->canTransitionTo($target)) {
            throw StateTransitionException::invalidTransition(
                'Stock Opname',
                $this->getStatusLabel($from),
                $this->getStatusLabel($target)
            );
        }

        $this->context = array_merge($this->getContextData(), $context);

        $this->validateTransition($from, $target);

        // Update status
        $this->stockOpname->status = $target;
        $this->updateTimestamps($target);
        $this->stockOpname->save();

        $this->currentStatus = $target;

        // Dispatch event
        $this->eventDispatcher->dispatch(
            new StockOpnameStatusChanged(
                stockOpnameId: $this->stockOpname->id,
                opnameNumber: $this->stockOpname->opname_number,
                fromStatus: $from,
                toStatus: $target,
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
                $this->currentStatus,
                'Stock opname harus memiliki minimal satu item.'
            );
        }

        $this->transitionTo(StockOpname::STATUS_COUNTING, ['user_id' => $userId]);
    }

    public function submitForReview(?int $userId = null): void
    {
        $uncounted = $this->stockOpname->items()->whereNull('counted_quantity')->count();
        if ($uncounted > 0) {
            throw StateTransitionException::actionNotAvailable(
                'submit for review',
                $this->currentStatus,
                "Masih ada {$uncounted} item yang belum dihitung."
            );
        }

        $this->transitionTo(StockOpname::STATUS_REVIEWED, ['user_id' => $userId]);
    }

    public function approve(?int $userId = null): void
    {
        $this->transitionTo(StockOpname::STATUS_APPROVED, ['user_id' => $userId]);
    }

    public function reject(?int $userId = null, ?string $reason = null): void
    {
        $this->transitionTo(StockOpname::STATUS_COUNTING, [
            'user_id' => $userId,
            'rejection_reason' => $reason,
        ]);
    }

    public function complete(?int $userId = null): void
    {
        $this->transitionTo(StockOpname::STATUS_COMPLETED, ['user_id' => $userId]);
    }

    public function cancel(?int $userId = null, ?string $reason = null): void
    {
        $this->transitionTo(StockOpname::STATUS_CANCELLED, [
            'user_id' => $userId,
            'cancellation_reason' => $reason,
        ]);
    }

    // Business rule checks

    public function canStartCounting(): bool
    {
        return $this->currentStatus === StockOpname::STATUS_DRAFT
            && $this->stockOpname->items()->exists();
    }

    public function canSubmitForReview(): bool
    {
        return $this->currentStatus === StockOpname::STATUS_COUNTING
            && $this->stockOpname->items()->whereNull('counted_quantity')->doesntExist();
    }

    public function canApprove(): bool
    {
        return $this->currentStatus === StockOpname::STATUS_REVIEWED;
    }

    public function canReject(): bool
    {
        return $this->currentStatus === StockOpname::STATUS_REVIEWED;
    }

    public function canComplete(): bool
    {
        return $this->currentStatus === StockOpname::STATUS_APPROVED;
    }

    public function canCancel(): bool
    {
        return in_array($this->currentStatus, [
            StockOpname::STATUS_DRAFT,
            StockOpname::STATUS_COUNTING,
            StockOpname::STATUS_REVIEWED,
        ]);
    }

    public function canEdit(): bool
    {
        return in_array($this->currentStatus, [
            StockOpname::STATUS_DRAFT,
            StockOpname::STATUS_COUNTING,
        ]);
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

    private function validateTransition(string $from, string $to): void
    {
        if ($to === StockOpname::STATUS_COUNTING && $from === StockOpname::STATUS_DRAFT) {
            if (! $this->stockOpname->items()->exists()) {
                throw StateTransitionException::actionNotAvailable(
                    'start counting',
                    $from,
                    'Stock opname harus memiliki minimal satu item.'
                );
            }
        }

        if ($to === StockOpname::STATUS_REVIEWED) {
            $uncounted = $this->stockOpname->items()->whereNull('counted_quantity')->count();
            if ($uncounted > 0) {
                throw StateTransitionException::actionNotAvailable(
                    'submit for review',
                    $from,
                    "Masih ada {$uncounted} item yang belum dihitung."
                );
            }
        }
    }

    private function updateTimestamps(string $status): void
    {
        $userId = $this->context['user_id'] ?? auth()->id();

        match ($status) {
            StockOpname::STATUS_COUNTING => $this->stockOpname->fill([
                'counting_started_at' => now(),
                'counted_by' => $userId,
            ]),
            StockOpname::STATUS_REVIEWED => $this->stockOpname->fill([
                'reviewed_at' => now(),
                'reviewed_by' => $userId,
            ]),
            StockOpname::STATUS_APPROVED => $this->stockOpname->fill([
                'approved_at' => now(),
                'approved_by' => $userId,
            ]),
            StockOpname::STATUS_COMPLETED => $this->stockOpname->fill([
                'completed_at' => now(),
            ]),
            StockOpname::STATUS_CANCELLED => $this->stockOpname->fill([
                'cancelled_at' => now(),
            ]),
            default => null,
        };
    }

    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            StockOpname::STATUS_DRAFT => 'Draft',
            StockOpname::STATUS_COUNTING => 'Counting',
            StockOpname::STATUS_REVIEWED => 'Reviewed',
            StockOpname::STATUS_APPROVED => 'Approved',
            StockOpname::STATUS_COMPLETED => 'Completed',
            StockOpname::STATUS_CANCELLED => 'Cancelled',
            default => $status,
        };
    }
}
