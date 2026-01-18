<?php

declare(strict_types=1);

namespace App\Domain\Sales\DeliveryOrders\Events;

use App\Enums\DocumentStatus;

class DeliveryOrderStatusChanged
{
    public function __construct(
        public readonly int $deliveryOrderId,
        public readonly DocumentStatus $fromStatus,
        public readonly DocumentStatus $toStatus,
        public readonly ?int $userId
    ) {}

    public function toArray(): array
    {
        return [
            'delivery_order_id' => $this->deliveryOrderId,
            'from_status' => $this->fromStatus->value,
            'to_status' => $this->toStatus->value,
            'user_id' => $this->userId,
        ];
    }
}
