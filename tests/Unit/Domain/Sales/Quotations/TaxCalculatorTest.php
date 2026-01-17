<?php

declare(strict_types=1);

use App\Domain\Sales\Quotations\TaxCalculator;
use App\Models\Sales\QuotationItem;

describe('TaxCalculator', function () {

    it('calculates tax from array of items', function () {
        $calculator = new TaxCalculator(11);

        $items = [
            ['line_total' => 1000000],
            ['line_total' => 2000000],
        ];

        $result = $calculator->calculateFromItems($items);

        expect($result)->toBe(330000); // 3,000,000 * 11% = 330,000
    });

    it('calculates tax from QuotationItem models', function () {
        $calculator = new TaxCalculator(11);

        $item1 = new class(['line_total' => 1000000]) extends QuotationItem {};
        $item2 = new class(['line_total' => 2000000]) extends QuotationItem {};

        $result = $calculator->calculateFromItems([$item1, $item2]);

        expect($result)->toBe(330000);
    });

    it('calculates tax from subtotal directly', function () {
        $calculator = new TaxCalculator(11);

        $result = $calculator->calculateFromSubtotal(5000000);

        expect($result)->toBe(550000); // 5,000,000 * 11% = 550,000
    });

    it('handles zero tax rate', function () {
        $calculator = new TaxCalculator(0);

        $result = $calculator->calculateFromSubtotal(1000000);

        expect($result)->toBe(0);
    });

    it('handles different tax rates correctly', function () {
        $items = [['line_total' => 1000000]];

        expect((new TaxCalculator(5))->calculateFromItems($items))->toBe(50000);
        expect((new TaxCalculator(10))->calculateFromItems($items))->toBe(100000);
        expect((new TaxCalculator(15))->calculateFromItems($items))->toBe(150000);
    });
});
