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

        // Calculate tax
        $taxAmount = (int) round($subtotal * ($taxRate / 100));

        // Calculate total
        $totalAmount = $subtotal + $taxAmount - $discountAmount;

        // Handle multi-currency
        if ($currency !== 'IDR' && $exchangeRate > 0) {
            $baseCurrencyTotal = (int) round($totalAmount * $exchangeRate);
        } else {
            $baseCurrencyTotal = $totalAmount;
        }

        return new InvoiceTotals($subtotal, $taxAmount, $totalAmount);
    }
}
