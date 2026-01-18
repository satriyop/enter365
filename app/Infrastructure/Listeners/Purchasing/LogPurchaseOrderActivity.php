<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Purchasing;

use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderApproved;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderCancelled;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderPartial;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderReceived;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderRejected;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderStatusChanged;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderSubmitted;
use Illuminate\Support\Facades\Log;

class LogPurchaseOrderActivity
{
    public function handle(
        PurchaseOrderSubmitted|PurchaseOrderApproved|PurchaseOrderRejected|
        PurchaseOrderCancelled|PurchaseOrderPartial|PurchaseOrderReceived|PurchaseOrderStatusChanged $event
    ): void {
        if ($event instanceof PurchaseOrderStatusChanged) {
            Log::info('Purchase Order status changed', [
                'purchase_order_id' => $event->purchaseOrderId,
                'from_status' => $event->fromStatus->value,
                'to_status' => $event->toStatus->value,
                'user_id' => $event->userId,
            ]);

            return;
        }

        if ($event instanceof PurchaseOrderSubmitted) {
            Log::info('Purchase Order submitted', [
                'purchase_order_id' => $event->purchaseOrderId,
                'po_number' => $event->poNumber,
                'vendor_id' => $event->vendorId,
                'total_amount' => $event->totalAmount,
                'user_id' => $event->userId,
            ]);

            return;
        }

        if ($event instanceof PurchaseOrderApproved) {
            Log::info('Purchase Order approved', [
                'purchase_order_id' => $event->purchaseOrderId,
                'po_number' => $event->poNumber,
                'vendor_id' => $event->vendorId,
                'total_amount' => $event->totalAmount,
                'user_id' => $event->userId,
            ]);

            return;
        }

        if ($event instanceof PurchaseOrderRejected) {
            Log::info('Purchase Order rejected', [
                'purchase_order_id' => $event->purchaseOrderId,
                'po_number' => $event->poNumber,
                'vendor_id' => $event->vendorId,
                'reason' => $event->reason,
                'user_id' => $event->userId,
            ]);

            return;
        }

        if ($event instanceof PurchaseOrderCancelled) {
            Log::info('Purchase Order cancelled', [
                'purchase_order_id' => $event->purchaseOrderId,
                'po_number' => $event->poNumber,
                'vendor_id' => $event->vendorId,
                'reason' => $event->reason,
                'user_id' => $event->userId,
            ]);

            return;
        }

        if ($event instanceof PurchaseOrderPartial) {
            Log::info('Purchase Order partially received', [
                'purchase_order_id' => $event->purchaseOrderId,
                'po_number' => $event->poNumber,
                'vendor_id' => $event->vendorId,
                'received_percentage' => $event->receivedPercentage,
                'user_id' => $event->userId,
            ]);

            return;
        }

        if ($event instanceof PurchaseOrderReceived) {
            Log::info('Purchase Order fully received', [
                'purchase_order_id' => $event->purchaseOrderId,
                'po_number' => $event->poNumber,
                'vendor_id' => $event->vendorId,
                'total_amount' => $event->totalAmount,
                'user_id' => $event->userId,
            ]);
        }
    }
}
