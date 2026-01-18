<?php

declare(strict_types=1);

namespace App\Domain\Sales\SalesReturns\Handlers;

use App\Contracts\Handlers\SalesReturnApprovalHandlerInterface;
use App\Models\Sales\SalesReturn;

/**
 * Pipeline that orchestrates sales return approval handlers.
 *
 * Runs handlers in priority order (lower priority numbers run first).
 * Each handler is responsible for a single concern (SRP).
 */
class SalesReturnApprovalPipeline
{
    /** @var array<SalesReturnApprovalHandlerInterface> */
    private array $handlers = [];

    /**
     * @param  iterable<SalesReturnApprovalHandlerInterface>  $handlers
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
    public function addHandler(SalesReturnApprovalHandlerInterface $handler): self
    {
        $this->handlers[] = $handler;

        // Sort by priority after each addition
        usort($this->handlers, fn ($a, $b) => $a->priority() <=> $b->priority());

        return $this;
    }

    /**
     * Process all handlers for the given sales return.
     *
     * @param  SalesReturn  $salesReturn  The approved sales return
     *
     * @throws \Exception If any handler fails (should trigger transaction rollback)
     */
    public function process(SalesReturn $salesReturn): void
    {
        foreach ($this->handlers as $handler) {
            if ($handler->shouldHandle($salesReturn)) {
                $handler->handle($salesReturn);
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
     * @return array<SalesReturnApprovalHandlerInterface>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }
}
