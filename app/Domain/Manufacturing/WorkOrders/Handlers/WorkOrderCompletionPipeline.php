<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders\Handlers;

use App\Models\Manufacturing\WorkOrder;

/**
 * Pipeline that orchestrates work order completion handlers.
 *
 * Runs handlers in priority order (lower priority numbers run first).
 * Each handler is responsible for a single concern (SRP).
 *
 * Typical handler sequence:
 * 1. MaterialConsumptionHandler (priority: 10) - Consume materials
 * 2. FinishedGoodsHandler (priority: 20) - Add finished goods to inventory
 * 3. CostCalculationHandler (priority: 30) - Calculate actual costs
 */
class WorkOrderCompletionPipeline
{
    /** @var array<CompletionHandlerInterface> */
    private array $handlers = [];

    /**
     * @param  iterable<CompletionHandlerInterface>  $handlers
     */
    public function __construct(iterable $handlers = [])
    {
        foreach ($handlers as $handler) {
            $this->addHandler($handler);
        }
    }

    /**
     * Add a handler to the pipeline.
     */
    public function addHandler(CompletionHandlerInterface $handler): self
    {
        $this->handlers[] = $handler;

        // Sort by priority after each addition
        usort($this->handlers, fn ($a, $b) => $a->priority() <=> $b->priority());

        return $this;
    }

    /**
     * Process all handlers for the given work order.
     *
     * @throws \Exception If any handler fails (triggers transaction rollback)
     */
    public function process(WorkOrder $workOrder, ?int $userId = null): void
    {
        foreach ($this->handlers as $handler) {
            if ($handler->shouldHandle($workOrder)) {
                $handler->handle($workOrder, $userId);
            }
        }
    }

    /**
     * Get the count of registered handlers.
     */
    public function count(): int
    {
        return count($this->handlers);
    }

    /**
     * Get all registered handlers (useful for debugging/testing).
     *
     * @return array<CompletionHandlerInterface>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }
}
