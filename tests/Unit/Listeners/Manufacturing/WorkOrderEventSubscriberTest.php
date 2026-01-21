<?php

declare(strict_types=1);

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderCancelled;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderCompleted;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderConfirmed;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderStarted;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderStatusChanged;
use App\Enums\DocumentStatus;
use App\Listeners\Manufacturing\WorkOrderEventSubscriber;
use Carbon\Carbon;
use Illuminate\Events\Dispatcher;

describe('WorkOrderEventSubscriber', function () {
    beforeEach(function () {
        $this->logger = Mockery::mock(ContextualLoggerInterface::class);
        $this->subscriber = new WorkOrderEventSubscriber($this->logger);
    });

    describe('subscribe', function () {
        it('subscribes to all work order events', function () {
            $dispatcher = Mockery::mock(Dispatcher::class);

            $subscriptions = $this->subscriber->subscribe($dispatcher);

            expect($subscriptions)->toBeArray()
                ->and($subscriptions)->toHaveKey(WorkOrderStatusChanged::class)
                ->and($subscriptions)->toHaveKey(WorkOrderConfirmed::class)
                ->and($subscriptions)->toHaveKey(WorkOrderStarted::class)
                ->and($subscriptions)->toHaveKey(WorkOrderCompleted::class)
                ->and($subscriptions)->toHaveKey(WorkOrderCancelled::class);
        });
    });

    describe('handleStatusChanged', function () {
        it('logs status change with correct context', function () {
            $event = new WorkOrderStatusChanged(
                workOrderId: 1,
                fromStatus: DocumentStatus::Draft,
                toStatus: DocumentStatus::Confirmed,
                userId: 10,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('work_order.status_changed', [
                    'work_order_id' => 1,
                    'from' => 'draft',
                    'to' => 'confirmed',
                    'user_id' => 10,
                ]);

            $this->subscriber->handleStatusChanged($event);
        });
    });

    describe('handleConfirmed', function () {
        it('logs confirmation with correct context', function () {
            $confirmedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new WorkOrderConfirmed(
                workOrderId: 1,
                workOrderNumber: 'WO-2025-0001',
                projectId: 5,
                userId: 10,
                confirmedAt: $confirmedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('work_order.confirmed', [
                    'work_order_id' => 1,
                    'wo_number' => 'WO-2025-0001',
                    'project_id' => 5,
                    'confirmed_at' => $confirmedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleConfirmed($event);
        });
    });

    describe('handleStarted', function () {
        it('logs start with correct context', function () {
            $startedAt = Carbon::parse('2025-01-16 08:00:00');

            $event = new WorkOrderStarted(
                workOrderId: 1,
                workOrderNumber: 'WO-2025-0001',
                projectId: 5,
                userId: 10,
                startedAt: $startedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('work_order.started', [
                    'work_order_id' => 1,
                    'wo_number' => 'WO-2025-0001',
                    'started_at' => $startedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleStarted($event);
        });
    });

    describe('handleCompleted', function () {
        it('logs completion with correct context', function () {
            $completedAt = Carbon::parse('2025-01-20 16:00:00');

            $event = new WorkOrderCompleted(
                workOrderId: 1,
                workOrderNumber: 'WO-2025-0001',
                projectId: 5,
                userId: 10,
                completedAt: $completedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('work_order.completed', [
                    'work_order_id' => 1,
                    'wo_number' => 'WO-2025-0001',
                    'completed_at' => $completedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleCompleted($event);
        });
    });

    describe('handleCancelled', function () {
        it('logs cancellation with correct context', function () {
            $cancelledAt = Carbon::parse('2025-01-21 11:00:00');

            $event = new WorkOrderCancelled(
                workOrderId: 1,
                workOrderNumber: 'WO-2025-0001',
                projectId: 5,
                userId: 10,
                cancelledAt: $cancelledAt,
                reason: 'Material shortage',
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('work_order.cancelled', [
                    'work_order_id' => 1,
                    'wo_number' => 'WO-2025-0001',
                    'reason' => 'Material shortage',
                    'user_id' => 10,
                ]);

            $this->subscriber->handleCancelled($event);
        });
    });
});
