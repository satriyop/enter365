<?php

use App\Domain\Shared\ValueObjects\Quantity;

describe('Quantity Value Object', function () {

    it('can create quantity with positive value', function () {
        $quantity = Quantity::of(10.5, 'pcs');

        expect($quantity->value)->toBe(10.5);
        expect($quantity->unit)->toBe('pcs');
    });

    it('cannot create quantity with negative value', function () {
        expect(fn () => Quantity::of(-5, 'pcs'))
            ->toThrow(InvalidArgumentException::class, 'cannot be negative');
    });

    it('cannot create quantity with empty unit', function () {
        expect(fn () => Quantity::of(10, ''))
            ->toThrow(InvalidArgumentException::class, 'cannot be empty');
    });

    it('cannot create quantity with unit exceeding 20 characters', function () {
        expect(fn () => Quantity::of(10, 'this_is_a_very_long_unit_name'))
            ->toThrow(InvalidArgumentException::class, 'exceed 20 characters');
    });

    it('can add quantities with same unit', function () {
        $qty1 = Quantity::of(10, 'pcs');
        $qty2 = Quantity::of(5, 'pcs');

        $result = $qty1->add($qty2);

        expect($result->value)->toBe(15.0);
        expect($result->unit)->toBe('pcs');
    });

    it('cannot add quantities with different units', function () {
        $qty1 = Quantity::of(10, 'pcs');
        $qty2 = Quantity::of(5, 'box');

        expect(fn () => $qty1->add($qty2))
            ->toThrow(InvalidArgumentException::class, 'different units');
    });

    it('can subtract quantities with same unit', function () {
        $qty1 = Quantity::of(10, 'pcs');
        $qty2 = Quantity::of(3, 'pcs');

        $result = $qty1->subtract($qty2);

        expect($result->value)->toBe(7.0);
    });

    it('cannot subtract resulting in negative', function () {
        $qty1 = Quantity::of(3, 'pcs');
        $qty2 = Quantity::of(5, 'pcs');

        expect(fn () => $qty1->subtract($qty2))
            ->toThrow(InvalidArgumentException::class, 'result would be negative');
    });

    it('can multiply quantity by positive factor', function () {
        $qty = Quantity::of(10, 'pcs');

        $result = $qty->multiply(2.5);

        expect($result->value)->toBe(25.0);
    });

    it('cannot multiply by negative factor', function () {
        $qty = Quantity::of(10, 'pcs');

        expect(fn () => $qty->multiply(-1))
            ->toThrow(InvalidArgumentException::class, 'cannot be negative');
    });

    it('can divide quantity', function () {
        $qty = Quantity::of(100, 'pcs');

        $result = $qty->divide(4);

        expect($result->value)->toBe(25.0);
    });

    it('cannot divide by zero', function () {
        $qty = Quantity::of(100, 'pcs');

        expect(fn () => $qty->divide(0))
            ->toThrow(InvalidArgumentException::class, 'greater than zero');
    });

    it('can check if zero', function () {
        $zeroQty = Quantity::of(0, 'pcs');
        $positiveQty = Quantity::of(10, 'pcs');

        expect($zeroQty->isZero())->toBeTrue();
        expect($positiveQty->isZero())->toBeFalse();
    });

    it('can check if whole number', function () {
        $wholeQty = Quantity::of(10, 'pcs');
        $decimalQty = Quantity::of(10.5, 'pcs');

        expect($wholeQty->isWholeNumber())->toBeTrue();
        expect($decimalQty->isWholeNumber())->toBeFalse();
    });

    it('can compare with greater than', function () {
        $qty1 = Quantity::of(10, 'pcs');
        $qty2 = Quantity::of(5, 'pcs');

        expect($qty1->greaterThan($qty2))->toBeTrue();
        expect($qty2->greaterThan($qty1))->toBeFalse();
    });

    it('can check equality with tolerance', function () {
        $qty1 = Quantity::of(10.0001, 'pcs');
        $qty2 = Quantity::of(10.0000, 'pcs');

        expect($qty1->equals($qty2))->toBeTrue();
    });

    it('can check same unit', function () {
        $qty1 = Quantity::of(10, 'pcs');
        $qty2 = Quantity::of(5, 'pcs');

        expect($qty1->isSameUnit($qty2))->toBeTrue();
    });

    it('can convert to different unit', function () {
        $qty = Quantity::of(1, 'kg');

        $result = $qty->toUnit('g', 1000);

        expect($result->value)->toBe(1000.0);
        expect($result->unit)->toBe('g');
    });

    it('cannot convert with invalid factor', function () {
        $qty = Quantity::of(1, 'kg');

        expect(fn () => $qty->toUnit('g', 0))
            ->toThrow(InvalidArgumentException::class, 'greater than zero');
    });

    it('can format quantity', function () {
        $qty = Quantity::of(10.5, 'pcs');

        $formatted = $qty->format();

        expect($formatted)->toBe('10,5000 pcs');
    });

    it('can convert to integer when whole number', function () {
        $qty = Quantity::of(10, 'pcs');

        expect($qty->toInteger())->toBe(10);
    });

    it('cannot convert to integer when decimal', function () {
        $qty = Quantity::of(10.5, 'pcs');

        expect(fn () => $qty->toInteger())
            ->toThrow(InvalidArgumentException::class, 'non-whole quantity');
    });

    it('can create from integer', function () {
        $qty = Quantity::fromInteger(10, 'pcs');

        expect($qty->value)->toBe(10.0);
        expect($qty->isWholeNumber())->toBeTrue();
    });
});
