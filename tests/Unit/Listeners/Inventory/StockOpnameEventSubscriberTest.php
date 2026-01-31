<?php

declare(strict_types=1);

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Inventory\StockOpname\Events\StockOpnameStatusChanged;
use App\Listeners\Inventory\StockOpnameEventSubscriber;
use Illuminate\Events\Dispatcher;

describe('StockOpnameEventSubscriber', function () {
    beforeEach(function () {
        $this->logger = Mockery::mock(ContextualLoggerInterface::class);
        $this->subscriber = new StockOpnameEventSubscriber($this->logger);
    });

    describe('subscribe', function () {
        it('subscribes to stock opname status changed event', function () {
            $dispatcher = Mockery::mock(Dispatcher::class);

            $subscriptions = $this->subscriber->subscribe($dispatcher);

            expect($subscriptions)->toBeArray()
                ->and($subscriptions)->toHaveKey(StockOpnameStatusChanged::class);
        });
    });

    describe('handleStatusChanged', function () {
        it('logs status change with correct context', function () {
            $event = new StockOpnameStatusChanged(
                stockOpnameId: 1,
                opnameNumber: 'SO-2025-0001',
                fromStatus: 'draft',
                toStatus: 'in_progress',
                userId: 10,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('stock_opname.status_changed', [
                    'stock_opname_id' => 1,
                    'opname_number' => 'SO-2025-0001',
                    'from' => 'draft',
                    'to' => 'in_progress',
                    'user_id' => 10,
                ]);

            $this->subscriber->handleStatusChanged($event);
        });

        it('handles status change to completed', function () {
            $event = new StockOpnameStatusChanged(
                stockOpnameId: 2,
                opnameNumber: 'SO-2025-0002',
                fromStatus: 'in_progress',
                toStatus: 'completed',
                userId: 15,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('stock_opname.status_changed', [
                    'stock_opname_id' => 2,
                    'opname_number' => 'SO-2025-0002',
                    'from' => 'in_progress',
                    'to' => 'completed',
                    'user_id' => 15,
                ]);

            $this->subscriber->handleStatusChanged($event);
        });
    });
});
