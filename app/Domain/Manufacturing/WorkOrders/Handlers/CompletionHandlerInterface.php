<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders\Handlers;

use App\Models\Manufacturing\WorkOrder;

/**
 * Interface for work order completion handlers.
 *
 * Handlers are called in priority order when a work order is completed.
 * Each handler should be responsible for a single concern.
 */
interface CompletionHandlerInterface
{
    /**
     * Handle work order completion.
     */
    public function handle(WorkOrder $workOrder, ?int $userId = null): void;

    /**
     * Get handler priority (lower runs first).
     */
    public function priority(): int;

    /**
     * Determine if this handler should run for the given work order.
     */
    public function shouldHandle(WorkOrder $workOrder): bool;
}
