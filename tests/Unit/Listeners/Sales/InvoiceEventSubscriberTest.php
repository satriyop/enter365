<?php

declare(strict_types=1);

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Sales\Invoices\Events\InvoiceFullyPaid;
use App\Domain\Sales\Invoices\Events\InvoiceOverdue;
use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Domain\Sales\Invoices\Events\InvoiceStatusChanged;
use App\Domain\Sales\Invoices\Events\InvoiceVoided;
use App\Enums\DocumentStatus;
use App\Listeners\Sales\InvoiceEventSubscriber;
use Carbon\Carbon;
use Illuminate\Events\Dispatcher;

describe('InvoiceEventSubscriber', function () {
    beforeEach(function () {
        $this->logger = Mockery::mock(ContextualLoggerInterface::class);
        $this->subscriber = new InvoiceEventSubscriber($this->logger);
    });

    describe('subscribe', function () {
        it('subscribes to all invoice events', function () {
            $dispatcher = Mockery::mock(Dispatcher::class);

            $subscriptions = $this->subscriber->subscribe($dispatcher);

            expect($subscriptions)->toBeArray()
                ->and($subscriptions)->toHaveKey(InvoiceStatusChanged::class)
                ->and($subscriptions)->toHaveKey(InvoiceSent::class)
                ->and($subscriptions)->toHaveKey(InvoiceFullyPaid::class)
                ->and($subscriptions)->toHaveKey(InvoiceOverdue::class)
                ->and($subscriptions)->toHaveKey(InvoiceVoided::class);
        });
    });

    describe('handleStatusChanged', function () {
        it('logs status change with correct context', function () {
            $event = new InvoiceStatusChanged(
                invoiceId: 1,
                fromStatus: DocumentStatus::Draft,
                toStatus: DocumentStatus::Sent,
                userId: 10,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('invoice.status_changed', [
                    'invoice_id' => 1,
                    'from' => 'draft',
                    'to' => 'sent',
                    'user_id' => 10,
                ]);

            $this->subscriber->handleStatusChanged($event);
        });
    });

    describe('handleSent', function () {
        it('logs sent with correct context', function () {
            $sentAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new InvoiceSent(
                invoiceId: 1,
                invoiceNumber: 'INV-2025-0001',
                customerId: 5,
                totalAmount: 1500000,
                currency: 'IDR',
                userId: 10,
                sentAt: $sentAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('invoice.sent', [
                    'invoice_id' => 1,
                    'invoice_number' => 'INV-2025-0001',
                    'customer_id' => 5,
                    'total_amount' => 1500000,
                    'sent_at' => $sentAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleSent($event);
        });
    });

    describe('handleFullyPaid', function () {
        it('logs full payment with correct context', function () {
            $paidAt = Carbon::parse('2025-01-25 09:00:00');

            $event = new InvoiceFullyPaid(
                invoiceId: 1,
                customerId: 5,
                invoiceNumber: 'INV-2025-0001',
                totalAmount: 1500000,
                currency: 'IDR',
                userId: 10,
                paidAt: $paidAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('invoice.fully_paid', [
                    'invoice_id' => 1,
                    'invoice_number' => 'INV-2025-0001',
                    'total_amount' => 1500000,
                    'paid_at' => $paidAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleFullyPaid($event);
        });
    });

    describe('handleOverdue', function () {
        it('logs overdue with correct context', function () {
            $dueDate = Carbon::parse('2025-01-10');

            $event = new InvoiceOverdue(
                invoiceId: 1,
                customerId: 5,
                invoiceNumber: 'INV-2025-0001',
                outstandingAmount: 500000,
                currency: 'IDR',
                dueDate: $dueDate,
                daysOverdue: 15,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('invoice.overdue', [
                    'invoice_id' => 1,
                    'invoice_number' => 'INV-2025-0001',
                    'outstanding_amount' => 500000,
                    'due_date' => $dueDate->toDateString(),
                    'days_overdue' => 15,
                ]);

            $this->subscriber->handleOverdue($event);
        });
    });

    describe('handleVoided', function () {
        it('logs voiding with correct context', function () {
            $voidedAt = Carbon::parse('2025-01-26 11:00:00');

            $event = new InvoiceVoided(
                invoiceId: 1,
                invoiceNumber: 'INV-2025-0001',
                customerId: 5,
                totalAmount: 1500000,
                currency: 'IDR',
                userId: 10,
                voidedAt: $voidedAt,
                reason: 'Customer requested cancellation',
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('invoice.voided', [
                    'invoice_id' => 1,
                    'invoice_number' => 'INV-2025-0001',
                    'reason' => 'Customer requested cancellation',
                    'voided_at' => $voidedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleVoided($event);
        });
    });
});
