<?php

declare(strict_types=1);

namespace App\Listeners\Sales;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderCancelled;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderConfirmed;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderDelivered;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderShipped;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderStatusChanged;
use Illuminate\Events\Dispatcher;

/**
 * Handles all delivery order-related events.
 */
class DeliveryOrderEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function handleStatusChanged(DeliveryOrderStatusChanged $event): void
    {
        $this->logger->logOperation('delivery_order.status_changed', [
            'delivery_order_id' => $event->deliveryOrderId,
            'from' => $event->fromStatus->value,
            'to' => $event->toStatus->value,
            'user_id' => $event->userId,
        ]);
    }

    public function handleConfirmed(DeliveryOrderConfirmed $event): void
    {
        $this->logger->logOperation('delivery_order.confirmed', [
            'delivery_order_id' => $event->deliveryOrderId,
            'do_number' => $event->doNumber,
            'customer_id' => $event->customerId,
            'confirmed_at' => $event->confirmedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleShipped(DeliveryOrderShipped $event): void
    {
        $this->logger->logOperation('delivery_order.shipped', [
            'delivery_order_id' => $event->deliveryOrderId,
            'do_number' => $event->doNumber,
            'shipping_method' => $event->shippingMethod,
            'tracking_number' => $event->trackingNumber,
            'shipped_at' => $event->shippedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleDelivered(DeliveryOrderDelivered $event): void
    {
        $this->logger->logOperation('delivery_order.delivered', [
            'delivery_order_id' => $event->deliveryOrderId,
            'do_number' => $event->doNumber,
            'delivered_at' => $event->deliveredAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleCancelled(DeliveryOrderCancelled $event): void
    {
        $this->logger->logOperation('delivery_order.cancelled', [
            'delivery_order_id' => $event->deliveryOrderId,
            'do_number' => $event->doNumber,
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
            DeliveryOrderStatusChanged::class => 'handleStatusChanged',
            DeliveryOrderConfirmed::class => 'handleConfirmed',
            DeliveryOrderShipped::class => 'handleShipped',
            DeliveryOrderDelivered::class => 'handleDelivered',
            DeliveryOrderCancelled::class => 'handleCancelled',
        ];
    }
}
