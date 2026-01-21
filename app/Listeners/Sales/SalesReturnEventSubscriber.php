<?php

declare(strict_types=1);

namespace App\Listeners\Sales;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Sales\SalesReturns\Events\SalesReturnApproved;
use App\Domain\Sales\SalesReturns\Events\SalesReturnCancelled;
use App\Domain\Sales\SalesReturns\Events\SalesReturnCompleted;
use App\Domain\Sales\SalesReturns\Events\SalesReturnRejected;
use App\Domain\Sales\SalesReturns\Events\SalesReturnStatusChanged;
use App\Domain\Sales\SalesReturns\Events\SalesReturnSubmitted;
use Illuminate\Events\Dispatcher;

/**
 * Handles all sales return-related events.
 */
class SalesReturnEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function handleStatusChanged(SalesReturnStatusChanged $event): void
    {
        $this->logger->logOperation('sales_return.status_changed', [
            'sales_return_id' => $event->salesReturnId,
            'from' => $event->fromStatus->value,
            'to' => $event->toStatus->value,
            'user_id' => $event->userId,
        ]);
    }

    public function handleSubmitted(SalesReturnSubmitted $event): void
    {
        $this->logger->logOperation('sales_return.submitted', [
            'sales_return_id' => $event->salesReturnId,
            'return_number' => $event->returnNumber,
            'contact_id' => $event->contactId,
            'invoice_id' => $event->invoiceId,
            'submitted_at' => $event->submittedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleApproved(SalesReturnApproved $event): void
    {
        $this->logger->logOperation('sales_return.approved', [
            'sales_return_id' => $event->salesReturnId,
            'return_number' => $event->returnNumber,
            'approved_at' => $event->approvedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleRejected(SalesReturnRejected $event): void
    {
        $this->logger->logOperation('sales_return.rejected', [
            'sales_return_id' => $event->salesReturnId,
            'return_number' => $event->returnNumber,
            'reason' => $event->reason,
            'rejected_at' => $event->rejectedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleCompleted(SalesReturnCompleted $event): void
    {
        $this->logger->logOperation('sales_return.completed', [
            'sales_return_id' => $event->salesReturnId,
            'return_number' => $event->returnNumber,
            'completed_at' => $event->completedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleCancelled(SalesReturnCancelled $event): void
    {
        $this->logger->logOperation('sales_return.cancelled', [
            'sales_return_id' => $event->salesReturnId,
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
            SalesReturnStatusChanged::class => 'handleStatusChanged',
            SalesReturnSubmitted::class => 'handleSubmitted',
            SalesReturnApproved::class => 'handleApproved',
            SalesReturnRejected::class => 'handleRejected',
            SalesReturnCompleted::class => 'handleCompleted',
            SalesReturnCancelled::class => 'handleCancelled',
        ];
    }
}
