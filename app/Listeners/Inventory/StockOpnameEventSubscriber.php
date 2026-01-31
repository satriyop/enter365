<?php

declare(strict_types=1);

namespace App\Listeners\Inventory;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Inventory\StockOpname\Events\StockOpnameStatusChanged;
use Illuminate\Events\Dispatcher;

/**
 * Handles all stock opname-related events.
 */
class StockOpnameEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function handleStatusChanged(StockOpnameStatusChanged $event): void
    {
        $this->logger->logOperation('stock_opname.status_changed', [
            'stock_opname_id' => $event->stockOpnameId,
            'opname_number' => $event->opnameNumber,
            'from' => $event->fromStatus,
            'to' => $event->toStatus,
            'user_id' => $event->userId,
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            StockOpnameStatusChanged::class => 'handleStatusChanged',
        ];
    }
}
