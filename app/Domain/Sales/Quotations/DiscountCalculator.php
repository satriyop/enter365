<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations;

readonly class DiscountCalculator
{
    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    public function __construct(
        public ?string $discountType,
        public float $discountValue,
    ) {}

    public function calculate(int $subtotal): int
    {
        if ($this->discountType === self::TYPE_PERCENTAGE && $this->discountValue > 0) {
            return (int) round($subtotal * ($this->discountValue / 100));
        }

        if ($this->discountType === self::TYPE_FIXED && $this->discountValue > 0) {
            return (int) round($this->discountValue);
        }

        return 0;
    }
}
