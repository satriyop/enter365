<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\PurchaseOrders\Events;

use App\Enums\DocumentStatus;

class PurchaseOrderStatusChanged
{
    public function __construct(
        public readonly int $purchaseOrderId,
        public readonly DocumentStatus $fromStatus,
        public readonly DocumentStatus $toStatus,
        public readonly ?int $userId
    ) {}

    public function toArray(): array
    {
        return [
            'purchase_order_id' => $this->purchaseOrderId,
            'from_status' => $this->fromStatus->value,
            'to_status' => $this->toStatus->value,
            'user_id' => $this->userId,
        ];
    }
}
