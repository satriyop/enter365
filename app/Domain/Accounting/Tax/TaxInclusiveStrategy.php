<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Tax;

use App\Contracts\Accounting\TaxCalculationStrategy;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Tax inclusive calculation.
 *
 * Tax is already included in the quoted price:
 * - Total (including tax) = 111,000
 * - Tax (11%) = 11,000
 * - Base Price = 100,000
 *
 * Formula: Tax = Total - (Total / (1 + Rate/100))
 *
 * Used in some retail scenarios where consumer prices
 * are displayed with tax already included.
 */
class TaxInclusiveStrategy implements TaxCalculationStrategy
{
    /**
     * Calculate tax from tax-included amount.
     *
     * Tax = TotalAmount - (TotalAmount / (1 + Rate/100))
     */
    public function calculate(Money $totalAmount, float $rate): Money
    {
        $baseAmount = $this->getBaseAmount($totalAmount, $rate);

        // Tax = Total - Base (handle the case where base might be slightly higher due to rounding)
        $taxAmount = $totalAmount->amount - $baseAmount->amount;

        if ($taxAmount < 0) {
            $taxAmount = 0;
        }

        return Money::of($taxAmount, $totalAmount->currency);
    }

    /**
     * Extract base amount from tax-included total.
     *
     * BaseAmount = TotalAmount / (1 + Rate/100)
     */
    public function getBaseAmount(Money $totalAmount, float $rate): Money
    {
        $divisor = 1 + ($rate / 100);

        return $totalAmount->divide($divisor);
    }

    public function isTaxInclusive(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'inclusive';
    }
}
