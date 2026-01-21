<?php

use App\Domain\Shared\ValueObjects\DateRange;
use Carbon\CarbonImmutable;

describe('DateRange Value Object', function () {

    it('creates date range from start and end dates', function () {
        $start = CarbonImmutable::parse('2025-01-01');
        $end = CarbonImmutable::parse('2025-01-31');

        $range = new DateRange($start, $end);

        expect($range->start->toDateString())->toBe('2025-01-01');
        expect($range->end->toDateString())->toBe('2025-01-31');
    });

    it('throws exception when end is before start', function () {
        $start = CarbonImmutable::parse('2025-01-31');
        $end = CarbonImmutable::parse('2025-01-01');

        expect(fn () => new DateRange($start, $end))
            ->toThrow(InvalidArgumentException::class, 'End date cannot be before start date');
    });

    it('creates this month range', function () {
        $range = DateRange::thisMonth();

        expect($range->start->day)->toBe(1);
        expect($range->end->month)->toBe($range->start->month);
    });

    it('creates this year range', function () {
        $range = DateRange::thisYear();

        expect($range->start->month)->toBe(1);
        expect($range->start->day)->toBe(1);
        expect($range->end->month)->toBe(12);
        expect($range->end->day)->toBe(31);
    });

    it('creates last N days range', function () {
        $range = DateRange::lastDays(7);

        expect($range->days())->toBe(7);
    });

    it('creates for specific month', function () {
        $range = DateRange::forMonth(2025, 2);

        expect($range->start->toDateString())->toBe('2025-02-01');
        expect($range->end->toDateString())->toBe('2025-02-28');
    });

    it('correctly checks if date is contained', function () {
        $range = new DateRange(
            CarbonImmutable::parse('2025-01-01'),
            CarbonImmutable::parse('2025-01-31')
        );

        expect($range->contains(CarbonImmutable::parse('2025-01-15')))->toBeTrue();
        expect($range->contains(CarbonImmutable::parse('2025-02-15')))->toBeFalse();
        expect($range->contains('2025-01-01'))->toBeTrue();
        expect($range->contains('2025-01-31'))->toBeTrue();
    });

    it('correctly checks if ranges overlap', function () {
        $range1 = new DateRange(
            CarbonImmutable::parse('2025-01-01'),
            CarbonImmutable::parse('2025-01-31')
        );

        $range2 = new DateRange(
            CarbonImmutable::parse('2025-01-15'),
            CarbonImmutable::parse('2025-02-15')
        );

        $range3 = new DateRange(
            CarbonImmutable::parse('2025-02-01'),
            CarbonImmutable::parse('2025-02-28')
        );

        expect($range1->overlaps($range2))->toBeTrue();
        expect($range1->overlaps($range3))->toBeFalse();
    });

    it('calculates days correctly', function () {
        $range = new DateRange(
            CarbonImmutable::parse('2025-01-01'),
            CarbonImmutable::parse('2025-01-07')
        );

        expect($range->days())->toBe(7);
    });

    it('serializes to JSON', function () {
        $range = new DateRange(
            CarbonImmutable::parse('2025-01-01'),
            CarbonImmutable::parse('2025-01-31')
        );

        $json = json_encode($range);
        $decoded = json_decode($json, true);

        expect($decoded['start'])->toBe('2025-01-01');
        expect($decoded['end'])->toBe('2025-01-31');
    });

    it('converts to string', function () {
        $range = new DateRange(
            CarbonImmutable::parse('2025-01-01'),
            CarbonImmutable::parse('2025-01-31')
        );

        expect((string) $range)->toBe('2025-01-01 to 2025-01-31');
    });
});
