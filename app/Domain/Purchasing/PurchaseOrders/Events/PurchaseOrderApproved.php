<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\PurchaseOrders\Events;

use Carbon\Carbon;

class PurchaseOrderApproved
{
    public function __construct(
        public readonly int $purchaseOrderId,
        public readonly string $poNumber,
        public readonly int $vendorId,
        public readonly int $totalAmount,
        public readonly string $currency,
        public readonly ?int $userId,
        public readonly Carbon $approvedAt
    ) {}
}
