<?php

declare(strict_types=1);

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Sales\SalesReturns\Events\SalesReturnApproved;
use App\Domain\Sales\SalesReturns\Events\SalesReturnCancelled;
use App\Domain\Sales\SalesReturns\Events\SalesReturnCompleted;
use App\Domain\Sales\SalesReturns\Events\SalesReturnRejected;
use App\Domain\Sales\SalesReturns\Events\SalesReturnStatusChanged;
use App\Domain\Sales\SalesReturns\Events\SalesReturnSubmitted;
use App\Enums\DocumentStatus;
use App\Listeners\Sales\SalesReturnEventSubscriber;
use Carbon\Carbon;
use Illuminate\Events\Dispatcher;

describe('SalesReturnEventSubscriber', function () {
    beforeEach(function () {
        $this->logger = Mockery::mock(ContextualLoggerInterface::class);
        $this->subscriber = new SalesReturnEventSubscriber($this->logger);
    });

    describe('subscribe', function () {
        it('subscribes to all sales return events', function () {
            $dispatcher = Mockery::mock(Dispatcher::class);

            $subscriptions = $this->subscriber->subscribe($dispatcher);

            expect($subscriptions)->toBeArray()
                ->and($subscriptions)->toHaveKey(SalesReturnStatusChanged::class)
                ->and($subscriptions)->toHaveKey(SalesReturnSubmitted::class)
                ->and($subscriptions)->toHaveKey(SalesReturnApproved::class)
                ->and($subscriptions)->toHaveKey(SalesReturnRejected::class)
                ->and($subscriptions)->toHaveKey(SalesReturnCompleted::class)
                ->and($subscriptions)->toHaveKey(SalesReturnCancelled::class);
        });
    });

    describe('handleStatusChanged', function () {
        it('logs status change with correct context', function () {
            $event = new SalesReturnStatusChanged(
                salesReturnId: 1,
                fromStatus: DocumentStatus::Draft,
                toStatus: DocumentStatus::Sent,
                userId: 10,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('sales_return.status_changed', [
                    'sales_return_id' => 1,
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

            $event = new SalesReturnSubmitted(
                salesReturnId: 1,
                returnNumber: 'SR-2025-0001',
                contactId: 5,
                invoiceId: 42,
                userId: 10,
                submittedAt: $submittedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('sales_return.submitted', [
                    'sales_return_id' => 1,
                    'return_number' => 'SR-2025-0001',
                    'contact_id' => 5,
                    'invoice_id' => 42,
                    'submitted_at' => $submittedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleSubmitted($event);
        });
    });

    describe('handleApproved', function () {
        it('logs approved with correct context', function () {
            $approvedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new SalesReturnApproved(
                salesReturnId: 1,
                returnNumber: 'SR-2025-0001',
                contactId: 5,
                invoiceId: 42,
                userId: 10,
                approvedAt: $approvedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('sales_return.approved', [
                    'sales_return_id' => 1,
                    'return_number' => 'SR-2025-0001',
                    'approved_at' => $approvedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleApproved($event);
        });
    });

    describe('handleRejected', function () {
        it('logs rejected with correct context', function () {
            $rejectedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new SalesReturnRejected(
                salesReturnId: 1,
                returnNumber: 'SR-2025-0001',
                contactId: 5,
                invoiceId: 42,
                userId: 10,
                rejectedAt: $rejectedAt,
                reason: 'Return period expired',
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('sales_return.rejected', [
                    'sales_return_id' => 1,
                    'return_number' => 'SR-2025-0001',
                    'reason' => 'Return period expired',
                    'rejected_at' => $rejectedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleRejected($event);
        });
    });

    describe('handleCompleted', function () {
        it('logs completed with correct context', function () {
            $completedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new SalesReturnCompleted(
                salesReturnId: 1,
                returnNumber: 'SR-2025-0001',
                contactId: 5,
                invoiceId: 42,
                userId: 10,
                completedAt: $completedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('sales_return.completed', [
                    'sales_return_id' => 1,
                    'return_number' => 'SR-2025-0001',
                    'completed_at' => $completedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleCompleted($event);
        });
    });

    describe('handleCancelled', function () {
        it('logs cancelled with correct context', function () {
            $cancelledAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new SalesReturnCancelled(
                salesReturnId: 1,
                returnNumber: 'SR-2025-0001',
                contactId: 5,
                invoiceId: 42,
                userId: 10,
                cancelledAt: $cancelledAt,
                reason: 'Customer changed mind',
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('sales_return.cancelled', [
                    'sales_return_id' => 1,
                    'return_number' => 'SR-2025-0001',
                    'reason' => 'Customer changed mind',
                    'cancelled_at' => $cancelledAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleCancelled($event);
        });
    });
});
