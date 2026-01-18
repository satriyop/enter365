<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\PurchaseOrders;

readonly class PurchaseOrderTotals
{
    public function __construct(
        public int $subtotal,
        public int $discountAmount,
        public int $taxAmount,
        public int $totalAmount,
        public int $baseCurrencyTotal
    ) {}
}
