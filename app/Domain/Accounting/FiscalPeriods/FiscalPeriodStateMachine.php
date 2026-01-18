<?php

declare(strict_types=1);

namespace App\Domain\Accounting\FiscalPeriods;

use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus;
use App\Domain\Accounting\FiscalPeriods\Events\FiscalPeriodClosed;
use App\Domain\Accounting\FiscalPeriods\Events\FiscalPeriodClosing;
use App\Domain\Accounting\FiscalPeriods\Events\FiscalPeriodLocked;
use App\Domain\Accounting\FiscalPeriods\Events\FiscalPeriodReopened;
use App\Domain\Accounting\FiscalPeriods\Events\FiscalPeriodStatusChanged;
use App\Domain\Accounting\FiscalPeriods\Exceptions\FiscalPeriodException;
use App\Models\Accounting\FiscalPeriod;
use Illuminate\Support\Str;

/**
 * State machine for fiscal period lifecycle management.
 *
 * Handles status transitions:
 * - Open → Locked → Closing → Closed
 * - Closed → Open (reopen)
 *
 * Dispatches domain events for each transition and provides
 * lifecycle hooks for custom behavior.
 */
class FiscalPeriodStateMachine
{
    protected FiscalPeriodStatus $currentStatus;

    protected array $context = [];

    /**
     * Valid transitions: from_status => [allowed_target_statuses].
     *
     * @var array<string, array<string>>
     */
    protected array $transitions = [
        'open' => ['locked'],
        'locked' => ['open', 'closing'],
        'closing' => ['locked', 'closed'],
        'closed' => ['open'],
    ];

    public function __construct(
        protected FiscalPeriod $period,
        protected ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->currentStatus = $period->getStatus();
        $this->eventDispatcher = $eventDispatcher ?? app(EventDispatcherInterface::class);
    }

    /**
     * Get the current status.
     */
    public function getCurrentStatus(): FiscalPeriodStatus
    {
        return $this->currentStatus;
    }

    /**
     * Check if a transition to the target status is valid.
     */
    public function canTransitionTo(FiscalPeriodStatus $target): bool
    {
        if ($this->currentStatus === $target) {
            return true;
        }

        $validTargets = $this->transitions[$this->currentStatus->value] ?? [];

        return in_array($target->value, $validTargets, true);
    }

    /**
     * Get list of valid next statuses.
     *
     * @return array<string>
     */
    public function getNextValidStatuses(): array
    {
        return $this->transitions[$this->currentStatus->value] ?? [];
    }

    /**
     * Perform a status transition.
     *
     * @param  array<string, mixed>  $context
     *
     * @throws FiscalPeriodException
     */
    public function transitionTo(FiscalPeriodStatus $target, array $context = []): void
    {
        $from = $this->currentStatus;

        if (! $this->canTransitionTo($target)) {
            throw FiscalPeriodException::invalidTransition($from, $target);
        }

        if ($from === $target) {
            return;
        }

        $this->context = array_merge($this->getContextData(), $context);

        $this->beforeTransition($from, $target);
        $this->callHook('before', $target, $from);

        $this->performTransition($from, $target);

        $this->afterTransition($from, $target);
        $this->callHook('after', $target, $from);

        $this->dispatchStatusChangedEvent($from, $target);
        $this->dispatchSpecificEvent($target);
    }

    /**
     * Lock the period (Open → Locked).
     *
     * @param  array<string, mixed>  $context
     *
     * @throws FiscalPeriodException
     */
    public function lock(array $context = []): void
    {
        $this->transitionTo(FiscalPeriodStatus::Locked, $context);
    }

    /**
     * Unlock the period (Locked → Open or Closing → Locked).
     *
     * @param  array<string, mixed>  $context
     *
     * @throws FiscalPeriodException
     */
    public function unlock(array $context = []): void
    {
        if ($this->currentStatus === FiscalPeriodStatus::Closing) {
            $this->transitionTo(FiscalPeriodStatus::Locked, $context);
        } else {
            $this->transitionTo(FiscalPeriodStatus::Open, $context);
        }
    }

    /**
     * Start the closing process (Locked → Closing).
     *
     * @param  array<string, mixed>  $context
     *
     * @throws FiscalPeriodException
     */
    public function startClosing(array $context = []): void
    {
        $this->transitionTo(FiscalPeriodStatus::Closing, $context);
    }

    /**
     * Mark the period as closed (Closing → Closed).
     *
     * @param  array<string, mixed>  $context
     *
     * @throws FiscalPeriodException
     */
    public function markClosed(array $context = []): void
    {
        $this->transitionTo(FiscalPeriodStatus::Closed, $context);
    }

    /**
     * Reopen a closed period (Closed → Open).
     *
     * @param  array<string, mixed>  $context
     *
     * @throws FiscalPeriodException
     */
    public function reopen(array $context = []): void
    {
        $this->transitionTo(FiscalPeriodStatus::Open, $context);
    }

    /**
     * Perform the actual status update.
     */
    protected function performTransition(FiscalPeriodStatus $from, FiscalPeriodStatus $target): void
    {
        $this->updatePeriodStatus($target);
        $this->currentStatus = $target;
    }

