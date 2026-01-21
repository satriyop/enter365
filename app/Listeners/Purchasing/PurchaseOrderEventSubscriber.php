<?php

declare(strict_types=1);

namespace App\Listeners\Purchasing;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderApproved;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderCancelled;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderPartial;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderReceived;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderRejected;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderStatusChanged;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderSubmitted;
use Illuminate\Events\Dispatcher;

/**
 * Handles all purchase order-related events.
 */
class PurchaseOrderEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function handleStatusChanged(PurchaseOrderStatusChanged $event): void
    {
        $this->logger->logOperation('purchase_order.status_changed', [
            'purchase_order_id' => $event->purchaseOrderId,
            'from' => $event->fromStatus->value,
            'to' => $event->toStatus->value,
            'user_id' => $event->userId,
        ]);
    }

    public function handleSubmitted(PurchaseOrderSubmitted $event): void
    {
        $this->logger->logOperation('purchase_order.submitted', [
            'purchase_order_id' => $event->purchaseOrderId,
            'po_number' => $event->poNumber,
            'vendor_id' => $event->vendorId,
            'total_amount' => $event->totalAmount,
            'submitted_at' => $event->submittedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleApproved(PurchaseOrderApproved $event): void
    {
        $this->logger->logOperation('purchase_order.approved', [
            'purchase_order_id' => $event->purchaseOrderId,
            'po_number' => $event->poNumber,
            'approved_at' => $event->approvedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleRejected(PurchaseOrderRejected $event): void
    {
        $this->logger->logOperation('purchase_order.rejected', [
            'purchase_order_id' => $event->purchaseOrderId,
            'po_number' => $event->poNumber,
            'reason' => $event->reason,
            'rejected_at' => $event->rejectedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleCancelled(PurchaseOrderCancelled $event): void
    {
        $this->logger->logOperation('purchase_order.cancelled', [
            'purchase_order_id' => $event->purchaseOrderId,
            'po_number' => $event->poNumber,
            'reason' => $event->reason,
            'cancelled_at' => $event->cancelledAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handlePartial(PurchaseOrderPartial $event): void
    {
        $this->logger->logOperation('purchase_order.partial', [
            'purchase_order_id' => $event->purchaseOrderId,
            'po_number' => $event->poNumber,
            'received_percentage' => $event->receivedPercentage,
            'partial_at' => $event->partialAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleReceived(PurchaseOrderReceived $event): void
    {
        $this->logger->logOperation('purchase_order.received', [
            'purchase_order_id' => $event->purchaseOrderId,
            'po_number' => $event->poNumber,
            'received_at' => $event->receivedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            PurchaseOrderStatusChanged::class => 'handleStatusChanged',
            PurchaseOrderSubmitted::class => 'handleSubmitted',
            PurchaseOrderApproved::class => 'handleApproved',
            PurchaseOrderRejected::class => 'handleRejected',
            PurchaseOrderCancelled::class => 'handleCancelled',
            PurchaseOrderPartial::class => 'handlePartial',
            PurchaseOrderReceived::class => 'handleReceived',
        ];
    }
}
