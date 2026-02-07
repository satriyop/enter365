<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations;

class DiscountCalculator
{
    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    public static function calculate(
        ?string $discountType,
        float $discountValue,
        int $subtotal
    ): int {
        if ($discountType === self::TYPE_PERCENTAGE && $discountValue > 0) {
            return (int) round($subtotal * ($discountValue / 100));
        }

        if ($discountType === self::TYPE_FIXED && $discountValue > 0) {
            return min((int) round($discountValue), $subtotal);
        }

        return 0;
    }
}
