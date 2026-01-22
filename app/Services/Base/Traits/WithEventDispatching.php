<?php

declare(strict_types=1);

namespace App\Services\Base\Traits;

/**
 * Event dispatching for services.
 *
 * Provides dispatch() method with automatic logging.
 */
trait WithEventDispatching
{
    /**
     * Dispatch domain event.
     */
    protected function dispatch(object $event): void
    {
        $this->eventDispatcher->dispatch($event);
        $this->logger->logDomainEvent($event);
    }
}
