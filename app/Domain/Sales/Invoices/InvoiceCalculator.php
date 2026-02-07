<?php

declare(strict_types=1);

namespace App\Domain\Sales\Invoices;

use App\Contracts\Sales\InvoiceCalculatorInterface;

class InvoiceCalculator implements InvoiceCalculatorInterface
{
    /**
     * Calculate invoice totals.
     */
    public function calculate(
        array $lineTotals,
        float $taxRate,
        int $discountAmount,
        string $currency,
        float $exchangeRate
    ): InvoiceTotals {
        // Sum all line totals
        $subtotal = (int) array_sum($lineTotals);

        // Calculate tax per-line with rounding, then sum (PMK-151/PMK.03/2013 compliant)
        $taxAmount = 0;
        foreach ($lineTotals as $lineTotal) {
            $taxAmount += (int) round((int) $lineTotal * ($taxRate / 100));
        }

        // Calculate total (floor at zero to prevent negative)
        $totalAmount = max(0, $subtotal + $taxAmount - $discountAmount);

        // Handle multi-currency
        if ($currency !== 'IDR' && $exchangeRate > 0) {
            $baseCurrencyTotal = (int) round($totalAmount * $exchangeRate);
        } else {
            $baseCurrencyTotal = $totalAmount;
        }

        return new InvoiceTotals($subtotal, $taxAmount, $totalAmount);
    }
}
