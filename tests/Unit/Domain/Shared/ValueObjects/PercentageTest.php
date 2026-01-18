<?php

use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Shared\ValueObjects\Percentage;

describe('Percentage Value Object', function () {

    it('can create percentage with valid value', function () {
        $percentage = Percentage::of(10.5);

        expect($percentage->value)->toBe(10.5);
    });

    it('cannot create percentage with negative value', function () {
        expect(fn () => Percentage::of(-5))
            ->toThrow(InvalidArgumentException::class, 'cannot be negative');
    });

    it('cannot create percentage exceeding 100', function () {
        expect(fn () => Percentage::of(101))
            ->toThrow(InvalidArgumentException::class, 'cannot exceed 100');
    });

    it('can create from decimal', function () {
        $percentage = Percentage::fromDecimal(0.1);

        expect($percentage->value)->toBe(10.0);
    });

    it('can create zero percentage', function () {
        $percentage = Percentage::zero();

        expect($percentage->value)->toBe(0.0);
        expect($percentage->isZero())->toBeTrue();
    });

    it('can create predefined 10%', function () {
        $percentage = Percentage::ten();

        expect($percentage->value)->toBe(10.0);
    });

    it('can create predefined 11%', function () {
        $percentage = Percentage::eleven();

        expect($percentage->value)->toBe(11.0);
    });

    it('can convert to decimal', function () {
        $percentage = Percentage::of(25);

        expect($percentage->toDecimal())->toBe(0.25);
    });

    it('can calculate amount from percentage', function () {
        $percentage = Percentage::of(10);

        $result = $percentage->calculate(1000000);

        expect($result)->toBe(100000);
    });

    it('can calculate amount with rounding', function () {
        $percentage = Percentage::of(7.5);

        $result = $percentage->calculate(1000000);

        expect($result)->toBe(75000);
    });

    it('cannot calculate on negative amount', function () {
        $percentage = Percentage::of(10);

        expect(fn () => $percentage->calculate(-100))
            ->toThrow(InvalidArgumentException::class, 'cannot be negative');
    });

    it('can calculate money', function () {
        $percentage = Percentage::of(10);

        $money = $percentage->calculateMoney(1000000);

        expect($money)->toBeInstanceOf(Money::class);
        expect($money->amount)->toBe(100000);
    });

    it('can add percentages', function () {
        $p1 = Percentage::of(10);
        $p2 = Percentage::of(5);

        $result = $p1->add($p2);

        expect($result->value)->toBe(15.0);
    });

    it('cannot add percentages exceeding 100', function () {
        $p1 = Percentage::of(90);
        $p2 = Percentage::of(20);

        expect(fn () => $p1->add($p2))
            ->toThrow(InvalidArgumentException::class, 'cannot exceed 100%');
    });

    it('can subtract percentages', function () {
        $p1 = Percentage::of(10);
        $p2 = Percentage::of(3);

        $result = $p1->subtract($p2);

        expect($result->value)->toBe(7.0);
    });

    it('cannot subtract resulting in negative', function () {
        $p1 = Percentage::of(3);
        $p2 = Percentage::of(5);

        expect(fn () => $p1->subtract($p2))
            ->toThrow(InvalidArgumentException::class, 'cannot be negative');
    });

    it('can check if same as another percentage', function () {
        $p1 = Percentage::of(10.001);
        $p2 = Percentage::of(10.000);

        expect($p1->isSameAs($p2))->toBeTrue();
    });

    it('can check if greater than another', function () {
        $p1 = Percentage::of(20);
        $p2 = Percentage::of(10);

        expect($p1->isGreaterThan($p2))->toBeTrue();
        expect($p2->isGreaterThan($p1))->toBeFalse();
    });

    it('can check if less than another', function () {
        $p1 = Percentage::of(10);
        $p2 = Percentage::of(20);

        expect($p1->isLessThan($p2))->toBeTrue();
        expect($p2->isLessThan($p1))->toBeFalse();
    });

    it('can format percentage', function () {
        $percentage = Percentage::of(11.5);

        expect($percentage->format())->toBe('11,50%');
    });

    it('can convert to string', function () {
        $percentage = Percentage::of(10);

        expect((string) $percentage)->toBe('10,00%');
    });
});