    /**
     * Update the fiscal period status in the database.
     */
    protected function updatePeriodStatus(FiscalPeriodStatus $status): void
    {
        $updateData = ['status' => $status->value];

        // Update legacy boolean fields for backward compatibility
        $updateData['is_locked'] = in_array($status, [
            FiscalPeriodStatus::Locked,
            FiscalPeriodStatus::Closing,
            FiscalPeriodStatus::Closed,
        ], true);

        $updateData['is_closed'] = $status === FiscalPeriodStatus::Closed;

        // Set closed_at and closed_by when closing
        if ($status === FiscalPeriodStatus::Closed) {
            $updateData['closed_at'] = now();
            $updateData['closed_by'] = $this->getContextUserId();
        }

        // Clear closed fields when reopening
        if ($status === FiscalPeriodStatus::Open && $this->currentStatus === FiscalPeriodStatus::Closed) {
            $updateData['closed_at'] = null;
            $updateData['closed_by'] = null;
            $updateData['closing_entry_id'] = null;
            $updateData['retained_earnings_amount'] = null;
            $updateData['closing_notes'] = null;
        }

        $this->period->update($updateData);
    }

    /**
     * Get context data for the transition.
     *
     * @return array<string, mixed>
     */
    protected function getContextData(): array
    {
        return [
            'period_id' => $this->period->id,
            'period_name' => $this->period->name,
            'user_id' => auth()->id(),
        ];
    }

    /**
     * Get user ID from context.
     */
    protected function getContextUserId(): ?int
    {
        return $this->context['user_id'] ?? auth()->id();
    }

    /**
     * Get hook method name for a status.
     */
    protected function getHookName(string $prefix, FiscalPeriodStatus $status): string
    {
        return $prefix.Str::studly($status->value);
    }

    /**
     * Call a lifecycle hook if it exists.
     */
    protected function callHook(string $prefix, FiscalPeriodStatus $target, FiscalPeriodStatus $from): void
    {
        $method = $this->getHookName($prefix, $target);
        if (method_exists($this, $method)) {
            $this->{$method}($from, $target);
        }
    }

    /**
     * Dispatch the generic status changed event.
     */
    protected function dispatchStatusChangedEvent(FiscalPeriodStatus $from, FiscalPeriodStatus $to): void
    {
        $this->eventDispatcher->dispatch(new FiscalPeriodStatusChanged(
            periodId: $this->period->id,
            fromStatus: $from,
            toStatus: $to,
            userId: $this->getContextUserId()
        ));
    }

    /**
     * Dispatch status-specific events.
     */
    protected function dispatchSpecificEvent(FiscalPeriodStatus $status): void
    {
        $event = match ($status) {
            FiscalPeriodStatus::Locked => new FiscalPeriodLocked(
                periodId: $this->period->id,
                userId: $this->getContextUserId()
            ),
            FiscalPeriodStatus::Closing => new FiscalPeriodClosing(
                periodId: $this->period->id,
                userId: $this->getContextUserId()
            ),
            FiscalPeriodStatus::Closed => new FiscalPeriodClosed(
                periodId: $this->period->id,
                netIncome: $this->context['net_income'] ?? 0,
                closingEntryId: $this->context['closing_entry_id'] ?? null,
                userId: $this->getContextUserId()
            ),
            FiscalPeriodStatus::Open => $this->wasReopened()
                ? new FiscalPeriodReopened(
                    periodId: $this->period->id,
                    userId: $this->getContextUserId()
                )
                : null,
        };

        if ($event !== null) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    /**
     * Check if the transition was a reopen operation.
     */
    protected function wasReopened(): bool
    {
        return isset($this->context['previous_status'])
            && $this->context['previous_status'] === FiscalPeriodStatus::Closed->value;
    }

    // Lifecycle hooks - override in subclass or use event listeners

    protected function beforeTransition(FiscalPeriodStatus $from, FiscalPeriodStatus $to): void
    {
        // Store previous status for reopen detection
        $this->context['previous_status'] = $from->value;
    }

    protected function afterTransition(FiscalPeriodStatus $from, FiscalPeriodStatus $to): void {}

    protected function beforeLocked(FiscalPeriodStatus $from, FiscalPeriodStatus $to): void {}

    protected function afterLocked(FiscalPeriodStatus $from, FiscalPeriodStatus $to): void {}

    protected function beforeClosing(FiscalPeriodStatus $from, FiscalPeriodStatus $to): void {}

    protected function afterClosing(FiscalPeriodStatus $from, FiscalPeriodStatus $to): void {}

    protected function beforeClosed(FiscalPeriodStatus $from, FiscalPeriodStatus $to): void {}

    protected function afterClosed(FiscalPeriodStatus $from, FiscalPeriodStatus $to): void {}

    protected function beforeOpen(FiscalPeriodStatus $from, FiscalPeriodStatus $to): void {}

    protected function afterOpen(FiscalPeriodStatus $from, FiscalPeriodStatus $to): void {}
}
