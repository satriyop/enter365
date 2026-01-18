<?php

declare(strict_types=1);

namespace App\Domain\Sales\DeliveryOrders\Events;

use Carbon\Carbon;

class DeliveryOrderCancelled
{
    public function __construct(
        public readonly int $deliveryOrderId,
        public readonly string $doNumber,
        public readonly int $customerId,
        public readonly string $reason,
        public readonly ?int $userId,
        public readonly Carbon $cancelledAt
    ) {}
}
