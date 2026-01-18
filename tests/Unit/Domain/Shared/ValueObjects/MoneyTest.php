<?php

use App\Domain\Shared\ValueObjects\Money;

describe('Money Value Object', function () {

    it('can create money with positive amount', function () {
        $money = Money::of(1000000, 'IDR');

        expect($money->amount)->toBe(1000000);
        expect($money->currency)->toBe('IDR');
    });

    it('cannot create money with negative amount', function () {
        expect(fn () => Money::of(-100, 'IDR'))
            ->toThrow(InvalidArgumentException::class, 'Money amount cannot be negative');
    });

    it('cannot create money with empty currency', function () {
        expect(fn () => Money::of(100, ''))
            ->toThrow(InvalidArgumentException::class, 'Currency cannot be empty');
    });

    it('can add money with same currency', function () {
        $money1 = Money::of(100000, 'IDR');
        $money2 = Money::of(50000, 'IDR');

        $result = $money1->add($money2);

        expect($result->amount)->toBe(150000);
        expect($result->currency)->toBe('IDR');
    });

    it('cannot add money with different currency', function () {
        $money1 = Money::of(100000, 'IDR');
        $money2 = Money::of(100, 'USD');

        expect(fn () => $money1->add($money2))
            ->toThrow(InvalidArgumentException::class, 'different currencies');
    });

    it('can subtract money with same currency', function () {
        $money1 = Money::of(100000, 'IDR');
        $money2 = Money::of(30000, 'IDR');

        $result = $money1->subtract($money2);

        expect($result->amount)->toBe(70000);
        expect($result->currency)->toBe('IDR');
    });

    it('cannot subtract resulting in negative', function () {
        $money1 = Money::of(30000, 'IDR');
        $money2 = Money::of(50000, 'IDR');

        expect(fn () => $money1->subtract($money2))
            ->toThrow(InvalidArgumentException::class, 'result would be negative');
    });

    it('can multiply money by positive factor', function () {
        $money = Money::of(100000, 'IDR');

        $result = $money->multiply(2.5);

        expect($result->amount)->toBe(250000);
        expect($result->currency)->toBe('IDR');
    });

    it('cannot multiply by negative factor', function () {
        $money = Money::of(100000, 'IDR');

        expect(fn () => $money->multiply(-1))
            ->toThrow(InvalidArgumentException::class, 'cannot be negative');
    });

    it('can calculate percentage', function () {
        $money = Money::of(1000000, 'IDR');
        $percentage = 10.0;

        $result = $money->percentage($percentage);

        expect($result->amount)->toBe(100000);
    });

    it('can check if money is zero', function () {
        $zeroMoney = Money::of(0, 'IDR');
        $positiveMoney = Money::of(100, 'IDR');

        expect($zeroMoney->isZero())->toBeTrue();
        expect($positiveMoney->isZero())->toBeFalse();
    });

    it('can check if money is positive', function () {
        $zeroMoney = Money::of(0, 'IDR');
        $positiveMoney = Money::of(100, 'IDR');

        expect($zeroMoney->isPositive())->toBeFalse();
        expect($positiveMoney->isPositive())->toBeTrue();
    });

    it('can compare money with greater than', function () {
        $money1 = Money::of(100000, 'IDR');
        $money2 = Money::of(50000, 'IDR');

        expect($money1->greaterThan($money2))->toBeTrue();
        expect($money2->greaterThan($money1))->toBeFalse();
    });

    it('can compare money with less than', function () {
        $money1 = Money::of(50000, 'IDR');
        $money2 = Money::of(100000, 'IDR');

        expect($money1->lessThan($money2))->toBeTrue();
        expect($money2->lessThan($money1))->toBeFalse();
    });

    it('can check equality', function () {
        $money1 = Money::of(100000, 'IDR');
        $money2 = Money::of(100000, 'IDR');
        $money3 = Money::of(100000, 'USD');

        expect($money1->equals($money2))->toBeTrue();
        expect($money1->equals($money3))->toBeFalse();
    });

    it('can check if IDR currency', function () {
        $idrMoney = Money::of(100000, 'IDR');
        $usdMoney = Money::of(100, 'USD');

        expect($idrMoney->isIDR())->toBeTrue();
        expect($usdMoney->isIDR())->toBeFalse();
    });

    it('can format IDR money', function () {
        $money = Money::of(1500000, 'IDR');

        $formatted = $money->format();

        expect($formatted)->toBe('Rp 1.500.000');
    });

    it('can convert non-IDR to IDR', function () {
        $usdMoney = Money::of(100, 'USD');

        $idrMoney = $usdMoney->toIDR(15000);

        expect($idrMoney->currency)->toBe('IDR');
        expect($idrMoney->amount)->toBe(1500000);
    });

    it('cannot convert with invalid exchange rate', function () {
        $money = Money::of(100, 'USD');

        expect(fn () => $money->toIDR(0))
            ->toThrow(InvalidArgumentException::class, 'greater than zero');
    });

    it('can divide money', function () {
        $money = Money::of(100000, 'IDR');

        $result = $money->divide(4);

        expect($result->amount)->toBe(25000);
    });

    it('cannot divide by zero', function () {
        $money = Money::of(100000, 'IDR');

        expect(fn () => $money->divide(0))
            ->toThrow(InvalidArgumentException::class, 'greater than zero');
    });

    it('can create from float', function () {
        $money = Money::fromFloat(1000.50, 'IDR');

        expect($money->amount)->toBe(1001);
    });
});
