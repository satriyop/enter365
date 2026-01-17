<?php

declare(strict_types=1);

namespace App\Contracts\Services\Domain;

use App\Domain\Sales\Invoices\InvoiceTotals;

interface InvoiceCalculatorInterface
{
    /**
     * Calculate invoice totals from items data.
     *
     * @param  array<int, int>  $lineTotals  Array of line item totals
     * @param  float  $taxRate  Tax rate percentage (e.g., 11.0 for 11%)
     * @param  int  $discountAmount  Discount amount to subtract
     * @param  string  $currency  Invoice currency
     * @param  float  $exchangeRate  Exchange rate if multi-currency
     * @return InvoiceTotals Calculated totals
     */
    public function calculate(
        array $lineTotals,
        float $taxRate,
        int $discountAmount,
        string $currency,
        float $exchangeRate
    ): InvoiceTotals;
}
