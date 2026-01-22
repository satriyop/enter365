<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Trait for models that support discounts.
 *
 * Provides common discount calculation methods for document-level discounts:
 * - Early payment discounts
 * - Percentage discounts
 * - Fixed amount discounts
 * - Document-level promotional discounts
 *
 * Usage:
 *   use HasDocumentDiscount;
 *
 *   protected function getDiscountAmountColumn(): string
 *   {
 *       return 'discount_amount';
 *   }
 *
 *   protected function getTotalAmountColumn(): string
 *   {
 *       return 'total_amount';
 *   }
 */
trait HasDocumentDiscount
{
    /**
     * Get the discount amount column name.
     */
    abstract protected function getDiscountAmountColumn(): string;

    /**
     * Get the total amount column name.
     */
    abstract protected function getTotalAmountColumn(): string;

    /**
     * Get early discount percent column name.
     */
    protected function getEarlyDiscountPercentColumn(): string
    {
        return 'early_discount_percent';
    }

    /**
     * Get early discount deadline column name.
     */
    protected function getEarlyDiscountDeadlineColumn(): string
    {
        return 'early_discount_deadline';
    }

    /**
     * Get discount type column name.
     */
    protected function getDiscountTypeColumn(): string
    {
        return 'discount_type';
    }

    /**
     * Get discount value column name.
     */
    protected function getDiscountValueColumn(): string
    {
        return 'discount_value';
    }

    /**
     * Check if early payment discount is available.
     */
    public function hasEarlyPaymentDiscount(): bool
    {
        $percentColumn = $this->getEarlyDiscountPercentColumn();
        $deadlineColumn = $this->getEarlyDiscountDeadlineColumn();

        return $this->{$percentColumn} > 0
            && $this->{$deadlineColumn}
            && $this->{$deadlineColumn}->isFuture();
    }

    /**
     * Calculate early payment discount amount.
     */
    public function calculateEarlyDiscountAmount(): int
    {
        if (! $this->hasEarlyPaymentDiscount()) {
            return 0;
        }

        return (int) round(
            $this->{$this->getTotalAmountColumn()} *
            ($this->{$this->getEarlyDiscountPercentColumn()} / 100)
        );
    }

    /**
     * Get the discounted total if paid early.
     */
    public function getEarlyPaymentTotal(): int
    {
        return $this->{$this->getTotalAmountColumn()} - $this->calculateEarlyDiscountAmount();
    }

    /**
     * Calculate discount amount from percentage.
     */
    public function calculatePercentageDiscount(int $grossAmount, float $percent): int
    {
        return (int) round($grossAmount * ($percent / 100));
    }

    /**
     * Apply percentage discount to an amount.
     */
    public function applyPercentageDiscount(int $grossAmount, float $percent): int
    {
        return $grossAmount - $this->calculatePercentageDiscount($grossAmount, $percent);
    }

    /**
     * Get the effective discount amount.
     *
     * For models with discount_type and discount_value:
     * - If type is 'percentage': calculate from value
     * - If type is 'fixed': return value directly
     */
    public function getEffectiveDiscountAmount(int $totalAmount): int
    {
        $typeColumn = $this->getDiscountTypeColumn();
        $valueColumn = $this->getDiscountValueColumn();
        $amountColumn = $this->getDiscountAmountColumn();

        // If discount_type exists, use it
        if (isset($this->{$typeColumn}) && isset($this->{$valueColumn})) {
            $type = $this->{$typeColumn};
            $value = (float) $this->{$valueColumn};

            if ($type === 'percentage') {
                return $this->calculatePercentageDiscount($totalAmount, $value);
            }

            // Fixed amount
            return (int) $value;
        }

        // Fallback to stored discount_amount
        return $this->{$amountColumn} ?? 0;
    }

    /**
     * Check if model has discount applied.
     */
    public function hasDiscount(): bool
    {
        return ($this->{$this->getDiscountAmountColumn()} ?? 0) > 0;
    }

    /**
     * Get discount percentage if applicable.
     */
    public function getDiscountPercentage(?int $totalAmount = null): float
    {
        $amountColumn = $this->getDiscountAmountColumn();
        $valueColumn = $this->getDiscountValueColumn();
        $typeColumn = $this->getDiscountTypeColumn();

        // If using discount_type/value
        if (isset($this->{$typeColumn}) && isset($this->{$valueColumn})) {
            $type = $this->{$typeColumn};
            $value = (float) $this->{$valueColumn};

            if ($type === 'percentage') {
                return $value;
            }

            // Fixed amount - calculate percentage from total
            if ($type === 'fixed' && $totalAmount > 0) {
                return round(($this->{$valueColumn} / $totalAmount) * 100, 2);
            }
        }

        // Calculate from stored amount
        if ($totalAmount > 0) {
            return round(($this->{$amountColumn} / $totalAmount) * 100, 2);
        }

        return 0;
    }
}
