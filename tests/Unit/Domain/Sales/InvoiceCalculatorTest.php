<?php

declare(strict_types=1);

use App\Domain\Sales\Invoices\InvoiceCalculator;
use App\Domain\Sales\Invoices\InvoiceTotals;

beforeEach(function () {
    $this->calculator = new InvoiceCalculator;
});

describe('InvoiceCalculator - tax rounding', function () {

    it('calculates tax per-line with rounding (PMK-151 compliant)', function () {
        // Two items at 99,999 each with 11% tax
        // Per-line: round(99999 * 0.11) = round(10999.89) = 11000 each = 22000 total
        // Per-document: round(199998 * 0.11) = round(21999.78) = 22000
        // Same result in this case, but tests the per-line mechanism
        $lineTotals = [99999, 99999];

        $totals = $this->calculator->calculate($lineTotals, 11.0, 0, 'IDR', 1.0);

        expect($totals)
            ->toBeInstanceOf(InvoiceTotals::class)
            ->subtotal->toBe(199998)
            ->taxAmount->toBe(22000)
            ->totalAmount->toBe(221998);
    });

    it('produces different result from per-document rounding with odd amounts', function () {
        // Three items with amounts that produce rounding differences
        // Line 1: 100,003 → tax = round(100003 * 0.11) = round(11000.33) = 11000
        // Line 2: 100,003 → tax = round(100003 * 0.11) = round(11000.33) = 11000
        // Line 3: 100,003 → tax = round(100003 * 0.11) = round(11000.33) = 11000
        // Per-line total: 33000
        //
        // Per-document would be: round(300009 * 0.11) = round(33000.99) = 33001
        // Difference: 1 IDR
        $lineTotals = [100003, 100003, 100003];

        $totals = $this->calculator->calculate($lineTotals, 11.0, 0, 'IDR', 1.0);

        // Per-line rounding gives 33000, not 33001
        expect($totals->taxAmount)->toBe(33000);
    });

    it('handles single line item correctly', function () {
        $lineTotals = [500000];

        $totals = $this->calculator->calculate($lineTotals, 11.0, 0, 'IDR', 1.0);

        expect($totals)
            ->subtotal->toBe(500000)
            ->taxAmount->toBe(55000)
            ->totalAmount->toBe(555000);
    });

    it('handles zero tax rate', function () {
        $lineTotals = [100000, 200000];

        $totals = $this->calculator->calculate($lineTotals, 0.0, 0, 'IDR', 1.0);

        expect($totals)
            ->subtotal->toBe(300000)
            ->taxAmount->toBe(0)
            ->totalAmount->toBe(300000);
    });

    it('handles empty line totals', function () {
        $totals = $this->calculator->calculate([], 11.0, 0, 'IDR', 1.0);

        expect($totals)
            ->subtotal->toBe(0)
            ->taxAmount->toBe(0)
            ->totalAmount->toBe(0);
    });

});

describe('InvoiceCalculator - discounts', function () {

    it('subtracts discount from total', function () {
        $lineTotals = [500000];

        $totals = $this->calculator->calculate($lineTotals, 11.0, 50000, 'IDR', 1.0);

        expect($totals)
            ->subtotal->toBe(500000)
            ->taxAmount->toBe(55000)
            ->totalAmount->toBe(505000); // 500000 + 55000 - 50000
    });

    it('floors total at zero when discount exceeds subtotal plus tax', function () {
        $lineTotals = [10000];

        $totals = $this->calculator->calculate($lineTotals, 11.0, 999999, 'IDR', 1.0);

        expect($totals->totalAmount)->toBe(0);
    });

});

describe('InvoiceCalculator - multi-currency', function () {

    it('calculates totals in transaction currency', function () {
        // USD invoice: $1000 item
        $lineTotals = [1000];

        $totals = $this->calculator->calculate($lineTotals, 11.0, 0, 'USD', 15500.0);

        expect($totals)
            ->subtotal->toBe(1000)
            ->taxAmount->toBe(110)
            ->totalAmount->toBe(1110);
    });

});

describe('InvoiceCalculator - per-line rounding with many items', function () {

    it('accumulates per-line rounding correctly for 10 items', function () {
        // 10 items at 90,909 each (11% tax = round(9999.99) = 10000 per line)
        $lineTotals = array_fill(0, 10, 90909);

        $totals = $this->calculator->calculate($lineTotals, 11.0, 0, 'IDR', 1.0);

        // Per-line: 10 * round(90909 * 0.11) = 10 * round(9999.99) = 10 * 10000 = 100000
        // Per-document would be: round(909090 * 0.11) = round(99999.9) = 100000
        expect($totals)
            ->subtotal->toBe(909090)
            ->taxAmount->toBe(100000);
    });

    it('shows rounding difference with 7 items at odd amounts', function () {
        // 7 items at 142,857 each
        // Per-line: 7 * round(142857 * 0.11) = 7 * round(15714.27) = 7 * 15714 = 109998
        // Per-document: round(999999 * 0.11) = round(109999.89) = 110000
        // Difference: 2 IDR
        $lineTotals = array_fill(0, 7, 142857);

        $totals = $this->calculator->calculate($lineTotals, 11.0, 0, 'IDR', 1.0);

        expect($totals->taxAmount)->toBe(109998);
    });

});
