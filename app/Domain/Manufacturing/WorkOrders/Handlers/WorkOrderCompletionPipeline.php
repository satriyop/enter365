<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders\Handlers;

use App\Models\Manufacturing\WorkOrder;

/**
 * Optional ordered list of completion handlers (internal extensibility seam).
 *
 * Live production completion is orchestrated by WorkOrderCompletion (used from
 * WorkOrderService::complete). This pipeline remains for composing optional
 * handlers and for unit tests of priority/skip behaviour. Do not call it as a
 * second complete path — MaterialConsumptionHandler delegates to the same
 * WorkOrderMaterialService as WorkOrderCompletion.
 *
 * Typical handler sequence when composed:
 * 1. MaterialConsumptionHandler (priority: 10)
 * 2. FinishedGoodsHandler (priority: 20)
 * 3. CostCalculationHandler (priority: 30)
 *
 * Note: full complete() also transitions status between costs and FG; the
 * pipeline alone does not replace WorkOrderCompletion::run().
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
