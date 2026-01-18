<?php

declare(strict_types=1);

namespace App\Domain\Sales\DeliveryOrders\Events;

use Carbon\Carbon;

class DeliveryOrderConfirmed
{
    public function __construct(
        public readonly int $deliveryOrderId,
        public readonly string $doNumber,
        public readonly int $customerId,
        public readonly ?int $userId,
        public readonly Carbon $confirmedAt
    ) {}
}
