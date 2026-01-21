<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Tax;

use App\Contracts\Accounting\TaxCalculationStrategy;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Tax exclusive calculation (Indonesian PPN standard).
 *
 * Tax is added on top of the base price:
 * - Price = 100,000
 * - Tax (11%) = 11,000
 * - Total = 111,000
 *
 * This is the standard approach in Indonesia where prices
 * are typically quoted before tax.
 */
class TaxExclusiveStrategy implements TaxCalculationStrategy
{
    /**
     * Calculate tax: Amount × (Rate / 100)
     */
    public function calculate(Money $amount, float $rate): Money
    {
        return $amount->percentage($rate);
    }

    /**
     * Base amount is the amount itself (not tax inclusive).
     */
    public function getBaseAmount(Money $amount, float $rate): Money
    {
        return $amount;
    }

    public function isTaxInclusive(): bool
    {
        return false;
    }

    public function getName(): string
    {
        return 'exclusive';
    }
}
