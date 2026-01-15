<?php

declare(strict_types=1);

namespace App\Services\Base;

use App\Contracts\Services\WorkflowServiceInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Abstract base class for workflow/state machine services.
 *
 * Provides common state transition operations for documents.
 * Subclasses must define the state machine configuration.
 */
abstract class AbstractWorkflowService implements WorkflowServiceInterface
{
    /**
     * Get the state machine configuration.
     *
     * Format:
     * [
     *     'current_status' => ['allowed_action' => 'next_status', ...],
     *     ...
     * ]
     *
     * @return array<string, array<string, string>>
     */
    abstract protected function getTransitions(): array;

    /**
     * Get the status field name on the model.
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * Get available transitions for current state.
     *
     * @return array<string>
     */
    public function getAvailableTransitions(Model $document): array
    {
        $currentStatus = $document->{$this->getStatusField()};
        $transitions = $this->getTransitions();

        return array_keys($transitions[$currentStatus] ?? []);
    }

    /**
     * Check if a transition is allowed from current state.
     */
    public function canTransition(Model $document, string $action): bool
    {
        $available = $this->getAvailableTransitions($document);

        return in_array($action, $available, true);
    }

    /**
     * Execute a state transition.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public function transition(Model $document, string $action, array $data = []): Model
    {
        if (! $this->canTransition($document, $action)) {
            $currentStatus = $document->{$this->getStatusField()};
            throw new InvalidArgumentException(
                "Aksi '{$action}' tidak diizinkan dari status '{$currentStatus}'."
            );
        }

        return DB::transaction(function () use ($document, $action, $data) {
            $currentStatus = $document->{$this->getStatusField()};
            $transitions = $this->getTransitions();
            $newStatus = $transitions[$currentStatus][$action];

            // Execute before hook
            $this->beforeTransition($document, $action, $data);

            // Update status
            $document->{$this->getStatusField()} = $newStatus;

            // Apply action-specific updates
            $this->applyTransitionData($document, $action, $data);

            $document->save();

            // Execute after hook
            $this->afterTransition($document, $action, $data);

            return $document->fresh();
        });
    }

    /**
     * Hook called before transition.
     *
     * @param  array<string, mixed>  $data
     */
    protected function beforeTransition(Model $document, string $action, array $data): void
    {
        // Override in subclass if needed
    }

    /**
     * Hook called after transition.
     *
     * @param  array<string, mixed>  $data
     */
    protected function afterTransition(Model $document, string $action, array $data): void
    {
        // Override in subclass if needed
    }

    /**
     * Apply action-specific data to document.
     *
     * @param  array<string, mixed>  $data
     */
    protected function applyTransitionData(Model $document, string $action, array $data): void
    {
        // Common patterns
        $timestamp = now();
        $userId = auth()->id();

        // Auto-set common fields based on action name
        $actionFieldMap = [
            'submit' => ['submitted_at' => $timestamp, 'submitted_by' => $userId],
            'approve' => ['approved_at' => $timestamp, 'approved_by' => $userId],
            'reject' => ['rejected_at' => $timestamp, 'rejected_by' => $userId],
            'complete' => ['completed_at' => $timestamp, 'completed_by' => $userId],
            'cancel' => ['cancelled_at' => $timestamp, 'cancelled_by' => $userId],
        ];

        if (isset($actionFieldMap[$action])) {
            foreach ($actionFieldMap[$action] as $field => $value) {
                if ($document->isFillable($field) || in_array($field, $document->getFillable())) {
                    $document->{$field} = $value;
                }
            }
        }

        // Apply additional data from input
        foreach ($data as $key => $value) {
            if ($document->isFillable($key)) {
                $document->{$key} = $value;
            }
        }
    }
}
