<?php

declare(strict_types=1);

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderAssigned;
use App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderCancelled;
use App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderCompleted;
use App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderStarted;
use App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderStatusChanged;
use App\Enums\DocumentStatus;
use App\Listeners\Manufacturing\SubcontractorWorkOrderEventSubscriber;
use Carbon\Carbon;
use Illuminate\Events\Dispatcher;

describe('SubcontractorWorkOrderEventSubscriber', function () {
    beforeEach(function () {
        $this->logger = Mockery::mock(ContextualLoggerInterface::class);
        $this->subscriber = new SubcontractorWorkOrderEventSubscriber($this->logger);
    });

    describe('subscribe', function () {
        it('subscribes to all events', function () {
            $dispatcher = Mockery::mock(Dispatcher::class);

            $subscriptions = $this->subscriber->subscribe($dispatcher);

            expect($subscriptions)->toBeArray()
                ->and($subscriptions)->toHaveKey(SubcontractorWorkOrderStatusChanged::class)
                ->and($subscriptions)->toHaveKey(SubcontractorWorkOrderAssigned::class)
                ->and($subscriptions)->toHaveKey(SubcontractorWorkOrderStarted::class)
                ->and($subscriptions)->toHaveKey(SubcontractorWorkOrderCompleted::class)
                ->and($subscriptions)->toHaveKey(SubcontractorWorkOrderCancelled::class);
        });
    });

    describe('handleStatusChanged', function () {
        it('logs status change with correct context', function () {
            $event = new SubcontractorWorkOrderStatusChanged(
                subcontractorWorkOrderId: 1,
                fromStatus: DocumentStatus::Draft,
                toStatus: DocumentStatus::InProgress,
                userId: 10,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('subcontractor_work_order.status_changed', [
                    'subcontractor_work_order_id' => 1,
                    'from' => 'draft',
                    'to' => 'in_progress',
                    'user_id' => 10,
                ]);

            $this->subscriber->handleStatusChanged($event);
        });
    });

    describe('handleAssigned', function () {
        it('logs assigned with correct context', function () {
            $assignedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new SubcontractorWorkOrderAssigned(
                subcontractorWorkOrderId: 1,
                scWoNumber: 'SCWO-2025-0001',
                subcontractorId: 5,
                projectId: 3,
                userId: 10,
                assignedAt: $assignedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('subcontractor_work_order.assigned', [
                    'subcontractor_work_order_id' => 1,
                    'sc_wo_number' => 'SCWO-2025-0001',
                    'subcontractor_id' => 5,
                    'project_id' => 3,
                    'assigned_at' => $assignedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleAssigned($event);
        });
    });

    describe('handleStarted', function () {
        it('logs started with correct context', function () {
            $startedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new SubcontractorWorkOrderStarted(
                subcontractorWorkOrderId: 1,
                scWoNumber: 'SCWO-2025-0001',
                subcontractorId: 5,
                projectId: 3,
                userId: 10,
                startedAt: $startedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('subcontractor_work_order.started', [
                    'subcontractor_work_order_id' => 1,
                    'sc_wo_number' => 'SCWO-2025-0001',
                    'started_at' => $startedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleStarted($event);
        });
    });

    describe('handleCompleted', function () {
        it('logs completed with correct context', function () {
            $completedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new SubcontractorWorkOrderCompleted(
                subcontractorWorkOrderId: 1,
                scWoNumber: 'SCWO-2025-0001',
                subcontractorId: 5,
                projectId: 3,
                userId: 10,
                completedAt: $completedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('subcontractor_work_order.completed', [
                    'subcontractor_work_order_id' => 1,
                    'sc_wo_number' => 'SCWO-2025-0001',
                    'completed_at' => $completedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleCompleted($event);
        });
    });

    describe('handleCancelled', function () {
        it('logs cancelled with correct context', function () {
            $cancelledAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new SubcontractorWorkOrderCancelled(
                subcontractorWorkOrderId: 1,
                scWoNumber: 'SCWO-2025-0001',
                subcontractorId: 5,
                projectId: 3,
                userId: 10,
                cancelledAt: $cancelledAt,
                reason: 'Project requirements changed',
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('subcontractor_work_order.cancelled', [
                    'subcontractor_work_order_id' => 1,
                    'sc_wo_number' => 'SCWO-2025-0001',
                    'reason' => 'Project requirements changed',
                    'cancelled_at' => $cancelledAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleCancelled($event);
        });
    });
});
