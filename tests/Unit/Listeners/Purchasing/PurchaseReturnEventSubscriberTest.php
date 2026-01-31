<?php

declare(strict_types=1);

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnApproved;
use App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnCancelled;
use App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnCompleted;
use App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnRejected;
use App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnStatusChanged;
use App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnSubmitted;
use App\Enums\DocumentStatus;
use App\Listeners\Purchasing\PurchaseReturnEventSubscriber;
use Carbon\Carbon;
use Illuminate\Events\Dispatcher;

describe('PurchaseReturnEventSubscriber', function () {
    beforeEach(function () {
        $this->logger = Mockery::mock(ContextualLoggerInterface::class);
        $this->subscriber = new PurchaseReturnEventSubscriber($this->logger);
    });

    describe('subscribe', function () {
        it('subscribes to all purchase return events', function () {
            $dispatcher = Mockery::mock(Dispatcher::class);

            $subscriptions = $this->subscriber->subscribe($dispatcher);

            expect($subscriptions)->toBeArray()
                ->and($subscriptions)->toHaveKey(PurchaseReturnStatusChanged::class)
                ->and($subscriptions)->toHaveKey(PurchaseReturnSubmitted::class)
                ->and($subscriptions)->toHaveKey(PurchaseReturnApproved::class)
                ->and($subscriptions)->toHaveKey(PurchaseReturnRejected::class)
                ->and($subscriptions)->toHaveKey(PurchaseReturnCompleted::class)
                ->and($subscriptions)->toHaveKey(PurchaseReturnCancelled::class);
        });
    });

    describe('handleStatusChanged', function () {
        it('logs status change with correct context', function () {
            $event = new PurchaseReturnStatusChanged(
                purchaseReturnId: 1,
                fromStatus: DocumentStatus::Draft,
                toStatus: DocumentStatus::Submitted,
                userId: 10,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('purchase_return.status_changed', [
                    'purchase_return_id' => 1,
                    'from' => 'draft',
                    'to' => 'submitted',
                    'user_id' => 10,
                ]);

            $this->subscriber->handleStatusChanged($event);
        });
    });

    describe('handleSubmitted', function () {
        it('logs submitted with correct context', function () {
            $submittedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new PurchaseReturnSubmitted(
                purchaseReturnId: 1,
                returnNumber: 'PR-2025-0001',
                contactId: 5,
                billId: 10,
                userId: 8,
                submittedAt: $submittedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('purchase_return.submitted', [
                    'purchase_return_id' => 1,
                    'return_number' => 'PR-2025-0001',
                    'contact_id' => 5,
                    'bill_id' => 10,
                    'submitted_at' => $submittedAt->toIso8601String(),
                    'user_id' => 8,
                ]);

            $this->subscriber->handleSubmitted($event);
        });
    });

    describe('handleApproved', function () {
        it('logs approval with correct context', function () {
            $approvedAt = Carbon::parse('2025-01-16 14:00:00');

            $event = new PurchaseReturnApproved(
                purchaseReturnId: 2,
                returnNumber: 'PR-2025-0002',
                contactId: 6,
                billId: 11,
                userId: 12,
                approvedAt: $approvedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('purchase_return.approved', [
                    'purchase_return_id' => 2,
                    'return_number' => 'PR-2025-0002',
                    'approved_at' => $approvedAt->toIso8601String(),
                    'user_id' => 12,
                ]);

            $this->subscriber->handleApproved($event);
        });
    });

    describe('handleRejected', function () {
        it('logs rejection with reason', function () {
            $rejectedAt = Carbon::parse('2025-01-17 09:00:00');

            $event = new PurchaseReturnRejected(
                purchaseReturnId: 3,
                returnNumber: 'PR-2025-0003',
                contactId: 7,
                billId: 12,
                userId: 15,
                rejectedAt: $rejectedAt,
                reason: 'Items not defective',
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('purchase_return.rejected', [
                    'purchase_return_id' => 3,
                    'return_number' => 'PR-2025-0003',
                    'reason' => 'Items not defective',
                    'rejected_at' => $rejectedAt->toIso8601String(),
                    'user_id' => 15,
                ]);

            $this->subscriber->handleRejected($event);
        });
    });

    describe('handleCompleted', function () {
        it('logs completion with correct context', function () {
            $completedAt = Carbon::parse('2025-01-20 16:00:00');

            $event = new PurchaseReturnCompleted(
                purchaseReturnId: 4,
                returnNumber: 'PR-2025-0004',
                contactId: 8,
                billId: 13,
                userId: 10,
                completedAt: $completedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('purchase_return.completed', [
                    'purchase_return_id' => 4,
                    'return_number' => 'PR-2025-0004',
                    'completed_at' => $completedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleCompleted($event);
        });
    });

    describe('handleCancelled', function () {
        it('logs cancellation with reason', function () {
            $cancelledAt = Carbon::parse('2025-01-21 11:00:00');

            $event = new PurchaseReturnCancelled(
                purchaseReturnId: 5,
                returnNumber: 'PR-2025-0005',
                contactId: 9,
                billId: 14,
                userId: 20,
                cancelledAt: $cancelledAt,
                reason: 'Vendor resolved issue',
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('purchase_return.cancelled', [
                    'purchase_return_id' => 5,
                    'return_number' => 'PR-2025-0005',
                    'reason' => 'Vendor resolved issue',
                    'cancelled_at' => $cancelledAt->toIso8601String(),
                    'user_id' => 20,
                ]);

            $this->subscriber->handleCancelled($event);
        });
    });
});
