<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations;

readonly class QuotationTotals
{
    public function __construct(
        public int $subtotal,
        public int $discountAmount,
        public int $taxAmount,
        public int $totalAmount,
        public int $baseCurrencyTotal
    ) {}
}
