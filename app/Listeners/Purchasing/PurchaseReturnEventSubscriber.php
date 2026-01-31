<?php

declare(strict_types=1);

namespace App\Listeners\Purchasing;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnApproved;
use App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnCancelled;
use App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnCompleted;
use App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnRejected;
use App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnStatusChanged;
use App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnSubmitted;
use Illuminate\Events\Dispatcher;

/**
 * Handles all purchase return-related events.
 */
class PurchaseReturnEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function handleStatusChanged(PurchaseReturnStatusChanged $event): void
    {
        $this->logger->logOperation('purchase_return.status_changed', [
            'purchase_return_id' => $event->purchaseReturnId,
            'from' => $event->fromStatus->value,
            'to' => $event->toStatus->value,
            'user_id' => $event->userId,
        ]);
    }

    public function handleSubmitted(PurchaseReturnSubmitted $event): void
    {
        $this->logger->logOperation('purchase_return.submitted', [
            'purchase_return_id' => $event->purchaseReturnId,
            'return_number' => $event->returnNumber,
            'contact_id' => $event->contactId,
            'bill_id' => $event->billId,
            'submitted_at' => $event->submittedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleApproved(PurchaseReturnApproved $event): void
    {
        $this->logger->logOperation('purchase_return.approved', [
            'purchase_return_id' => $event->purchaseReturnId,
            'return_number' => $event->returnNumber,
            'approved_at' => $event->approvedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleRejected(PurchaseReturnRejected $event): void
    {
        $this->logger->logOperation('purchase_return.rejected', [
            'purchase_return_id' => $event->purchaseReturnId,
            'return_number' => $event->returnNumber,
            'reason' => $event->reason,
            'rejected_at' => $event->rejectedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleCompleted(PurchaseReturnCompleted $event): void
    {
        $this->logger->logOperation('purchase_return.completed', [
            'purchase_return_id' => $event->purchaseReturnId,
            'return_number' => $event->returnNumber,
            'completed_at' => $event->completedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleCancelled(PurchaseReturnCancelled $event): void
    {
        $this->logger->logOperation('purchase_return.cancelled', [
            'purchase_return_id' => $event->purchaseReturnId,
            'return_number' => $event->returnNumber,
            'reason' => $event->reason,
            'cancelled_at' => $event->cancelledAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            PurchaseReturnStatusChanged::class => 'handleStatusChanged',
            PurchaseReturnSubmitted::class => 'handleSubmitted',
            PurchaseReturnApproved::class => 'handleApproved',
            PurchaseReturnRejected::class => 'handleRejected',
            PurchaseReturnCompleted::class => 'handleCompleted',
            PurchaseReturnCancelled::class => 'handleCancelled',
        ];
    }
}
