<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderCancelled;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderConfirmed;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderDelivered;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderShipped;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderStatusChanged;
use Illuminate\Support\Facades\Log;

class LogDeliveryOrderActivity
{
    public function handle(
        DeliveryOrderConfirmed|DeliveryOrderShipped|DeliveryOrderDelivered|
        DeliveryOrderCancelled|DeliveryOrderStatusChanged $event
    ): void {
        if ($event instanceof DeliveryOrderStatusChanged) {
            Log::info('Delivery Order status changed', [
                'delivery_order_id' => $event->deliveryOrderId,
                'from_status' => $event->fromStatus->value,
                'to_status' => $event->toStatus->value,
                'user_id' => $event->userId,
            ]);

            return;
        }

        if ($event instanceof DeliveryOrderConfirmed) {
            Log::info('Delivery Order confirmed', [
                'delivery_order_id' => $event->deliveryOrderId,
                'do_number' => $event->doNumber,
                'customer_id' => $event->customerId,
                'user_id' => $event->userId,
            ]);

            return;
        }

        if ($event instanceof DeliveryOrderShipped) {
            Log::info('Delivery Order shipped', [
                'delivery_order_id' => $event->deliveryOrderId,
                'do_number' => $event->doNumber,
                'customer_id' => $event->customerId,
                'shipping_method' => $event->shippingMethod,
                'tracking_number' => $event->trackingNumber,
                'user_id' => $event->userId,
            ]);

            return;
        }

        if ($event instanceof DeliveryOrderDelivered) {
            Log::info('Delivery Order delivered', [
                'delivery_order_id' => $event->deliveryOrderId,
                'do_number' => $event->doNumber,
                'customer_id' => $event->customerId,
                'user_id' => $event->userId,
            ]);

            return;
        }

        // Last branch: must be DeliveryOrderCancelled
        Log::info('Delivery Order cancelled', [
            'delivery_order_id' => $event->deliveryOrderId,
            'do_number' => $event->doNumber,
            'customer_id' => $event->customerId,
            'reason' => $event->reason,
            'user_id' => $event->userId,
        ]);
    }
}
