<?php

declare(strict_types=1);

use App\Domain\Accounting\Tax\TaxExclusiveStrategy;
use App\Domain\Accounting\Tax\TaxInclusiveStrategy;
use App\Domain\Shared\ValueObjects\Money;

describe('TaxExclusiveStrategy', function () {
    beforeEach(function () {
        $this->strategy = new TaxExclusiveStrategy;
    });

    it('calculates tax by multiplying amount by rate', function () {
        $amount = Money::of(100000, 'IDR');

        $tax = $this->strategy->calculate($amount, 11);

        expect($tax->amount)->toBe(11000)
            ->and($tax->currency)->toBe('IDR');
    });

    it('handles zero rate', function () {
        $amount = Money::of(100000, 'IDR');

        $tax = $this->strategy->calculate($amount, 0);

        expect($tax->amount)->toBe(0);
    });

    it('returns original amount as base amount', function () {
        $amount = Money::of(100000, 'IDR');

        $base = $this->strategy->getBaseAmount($amount, 11);

        expect($base->amount)->toBe(100000);
    });

    it('is not tax inclusive', function () {
        expect($this->strategy->isTaxInclusive())->toBeFalse();
    });

    it('has correct name', function () {
        expect($this->strategy->getName())->toBe('exclusive');
    });
});

describe('TaxInclusiveStrategy', function () {
    beforeEach(function () {
        $this->strategy = new TaxInclusiveStrategy;
    });

    it('calculates tax by extracting from total', function () {
        // Total 111,000 at 11% = Tax 11,000, Base 100,000
        $totalAmount = Money::of(111000, 'IDR');

        $tax = $this->strategy->calculate($totalAmount, 11);

        // 111000 / 1.11 = 100000 (base), 111000 - 100000 = 11000 (tax)
        expect($tax->amount)->toBe(11000);
    });

    it('extracts base amount correctly', function () {
        // Total 111,000 at 11% should give base 100,000
        $totalAmount = Money::of(111000, 'IDR');

        $base = $this->strategy->getBaseAmount($totalAmount, 11);

        expect($base->amount)->toBe(100000);
    });

    it('handles round numbers', function () {
        $totalAmount = Money::of(1110000, 'IDR');

        $tax = $this->strategy->calculate($totalAmount, 11);
        $base = $this->strategy->getBaseAmount($totalAmount, 11);

        expect($base->amount)->toBe(1000000)
            ->and($tax->amount)->toBe(110000);
    });

    it('is tax inclusive', function () {
        expect($this->strategy->isTaxInclusive())->toBeTrue();
    });

    it('has correct name', function () {
        expect($this->strategy->getName())->toBe('inclusive');
    });
});
