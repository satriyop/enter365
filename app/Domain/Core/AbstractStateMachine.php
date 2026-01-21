<?php

declare(strict_types=1);

namespace App\Domain\Core;

use App\Contracts\Events\EventDispatcherInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use Illuminate\Support\Str;

/**
 * Abstract base class for document state machines.
 *
 * Provides a consistent workflow management pattern with:
 * - Transition validation via guards (pre-conditions)
 * - Transition actions (post-conditions)
 * - Lifecycle hooks (before/after transitions)
 * - History tracking
 * - Workflow metadata for UI visualization
 */
abstract class AbstractStateMachine
{
    protected DocumentStatus $currentStatus;

    protected array $context = [];

    /** @var array<string, mixed> Context passed to current transition */
    protected array $transitionContext = [];

    protected EventDispatcherInterface $eventDispatcher;

    /** @var array<string, array<callable>> Guards by transition key */
    protected array $guards = [];

    /** @var array<string, array<callable>> Actions by transition key */
    protected array $actions = [];

    public function __construct(
        DocumentStatus $initialStatus,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->currentStatus = $initialStatus;
        $this->eventDispatcher = $eventDispatcher ?? app(EventDispatcherInterface::class);
        $this->registerGuards();
        $this->registerActions();
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
     * Persist status change to database.
     */
    abstract protected function updateDocumentStatus(DocumentStatus $status): void;

    /**
     * Get document type name for error messages.
     */
    abstract protected function getDocumentType(): string;

    /**
     * Get document ID for events.
     */
    abstract protected function getDocumentId(): int;

    /**
     * Get event class for status changes.
     */
    abstract protected function getStatusChangedEvent(): string;

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
     * @param  string  $from  Source status value
     * @param  string  $to  Target status value
     * @param  callable  $guard  Function returning bool or array with 'passes' and 'message'
     */
    protected function addGuard(string $from, string $to, callable $guard): void
    {
        $key = "{$from}->{$to}";
        $this->guards[$key][] = $guard;
    }

    /**
     * Add action for specific transition.
     *
     * @param  string  $from  Source status value
     * @param  string  $to  Target status value
     * @param  callable  $action  Function to execute after transition
     */
    protected function addAction(string $from, string $to, callable $action): void
    {
        $key = "{$from}->{$to}";
        $this->actions[$key][] = $action;
    }

    /**
     * Get current status.
     */
    public function getCurrentStatus(): DocumentStatus
    {
        return $this->currentStatus;
    }

    /**
     * Check if transition to target status is valid.
     * Validates both transition rules and guards.
     */
    public function canTransitionTo(DocumentStatus $target): bool
    {
        // Same status is not a transition - cannot "transition" to current state
        if ($this->currentStatus === $target) {
            return false;
        }

        $validTransitions = $this->getTransitions()[$this->currentStatus->value] ?? [];

        if (! in_array($target->value, $validTransitions, true)) {
            return false;
        }

        // Check guards
        return $this->passesGuards($this->currentStatus->value, $target->value);
    }

    /**
     * Get all valid next statuses (considering guards).
     *
     * @return array<string>
     */
    public function getNextValidStatuses(): array
    {
        $transitions = $this->getTransitions()[$this->currentStatus->value] ?? [];

        return array_values(array_filter(
            $transitions,
            fn (string $target) => $this->passesGuards($this->currentStatus->value, $target)
        ));
    }

    /**
     * Execute transition to new status.
     *
     * @param  array<string, mixed>  $context
     *
     * @throws StateTransitionException
     */
    public function transitionTo(DocumentStatus $target, array $context = []): void
    {
        $from = $this->currentStatus;
        $this->transitionContext = $context;
        $this->context = array_merge($this->getContextData(), $context);

        if (! $this->canTransitionTo($target)) {
            throw StateTransitionException::invalidTransition(
                $this->getDocumentType(),
                $from->label(),
                $target->label()
            );
        }

        // Execute before hooks
        $this->executeBeforeHooks($from, $target);

        // Persist status change
        $this->updateDocumentStatus($target);

        // Record history
        $this->recordHistory($from, $target);

        // Execute actions
        $this->executeActions($from->value, $target->value);

        // Execute after hooks
        $this->executeAfterHooks($from, $target);

        // Dispatch event
        $this->dispatchStatusChangedEvent($from, $target);

        // Update current status
        $this->currentStatus = $target;
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
            $result = $guard();

            // Support both simple boolean and array with message
            if (is_array($result)) {
                if (! ($result['passes'] ?? false)) {
                    return false;
                }
            } elseif (! $result) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get guard failure reasons for a transition.
     *
     * @return array<string>
     */
    public function getGuardFailureReasons(DocumentStatus $target): array
    {
        $key = "{$this->currentStatus->value}->{$target->value}";
        $reasons = [];

        if (! isset($this->guards[$key])) {
            return $reasons;
        }

        foreach ($this->guards[$key] as $guard) {
            $result = $guard();

            if (is_array($result) && ! ($result['passes'] ?? false)) {
                $reasons[] = $result['message'] ?? 'Guard failed';
            } elseif (! $result) {
                $reasons[] = 'Pre-condition not met';
            }
        }

        return $reasons;
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
     * Override in subclasses that use HasStatusHistory trait.
     */
    protected function recordHistory(DocumentStatus $from, DocumentStatus $to): void
    {
        // Override in subclasses to record in status_histories table
    }

    /**
     * Execute before hooks.
     */
    protected function executeBeforeHooks(DocumentStatus $from, DocumentStatus $to): void
    {
        // General before hook
        $this->beforeTransition($from, $to);

        // Specific before hook (beforeSent, beforeApproved, etc.)
        $specificMethod = 'before'.$this->getHookName($to);
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
        $specificMethod = 'after'.$this->getHookName($to);
        if (method_exists($this, $specificMethod)) {
            $this->$specificMethod($from, $to);
        }

        // General after hook
        $this->afterTransition($from, $to);
    }

    /**
     * Dispatch status changed event.
     */
    protected function dispatchStatusChangedEvent(DocumentStatus $from, DocumentStatus $to): void
    {
        $eventClass = $this->getStatusChangedEvent();

        $this->eventDispatcher->dispatch(new $eventClass(
            $this->getDocumentId(),
            $from,
            $to,
            $this->getContextUserId()
        ));
    }

    /**
     * Get user ID from transition context.
     */
    protected function getContextUserId(): ?int
    {
        return $this->transitionContext['user_id'] ?? $this->context['user_id'] ?? null;
    }

    /**
     * Get hook name for status.
     */
    protected function getHookName(DocumentStatus $status): string
    {
        return Str::studly($status->value);
    }

    /**
     * Get workflow metadata for UI visualization.
     *
     * @return array<string, mixed>
     */
    public function getWorkflowMetadata(): array
    {
        return [
            'current_status' => [
                'value' => $this->currentStatus->value,
                'label' => $this->currentStatus->label(),
                'color' => $this->currentStatus->color(),
                'is_terminal' => $this->currentStatus->isTerminal(),
            ],
            'next_statuses' => array_map(
                fn (string $value) => [
                    'value' => $value,
                    'label' => DocumentStatus::from($value)->label(),
                    'color' => DocumentStatus::from($value)->color(),
                ],
                $this->getNextValidStatuses()
            ),
            'all_statuses' => array_map(
                fn (DocumentStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'color' => $status->color(),
                ],
                $this->getAllValidStatuses()
            ),
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

    // Lifecycle hooks - override in subclasses as needed

    protected function beforeTransition(DocumentStatus $from, DocumentStatus $to): void {}

    protected function afterTransition(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeSend(DocumentStatus $from, DocumentStatus $to): void {}

    protected function afterSend(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforePartial(DocumentStatus $from, DocumentStatus $to): void {}

    protected function afterPartial(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforePaid(DocumentStatus $from, DocumentStatus $to): void {}

    protected function afterPaid(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeOverdue(DocumentStatus $from, DocumentStatus $to): void {}

    protected function afterOverdue(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeVoid(DocumentStatus $from, DocumentStatus $to): void {}

    protected function afterVoid(DocumentStatus $from, DocumentStatus $to): void {}
}
