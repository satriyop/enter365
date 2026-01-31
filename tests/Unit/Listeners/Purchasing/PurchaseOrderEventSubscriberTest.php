<?php

declare(strict_types=1);

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderApproved;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderCancelled;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderPartial;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderReceived;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderRejected;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderStatusChanged;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderSubmitted;
use App\Enums\DocumentStatus;
use App\Listeners\Purchasing\PurchaseOrderEventSubscriber;
use Carbon\Carbon;
use Illuminate\Events\Dispatcher;

describe('PurchaseOrderEventSubscriber', function () {
    beforeEach(function () {
        $this->logger = Mockery::mock(ContextualLoggerInterface::class);
        $this->subscriber = new PurchaseOrderEventSubscriber($this->logger);
    });

    describe('subscribe', function () {
        it('subscribes to all purchase order events', function () {
            $dispatcher = Mockery::mock(Dispatcher::class);

            $subscriptions = $this->subscriber->subscribe($dispatcher);

            expect($subscriptions)->toBeArray()
                ->and($subscriptions)->toHaveKey(PurchaseOrderStatusChanged::class)
                ->and($subscriptions)->toHaveKey(PurchaseOrderSubmitted::class)
                ->and($subscriptions)->toHaveKey(PurchaseOrderApproved::class)
                ->and($subscriptions)->toHaveKey(PurchaseOrderRejected::class)
                ->and($subscriptions)->toHaveKey(PurchaseOrderCancelled::class)
                ->and($subscriptions)->toHaveKey(PurchaseOrderPartial::class)
                ->and($subscriptions)->toHaveKey(PurchaseOrderReceived::class);
        });
    });

    describe('handleStatusChanged', function () {
        it('logs status change with correct context', function () {
            $event = new PurchaseOrderStatusChanged(
                purchaseOrderId: 1,
                fromStatus: DocumentStatus::Draft,
                toStatus: DocumentStatus::Sent,
                userId: 10,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('purchase_order.status_changed', [
                    'purchase_order_id' => 1,
                    'from' => 'draft',
                    'to' => 'sent',
                    'user_id' => 10,
                ]);

            $this->subscriber->handleStatusChanged($event);
        });
    });

    describe('handleSubmitted', function () {
        it('logs submitted with correct context', function () {
            $submittedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new PurchaseOrderSubmitted(
                purchaseOrderId: 1,
                poNumber: 'PO-2025-0001',
                vendorId: 5,
                totalAmount: 3500000,
                currency: 'IDR',
                userId: 10,
                submittedAt: $submittedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('purchase_order.submitted', [
                    'purchase_order_id' => 1,
                    'po_number' => 'PO-2025-0001',
                    'vendor_id' => 5,
                    'total_amount' => 3500000,
                    'submitted_at' => $submittedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleSubmitted($event);
        });
    });

    describe('handleApproved', function () {
        it('logs approved with correct context', function () {
            $approvedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new PurchaseOrderApproved(
                purchaseOrderId: 1,
                poNumber: 'PO-2025-0001',
                vendorId: 5,
                totalAmount: 3500000,
                currency: 'IDR',
                userId: 10,
                approvedAt: $approvedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('purchase_order.approved', [
                    'purchase_order_id' => 1,
                    'po_number' => 'PO-2025-0001',
                    'approved_at' => $approvedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleApproved($event);
        });
    });

    describe('handleRejected', function () {
        it('logs rejected with correct context', function () {
            $rejectedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new PurchaseOrderRejected(
                purchaseOrderId: 1,
                poNumber: 'PO-2025-0001',
                vendorId: 5,
                totalAmount: 3500000,
                currency: 'IDR',
                userId: 10,
                rejectedAt: $rejectedAt,
                reason: 'Budget exceeded',
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('purchase_order.rejected', [
                    'purchase_order_id' => 1,
                    'po_number' => 'PO-2025-0001',
                    'reason' => 'Budget exceeded',
                    'rejected_at' => $rejectedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleRejected($event);
        });
    });

    describe('handleCancelled', function () {
        it('logs cancelled with correct context', function () {
            $cancelledAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new PurchaseOrderCancelled(
                purchaseOrderId: 1,
                poNumber: 'PO-2025-0001',
                vendorId: 5,
                totalAmount: 3500000,
                currency: 'IDR',
                userId: 10,
                cancelledAt: $cancelledAt,
                reason: 'Project cancelled',
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('purchase_order.cancelled', [
                    'purchase_order_id' => 1,
                    'po_number' => 'PO-2025-0001',
                    'reason' => 'Project cancelled',
                    'cancelled_at' => $cancelledAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleCancelled($event);
        });
    });

    describe('handlePartial', function () {
        it('logs partial receipt with correct context', function () {
            $partialAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new PurchaseOrderPartial(
                purchaseOrderId: 1,
                poNumber: 'PO-2025-0001',
                vendorId: 5,
                totalAmount: 3500000,
                currency: 'IDR',
                receivedPercentage: 75.5,
                userId: 10,
                partialAt: $partialAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('purchase_order.partial', [
                    'purchase_order_id' => 1,
                    'po_number' => 'PO-2025-0001',
                    'received_percentage' => 75.5,
                    'partial_at' => $partialAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handlePartial($event);
        });
    });

    describe('handleReceived', function () {
        it('logs received with correct context', function () {
            $receivedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new PurchaseOrderReceived(
                purchaseOrderId: 1,
                poNumber: 'PO-2025-0001',
                vendorId: 5,
                totalAmount: 3500000,
                currency: 'IDR',
                userId: 10,
                receivedAt: $receivedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('purchase_order.received', [
                    'purchase_order_id' => 1,
                    'po_number' => 'PO-2025-0001',
                    'received_at' => $receivedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleReceived($event);
        });
    });
});
