<?php

declare(strict_types=1);

use App\Domain\Sales\Quotations\DiscountCalculator;

describe('DiscountCalculator', function () {

    it('calculates percentage discount correctly', function () {
        $result = DiscountCalculator::calculate(DiscountCalculator::TYPE_PERCENTAGE, 10.0, 1000000);

        expect($result)->toBe(100000); // 10% of 1,000,000
    });

    it('calculates fixed discount correctly', function () {
        $result = DiscountCalculator::calculate(DiscountCalculator::TYPE_FIXED, 150000, 1000000);

        expect($result)->toBe(150000);
    });

    it('returns zero when no discount type', function () {
        $result = DiscountCalculator::calculate(null, 0, 1000000);

        expect($result)->toBe(0);
    });

    it('returns zero when discount value is zero', function () {
        $result = DiscountCalculator::calculate(DiscountCalculator::TYPE_PERCENTAGE, 0, 1000000);

        expect($result)->toBe(0);
    });

    it('handles 100% discount', function () {
        $result = DiscountCalculator::calculate(DiscountCalculator::TYPE_PERCENTAGE, 100.0, 1000000);

        expect($result)->toBe(1000000);
    });

    it('handles percentage discount with decimal values', function () {
        $result = DiscountCalculator::calculate(DiscountCalculator::TYPE_PERCENTAGE, 5.5, 1000000);

        expect($result)->toBe(55000); // 5.5% of 1,000,000
    });
});
