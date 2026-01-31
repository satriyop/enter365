<?php

declare(strict_types=1);

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Purchasing\Bills\Events\BillFullyPaid;
use App\Domain\Purchasing\Bills\Events\BillReceived;
use App\Domain\Purchasing\Bills\Events\BillStatusChanged;
use App\Domain\Purchasing\Bills\Events\BillVoided;
use App\Enums\DocumentStatus;
use App\Listeners\Purchasing\BillEventSubscriber;
use Carbon\Carbon;
use Illuminate\Events\Dispatcher;

describe('BillEventSubscriber', function () {
    beforeEach(function () {
        $this->logger = Mockery::mock(ContextualLoggerInterface::class);
        $this->subscriber = new BillEventSubscriber($this->logger);
    });

    describe('subscribe', function () {
        it('subscribes to all bill events', function () {
            $dispatcher = Mockery::mock(Dispatcher::class);

            $subscriptions = $this->subscriber->subscribe($dispatcher);

            expect($subscriptions)->toBeArray()
                ->and($subscriptions)->toHaveKey(BillStatusChanged::class)
                ->and($subscriptions)->toHaveKey(BillReceived::class)
                ->and($subscriptions)->toHaveKey(BillFullyPaid::class)
                ->and($subscriptions)->toHaveKey(BillVoided::class);
        });
    });

    describe('handleStatusChanged', function () {
        it('logs status change with correct context', function () {
            $event = new BillStatusChanged(
                billId: 1,
                fromStatus: DocumentStatus::Draft,
                toStatus: DocumentStatus::Sent,
                userId: 10,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('bill.status_changed', [
                    'bill_id' => 1,
                    'from' => 'draft',
                    'to' => 'sent',
                    'user_id' => 10,
                ]);

            $this->subscriber->handleStatusChanged($event);
        });
    });

    describe('handleReceived', function () {
        it('logs received with correct context', function () {
            $receivedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new BillReceived(
                billId: 1,
                billNumber: 'BILL-2025-0001',
                vendorId: 5,
                totalAmount: 2500000,
                currency: 'IDR',
                userId: 10,
                receivedAt: $receivedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('bill.received', [
                    'bill_id' => 1,
                    'bill_number' => 'BILL-2025-0001',
                    'vendor_id' => 5,
                    'total_amount' => 2500000,
                    'received_at' => $receivedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleReceived($event);
        });
    });

    describe('handleFullyPaid', function () {
        it('logs full payment with correct context', function () {
            $paidAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new BillFullyPaid(
                billId: 1,
                vendorId: 5,
                billNumber: 'BILL-2025-0001',
                totalAmount: 2500000,
                currency: 'IDR',
                userId: 10,
                paidAt: $paidAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('bill.fully_paid', [
                    'bill_id' => 1,
                    'bill_number' => 'BILL-2025-0001',
                    'total_amount' => 2500000,
                    'paid_at' => $paidAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleFullyPaid($event);
        });
    });

    describe('handleVoided', function () {
        it('logs voiding with correct context', function () {
            $voidedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new BillVoided(
                billId: 1,
                billNumber: 'BILL-2025-0001',
                vendorId: 5,
                totalAmount: 2500000,
                currency: 'IDR',
                userId: 10,
                voidedAt: $voidedAt,
                reason: 'Vendor cancelled order',
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('bill.voided', [
                    'bill_id' => 1,
                    'bill_number' => 'BILL-2025-0001',
                    'reason' => 'Vendor cancelled order',
                    'voided_at' => $voidedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleVoided($event);
        });
    });
});
