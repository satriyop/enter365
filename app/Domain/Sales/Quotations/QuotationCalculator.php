<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations;

use App\Contracts\Services\Sales\QuotationCalculatorInterface;

class QuotationCalculator implements QuotationCalculatorInterface
{
    /**
     * Calculate quotation totals.
     */
    public function calculate(
        array $lineTotals,
        float $taxRate,
        ?string $discountType,
        ?float $discountValue,
        string $currency,
        float $exchangeRate
    ): QuotationTotals {
        $subtotal = (int) array_sum($lineTotals);

        $discountAmount = DiscountCalculator::calculate($discountType, $discountValue ?? 0, $subtotal);

        $taxableAmount = $subtotal - $discountAmount;
        $taxAmount = TaxCalculator::calculateFromSubtotal($taxableAmount, (int) $taxRate);

        $totalAmount = $taxableAmount + $taxAmount;

        $baseCurrencyTotal = $this->calculateBaseCurrencyTotal($totalAmount, $currency, $exchangeRate);

        return new QuotationTotals(
            subtotal: $subtotal,
            discountAmount: $discountAmount,
            taxAmount: $taxAmount,
            totalAmount: $totalAmount,
            baseCurrencyTotal: $baseCurrencyTotal
        );
    }

    /**
     * Calculate base currency total for multi-currency quotations.
     */
    private function calculateBaseCurrencyTotal(int $totalAmount, string $currency, float $exchangeRate): int
    {
        if ($currency !== 'IDR' && $exchangeRate > 0) {
            return (int) round($totalAmount * $exchangeRate);
        }

        return $totalAmount;
    }
}
