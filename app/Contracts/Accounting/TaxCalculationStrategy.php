<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Domain\Shared\ValueObjects\Money;

/**
 * Strategy interface for tax calculation.
 *
 * Supports both tax-exclusive (Indonesian standard) and
 * tax-inclusive pricing models.
 */
interface TaxCalculationStrategy
{
    /**
     * Calculate tax amount from a monetary amount.
     *
     * For exclusive: Tax = Amount × Rate
     * For inclusive: Tax = Amount - (Amount / (1 + Rate))
     *
     * @param  Money  $amount  The amount to calculate tax for
     * @param  float  $rate  Tax rate as percentage (e.g., 11 for 11%)
     */
    public function calculate(Money $amount, float $rate): Money;

    /**
     * Extract base amount from a tax-included total.
     *
     * For exclusive: BaseAmount = Amount (already base)
     * For inclusive: BaseAmount = Amount / (1 + Rate)
     *
     * @param  Money  $amount  The total amount
     * @param  float  $rate  Tax rate as percentage
     */
    public function getBaseAmount(Money $amount, float $rate): Money;

    /**
     * Check if prices are tax inclusive.
     */
    public function isTaxInclusive(): bool;

    /**
     * Get strategy name for configuration.
     */
    public function getName(): string;
}
