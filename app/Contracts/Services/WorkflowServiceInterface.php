<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Interface for workflow/state transition operations.
 *
 * Used by services that manage document state machines:
 * Quotation (draft → submitted → approved), WorkOrder (draft → in_progress → completed), etc.
 */
interface WorkflowServiceInterface
{
    /**
     * Get available transitions for current state.
     *
     * @return array<string> List of available action names
     */
    public function getAvailableTransitions(Model $document): array;

    /**
     * Check if a transition is allowed from current state.
     */
    public function canTransition(Model $document, string $action): bool;

    /**
     * Execute a state transition.
     *
     * @param  array<string, mixed>  $data  Additional data for the transition
     *
     * @throws \InvalidArgumentException If transition is not allowed
     */
    public function transition(Model $document, string $action, array $data = []): Model;
}
