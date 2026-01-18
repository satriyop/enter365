<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\PurchaseOrders;

use App\Contracts\Purchasing\PurchaseOrderCalculatorInterface;

class PurchaseOrderCalculator implements PurchaseOrderCalculatorInterface
{
    /**
     * Calculate purchase order totals.
     */
    public function calculate(
        array $lineTotals,
        float $taxRate,
        ?string $discountType,
        ?float $discountValue,
        string $currency,
        float $exchangeRate
    ): PurchaseOrderTotals {
        $subtotal = (int) array_sum($lineTotals);

        $discountAmount = $this->calculateDiscount($subtotal, $discountType, $discountValue);

        $taxableAmount = $subtotal - $discountAmount;
        $taxAmount = (int) round($taxableAmount * ($taxRate / 100));

        $totalAmount = $taxableAmount + $taxAmount;

        $baseCurrencyTotal = $this->calculateBaseCurrencyTotal($totalAmount, $currency, $exchangeRate);

        return new PurchaseOrderTotals(
            subtotal: $subtotal,
            discountAmount: $discountAmount,
            taxAmount: $taxAmount,
            totalAmount: $totalAmount,
            baseCurrencyTotal: $baseCurrencyTotal
        );
    }

    /**
     * Calculate discount amount based on type.
     */
    private function calculateDiscount(int $subtotal, ?string $discountType, ?float $discountValue): int
    {
        if ($discountType === 'percentage' && $discountValue !== null && $discountValue > 0) {
            return (int) round($subtotal * ($discountValue / 100));
        }

        if ($discountType === 'fixed' && $discountValue !== null) {
            return (int) $discountValue;
        }

        return 0;
    }

    /**
     * Calculate base currency total for multi-currency POs.
     */
    private function calculateBaseCurrencyTotal(int $totalAmount, string $currency, float $exchangeRate): int
    {
        if ($currency !== 'IDR' && $exchangeRate > 0) {
            return (int) round($totalAmount * $exchangeRate);
        }

        return $totalAmount;
    }
}
