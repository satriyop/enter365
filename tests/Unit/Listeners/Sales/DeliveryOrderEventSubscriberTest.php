<?php

declare(strict_types=1);

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderCancelled;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderConfirmed;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderDelivered;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderShipped;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderStatusChanged;
use App\Enums\DocumentStatus;
use App\Listeners\Sales\DeliveryOrderEventSubscriber;
use Carbon\Carbon;
use Illuminate\Events\Dispatcher;

describe('DeliveryOrderEventSubscriber', function () {
    beforeEach(function () {
        $this->logger = Mockery::mock(ContextualLoggerInterface::class);
        $this->subscriber = new DeliveryOrderEventSubscriber($this->logger);
    });

    describe('subscribe', function () {
        it('subscribes to all delivery order events', function () {
            $dispatcher = Mockery::mock(Dispatcher::class);

            $subscriptions = $this->subscriber->subscribe($dispatcher);

            expect($subscriptions)->toBeArray()
                ->and($subscriptions)->toHaveKey(DeliveryOrderStatusChanged::class)
                ->and($subscriptions)->toHaveKey(DeliveryOrderConfirmed::class)
                ->and($subscriptions)->toHaveKey(DeliveryOrderShipped::class)
                ->and($subscriptions)->toHaveKey(DeliveryOrderDelivered::class)
                ->and($subscriptions)->toHaveKey(DeliveryOrderCancelled::class);
        });
    });

    describe('handleStatusChanged', function () {
        it('logs status change with correct context', function () {
            $event = new DeliveryOrderStatusChanged(
                deliveryOrderId: 1,
                fromStatus: DocumentStatus::Draft,
                toStatus: DocumentStatus::Confirmed,
                userId: 10,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('delivery_order.status_changed', [
                    'delivery_order_id' => 1,
                    'from' => 'draft',
                    'to' => 'confirmed',
                    'user_id' => 10,
                ]);

            $this->subscriber->handleStatusChanged($event);
        });
    });

    describe('handleConfirmed', function () {
        it('logs confirmed with correct context', function () {
            $confirmedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new DeliveryOrderConfirmed(
                deliveryOrderId: 1,
                doNumber: 'DO-2025-0001',
                customerId: 5,
                userId: 10,
                confirmedAt: $confirmedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('delivery_order.confirmed', [
                    'delivery_order_id' => 1,
                    'do_number' => 'DO-2025-0001',
                    'customer_id' => 5,
                    'confirmed_at' => $confirmedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleConfirmed($event);
        });
    });

    describe('handleShipped', function () {
        it('logs shipped with correct context', function () {
            $shippedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new DeliveryOrderShipped(
                deliveryOrderId: 1,
                doNumber: 'DO-2025-0001',
                customerId: 5,
                shippingMethod: 'JNE Express',
                trackingNumber: 'JNE123456789',
                userId: 10,
                shippedAt: $shippedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('delivery_order.shipped', [
                    'delivery_order_id' => 1,
                    'do_number' => 'DO-2025-0001',
                    'shipping_method' => 'JNE Express',
                    'tracking_number' => 'JNE123456789',
                    'shipped_at' => $shippedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleShipped($event);
        });
    });

    describe('handleDelivered', function () {
        it('logs delivered with correct context', function () {
            $deliveredAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new DeliveryOrderDelivered(
                deliveryOrderId: 1,
                doNumber: 'DO-2025-0001',
                customerId: 5,
                userId: 10,
                deliveredAt: $deliveredAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('delivery_order.delivered', [
                    'delivery_order_id' => 1,
                    'do_number' => 'DO-2025-0001',
                    'delivered_at' => $deliveredAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleDelivered($event);
        });
    });

    describe('handleCancelled', function () {
        it('logs cancelled with correct context', function () {
            $cancelledAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new DeliveryOrderCancelled(
                deliveryOrderId: 1,
                doNumber: 'DO-2025-0001',
                customerId: 5,
                reason: 'Customer requested cancellation',
                userId: 10,
                cancelledAt: $cancelledAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('delivery_order.cancelled', [
                    'delivery_order_id' => 1,
                    'do_number' => 'DO-2025-0001',
                    'reason' => 'Customer requested cancellation',
                    'cancelled_at' => $cancelledAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleCancelled($event);
        });
    });
});
