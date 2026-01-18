<?php

declare(strict_types=1);

namespace App\Contracts\Services\Purchasing;

interface PurchaseOrderCalculatorInterface
{
    /**
     * Calculate purchase order totals from items data.
     *
     * @param  array<int, int>  $lineTotals  Array of line item totals
     * @param  float  $taxRate  Tax rate percentage (e.g., 11.0 for 11%)
     * @param  string|null  $discountType  Discount type: 'percentage' or 'fixed'
     * @param  float|null  $discountValue  Discount value (percentage or fixed amount)
     * @param  string  $currency  PO currency
     * @param  float  $exchangeRate  Exchange rate if multi-currency
     * @return \App\Domain\Purchasing\PurchaseOrders\PurchaseOrderTotals Calculated totals
     */
    public function calculate(
        array $lineTotals,
        float $taxRate,
        ?string $discountType,
        ?float $discountValue,
        string $currency,
        float $exchangeRate
    ): \App\Domain\Purchasing\PurchaseOrders\PurchaseOrderTotals;
}
