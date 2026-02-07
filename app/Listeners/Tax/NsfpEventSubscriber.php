<?php

declare(strict_types=1);

namespace App\Listeners\Tax;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Tax\Events\NsfpAllocated;
use App\Domain\Tax\Events\NsfpLowStock;
use Illuminate\Events\Dispatcher;

class NsfpEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function handleAllocated(NsfpAllocated $event): void
    {
        $this->logger->logOperation('nsfp.allocated', $event->toArray());
    }

    public function handleLowStock(NsfpLowStock $event): void
    {
        $this->logger->logOperation('nsfp.low_stock', $event->toArray());
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            NsfpAllocated::class => 'handleAllocated',
            NsfpLowStock::class => 'handleLowStock',
        ];
    }
}
