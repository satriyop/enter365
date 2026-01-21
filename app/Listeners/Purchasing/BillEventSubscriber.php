<?php

declare(strict_types=1);

namespace App\Listeners\Purchasing;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Purchasing\Bills\Events\BillFullyPaid;
use App\Domain\Purchasing\Bills\Events\BillReceived;
use App\Domain\Purchasing\Bills\Events\BillStatusChanged;
use App\Domain\Purchasing\Bills\Events\BillVoided;
use Illuminate\Events\Dispatcher;

/**
 * Handles all bill-related events.
 */
class BillEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function handleStatusChanged(BillStatusChanged $event): void
    {
        $this->logger->logOperation('bill.status_changed', [
            'bill_id' => $event->billId,
            'from' => $event->fromStatus->value,
            'to' => $event->toStatus->value,
            'user_id' => $event->userId,
        ]);
    }

    public function handleReceived(BillReceived $event): void
    {
        $this->logger->logOperation('bill.received', [
            'bill_id' => $event->billId,
            'bill_number' => $event->billNumber,
            'vendor_id' => $event->vendorId,
            'total_amount' => $event->totalAmount,
            'received_at' => $event->receivedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleFullyPaid(BillFullyPaid $event): void
    {
        $this->logger->logOperation('bill.fully_paid', [
            'bill_id' => $event->billId,
            'bill_number' => $event->billNumber,
            'total_amount' => $event->totalAmount,
            'paid_at' => $event->paidAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleVoided(BillVoided $event): void
    {
        $this->logger->logOperation('bill.voided', [
            'bill_id' => $event->billId,
            'bill_number' => $event->billNumber,
            'reason' => $event->reason,
            'voided_at' => $event->voidedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            BillStatusChanged::class => 'handleStatusChanged',
            BillReceived::class => 'handleReceived',
            BillFullyPaid::class => 'handleFullyPaid',
            BillVoided::class => 'handleVoided',
        ];
    }
}
