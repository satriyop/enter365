<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations;

use App\Models\Sales\QuotationItem;

readonly class TaxCalculator
{
    public function __construct(
        public int $taxRate,
    ) {}

    public function calculateFromItems(iterable $items): int
    {
        $subtotal = 0;

        foreach ($items as $item) {
            if ($item instanceof QuotationItem) {
                $subtotal += $item->line_total;
            } else {
                $subtotal += $item['line_total'];
            }
        }

        return $this->calculateFromSubtotal($subtotal);
    }

    public function calculateFromSubtotal(int $subtotal): int
    {
        return (int) round($subtotal * ($this->taxRate / 100));
    }
}
