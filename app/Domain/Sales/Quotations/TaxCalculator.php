<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations;

use App\Models\Sales\QuotationItem;

class TaxCalculator
{
    public static function calculateFromItems(iterable $items, float $taxRate = 11): int
    {
        $subtotal = 0;

        foreach ($items as $item) {
            if ($item instanceof QuotationItem) {
                $subtotal += $item->line_total;
            } else {
                $subtotal += $item['line_total'];
            }
        }

        return self::calculateFromSubtotal($subtotal, $taxRate);
    }

    public static function calculateFromSubtotal(int $subtotal, float $taxRate): int
    {
        return (int) round($subtotal * ($taxRate / 100));
    }
}
