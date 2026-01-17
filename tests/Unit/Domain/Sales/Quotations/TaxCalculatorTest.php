<?php

declare(strict_types=1);

use App\Domain\Sales\Quotations\TaxCalculator;
use App\Models\Sales\QuotationItem;

describe('TaxCalculator', function () {

    it('calculates tax from array of items', function () {
        $items = [
            ['line_total' => 1000000],
            ['line_total' => 2000000],
        ];

        $result = TaxCalculator::calculateFromItems($items);

        expect($result)->toBe(330000); // 3,000,000 * 11% = 330,000
    });

    it('calculates tax from QuotationItem models', function () {
        $item1 = new class(['line_total' => 1000000]) extends QuotationItem {};
        $item2 = new class(['line_total' => 2000000]) extends QuotationItem {};

        $result = TaxCalculator::calculateFromItems([$item1, $item2]);

        expect($result)->toBe(330000);
    });

    it('calculates tax from subtotal directly', function () {
        $result = TaxCalculator::calculateFromSubtotal(5000000, 11);

        expect($result)->toBe(550000); // 5,000,000 * 11% = 550,000
    });

    it('handles zero tax rate', function () {
        $result = TaxCalculator::calculateFromSubtotal(1000000, 0);

        expect($result)->toBe(0);
    });

    it('handles different tax rates correctly', function () {
        $items = [['line_total' => 1000000]];

        expect(TaxCalculator::calculateFromItems($items, 5))->toBe(50000);
        expect(TaxCalculator::calculateFromItems($items, 10))->toBe(100000);
        expect(TaxCalculator::calculateFromItems($items, 15))->toBe(150000);
    });
});
