<?php

declare(strict_types=1);

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionApproved;
use App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionCancelled;
use App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionIssued;
use App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionStatusChanged;
use App\Enums\DocumentStatus;
use App\Listeners\Manufacturing\MaterialRequisitionEventSubscriber;
use Carbon\Carbon;
use Illuminate\Events\Dispatcher;

describe('MaterialRequisitionEventSubscriber', function () {
    beforeEach(function () {
        $this->logger = Mockery::mock(ContextualLoggerInterface::class);
        $this->subscriber = new MaterialRequisitionEventSubscriber($this->logger);
    });

    describe('subscribe', function () {
        it('subscribes to all events', function () {
            $dispatcher = Mockery::mock(Dispatcher::class);

            $subscriptions = $this->subscriber->subscribe($dispatcher);

            expect($subscriptions)->toBeArray()
                ->and($subscriptions)->toHaveKey(MaterialRequisitionStatusChanged::class)
                ->and($subscriptions)->toHaveKey(MaterialRequisitionApproved::class)
                ->and($subscriptions)->toHaveKey(MaterialRequisitionIssued::class)
                ->and($subscriptions)->toHaveKey(MaterialRequisitionCancelled::class);
        });
    });

    describe('handleStatusChanged', function () {
        it('logs status change with correct context', function () {
            $event = new MaterialRequisitionStatusChanged(
                materialRequisitionId: 1,
                fromStatus: DocumentStatus::Draft,
                toStatus: DocumentStatus::InProgress,
                userId: 10,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('material_requisition.status_changed', [
                    'material_requisition_id' => 1,
                    'from' => 'draft',
                    'to' => 'in_progress',
                    'user_id' => 10,
                ]);

            $this->subscriber->handleStatusChanged($event);
        });
    });

    describe('handleApproved', function () {
        it('logs approved with correct context', function () {
            $approvedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new MaterialRequisitionApproved(
                materialRequisitionId: 1,
                requisitionNumber: 'MR-2025-0001',
                workOrderId: 5,
                userId: 10,
                approvedAt: $approvedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('material_requisition.approved', [
                    'material_requisition_id' => 1,
                    'requisition_number' => 'MR-2025-0001',
                    'work_order_id' => 5,
                    'approved_at' => $approvedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleApproved($event);
        });
    });

    describe('handleIssued', function () {
        it('logs issued with correct context', function () {
            $issuedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new MaterialRequisitionIssued(
                materialRequisitionId: 1,
                requisitionNumber: 'MR-2025-0001',
                workOrderId: 5,
                userId: 10,
                issuedAt: $issuedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('material_requisition.issued', [
                    'material_requisition_id' => 1,
                    'requisition_number' => 'MR-2025-0001',
                    'work_order_id' => 5,
                    'issued_at' => $issuedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleIssued($event);
        });
    });

    describe('handleCancelled', function () {
        it('logs cancelled with correct context', function () {
            $cancelledAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new MaterialRequisitionCancelled(
                materialRequisitionId: 1,
                requisitionNumber: 'MR-2025-0001',
                workOrderId: 5,
                userId: 10,
                cancelledAt: $cancelledAt,
                reason: 'Work order cancelled',
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('material_requisition.cancelled', [
                    'material_requisition_id' => 1,
                    'requisition_number' => 'MR-2025-0001',
                    'work_order_id' => 5,
                    'reason' => 'Work order cancelled',
                    'cancelled_at' => $cancelledAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleCancelled($event);
        });
    });
});
