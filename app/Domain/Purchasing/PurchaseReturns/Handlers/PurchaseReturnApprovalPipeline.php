<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\PurchaseReturns\Handlers;

use App\Contracts\Handlers\PurchaseReturnApprovalHandlerInterface;
use App\Models\Purchasing\PurchaseReturn;

/**
 * Pipeline that orchestrates purchase return approval handlers.
 *
 * Runs handlers in priority order (lower priority numbers run first).
 * Each handler is responsible for a single concern (SRP).
 */
class PurchaseReturnApprovalPipeline
{
    /** @var array<PurchaseReturnApprovalHandlerInterface> */
    private array $handlers = [];

    /**
     * @param  iterable<PurchaseReturnApprovalHandlerInterface>  $handlers
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
    public function addHandler(PurchaseReturnApprovalHandlerInterface $handler): self
    {
        $this->handlers[] = $handler;

        // Sort by priority after each addition
        usort($this->handlers, fn ($a, $b) => $a->priority() <=> $b->priority());

        return $this;
    }

    /**
     * Process all handlers for the given purchase return.
     *
     * @param  PurchaseReturn  $purchaseReturn  The approved purchase return
     *
     * @throws \Exception If any handler fails (should trigger transaction rollback)
     */
    public function process(PurchaseReturn $purchaseReturn): void
    {
        foreach ($this->handlers as $handler) {
            if ($handler->shouldHandle($purchaseReturn)) {
                $handler->handle($purchaseReturn);
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
     * @return array<PurchaseReturnApprovalHandlerInterface>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }
}
