<?php

declare(strict_types=1);

use App\Domain\Sales\Quotations\DiscountCalculator;

describe('DiscountCalculator', function () {

    it('calculates percentage discount correctly', function () {
        $calculator = new DiscountCalculator(DiscountCalculator::TYPE_PERCENTAGE, 10.0);

        $result = $calculator->calculate(1000000);

        expect($result)->toBe(100000); // 10% of 1,000,000
    });

    it('calculates fixed discount correctly', function () {
        $calculator = new DiscountCalculator(DiscountCalculator::TYPE_FIXED, 150000);

        $result = $calculator->calculate(1000000);

        expect($result)->toBe(150000);
    });

    it('returns zero when no discount type', function () {
        $calculator = new DiscountCalculator(null, 0);

        $result = $calculator->calculate(1000000);

        expect($result)->toBe(0);
    });

    it('returns zero when discount value is zero', function () {
        $calculator = new DiscountCalculator(DiscountCalculator::TYPE_PERCENTAGE, 0);

        $result = $calculator->calculate(1000000);

        expect($result)->toBe(0);
    });

    it('handles 100% discount', function () {
        $calculator = new DiscountCalculator(DiscountCalculator::TYPE_PERCENTAGE, 100.0);

        $result = $calculator->calculate(1000000);

        expect($result)->toBe(1000000);
    });

    it('handles percentage discount with decimal values', function () {
        $calculator = new DiscountCalculator(DiscountCalculator::TYPE_PERCENTAGE, 5.5);

        $result = $calculator->calculate(1000000);

        expect($result)->toBe(55000); // 5.5% of 1,000,000
    });
});
