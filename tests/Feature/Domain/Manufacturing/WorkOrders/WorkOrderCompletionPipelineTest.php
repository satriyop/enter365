<?php

use App\Domain\Manufacturing\WorkOrders\Handlers\CompletionHandlerInterface;
use App\Domain\Manufacturing\WorkOrders\Handlers\WorkOrderCompletionPipeline;
use App\Models\Manufacturing\WorkOrder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('WorkOrderCompletionPipeline', function () {

    it('executes handlers in priority order', function () {
        $order = [];

        // Create mock handlers with different priorities
        $handler1 = createMockHandler(30, function () use (&$order) {
            $order[] = 'handler-30';
        });

        $handler2 = createMockHandler(10, function () use (&$order) {
            $order[] = 'handler-10';
        });

        $handler3 = createMockHandler(20, function () use (&$order) {
            $order[] = 'handler-20';
        });

        $pipeline = new WorkOrderCompletionPipeline;
        $pipeline->addHandler($handler1);
        $pipeline->addHandler($handler2);
        $pipeline->addHandler($handler3);

        $workOrder = WorkOrder::factory()->create();
        $pipeline->process($workOrder);

        expect($order)->toBe(['handler-10', 'handler-20', 'handler-30']);
    });

    it('skips handlers that should not handle', function () {
        $executed = [];

        $handler1 = createMockHandler(10, function () use (&$executed) {
            $executed[] = 'handler-1';
        }, shouldHandle: true);

        $handler2 = createMockHandler(20, function () use (&$executed) {
            $executed[] = 'handler-2';
        }, shouldHandle: false);

        $pipeline = new WorkOrderCompletionPipeline;
        $pipeline->addHandler($handler1);
        $pipeline->addHandler($handler2);

        $workOrder = WorkOrder::factory()->create();
        $pipeline->process($workOrder);

        expect($executed)->toBe(['handler-1']);
    });

    it('counts registered handlers', function () {
        $pipeline = new WorkOrderCompletionPipeline;

        expect($pipeline->count())->toBe(0);

        $pipeline->addHandler(createMockHandler(10));
        expect($pipeline->count())->toBe(1);

        $pipeline->addHandler(createMockHandler(20));
        expect($pipeline->count())->toBe(2);
    });

    it('returns all handlers', function () {
        $handler1 = createMockHandler(10);
        $handler2 = createMockHandler(20);

        $pipeline = new WorkOrderCompletionPipeline;
        $pipeline->addHandler($handler1);
        $pipeline->addHandler($handler2);

        $handlers = $pipeline->getHandlers();

        expect($handlers)->toHaveCount(2);
        // Should be sorted by priority
        expect($handlers[0]->priority())->toBe(10);
        expect($handlers[1]->priority())->toBe(20);
    });

    it('passes userId to handlers', function () {
        $receivedUserId = null;

        $handler = createMockHandler(10, function ($workOrder, $userId) use (&$receivedUserId) {
            $receivedUserId = $userId;
        });

        $pipeline = new WorkOrderCompletionPipeline;
        $pipeline->addHandler($handler);

        $workOrder = WorkOrder::factory()->create();
        $pipeline->process($workOrder, 42);

        expect($receivedUserId)->toBe(42);
    });

    it('supports fluent addHandler syntax', function () {
        $pipeline = new WorkOrderCompletionPipeline;

        $result = $pipeline
            ->addHandler(createMockHandler(10))
            ->addHandler(createMockHandler(20));

        expect($result)->toBeInstanceOf(WorkOrderCompletionPipeline::class);
        expect($pipeline->count())->toBe(2);
    });
});

/**
 * Helper to create a mock handler for testing
 */
function createMockHandler(int $priority, ?Closure $callback = null, bool $shouldHandle = true): CompletionHandlerInterface
{
    return new class($priority, $callback, $shouldHandle) implements CompletionHandlerInterface
    {
        public function __construct(
            private int $handlerPriority,
            private ?Closure $callback,
            private bool $handlerShouldHandle
        ) {}

        public function handle(WorkOrder $workOrder, ?int $userId = null): void
        {
            if ($this->callback) {
                ($this->callback)($workOrder, $userId);
            }
        }

        public function priority(): int
        {
            return $this->handlerPriority;
        }

        public function shouldHandle(WorkOrder $workOrder): bool
        {
            return $this->handlerShouldHandle;
        }
    };
}
