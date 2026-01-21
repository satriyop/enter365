<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Immutable DateRange value object.
 *
 * Represents a period of time between two dates.
 * Useful for reporting periods, fiscal periods, filtering, etc.
 */
readonly class DateRange implements JsonSerializable, Stringable
{
    public CarbonImmutable $start;

    public CarbonImmutable $end;

    public function __construct(
        CarbonImmutable|string $start,
        CarbonImmutable|string $end
    ) {
        $this->start = $start instanceof CarbonImmutable
            ? $start
            : CarbonImmutable::parse($start)->startOfDay();
        $this->end = $end instanceof CarbonImmutable
            ? $end
            : CarbonImmutable::parse($end)->endOfDay();

        if ($this->end->isBefore($this->start)) {
            throw new InvalidArgumentException('End date cannot be before start date.');
        }
    }

    /**
     * Create from start and end date strings or Carbon instances.
     */
    public static function of(CarbonImmutable|string $start, CarbonImmutable|string $end): self
    {
        return new self($start, $end);
    }

    /**
     * Create for this month.
     */
    public static function thisMonth(): self
    {
        return new self(
            CarbonImmutable::now()->startOfMonth(),
            CarbonImmutable::now()->endOfMonth()
        );
    }

    /**
     * Create for this year.
     */
    public static function thisYear(): self
    {
        return new self(
            CarbonImmutable::now()->startOfYear(),
            CarbonImmutable::now()->endOfYear()
        );
    }

    /**
     * Create for the last N days (including today).
     */
    public static function lastDays(int $days): self
    {
        return new self(
            CarbonImmutable::now()->subDays($days - 1)->startOfDay(),
            CarbonImmutable::now()->endOfDay()
        );
    }

    /**
     * Create for a specific month.
     */
    public static function forMonth(int $year, int $month): self
    {
        $date = CarbonImmutable::create($year, $month, 1);

        return new self(
            $date->startOfMonth(),
            $date->endOfMonth()
        );
    }

    /**
     * Create for a specific year.
     */
    public static function forYear(int $year): self
    {
        return new self(
            CarbonImmutable::create($year, 1, 1)->startOfYear(),
            CarbonImmutable::create($year, 12, 31)->endOfYear()
        );
    }

    /**
     * Check if a date falls within this range.
     */
    public function contains(CarbonImmutable|string $date): bool
    {
        $date = $date instanceof CarbonImmutable
            ? $date
            : CarbonImmutable::parse($date);

        return $date->between($this->start, $this->end);
    }

    /**
     * Check if this range overlaps with another.
     */
    public function overlaps(DateRange $other): bool
    {
        return $this->start->lte($other->end) && $this->end->gte($other->start);
    }

    /**
     * Check if this range is adjacent to (immediately before or after) another.
     */
    public function isAdjacentTo(DateRange $other): bool
    {
        return $this->end->addDay()->isSameDay($other->start)
            || $other->end->addDay()->isSameDay($this->start);
    }

    /**
     * Get the number of days in this range (inclusive).
     */
    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }

    /**
     * Get the number of months spanned (partial months count as 1).
     */
    public function months(): int
    {
        return (int) $this->start->diffInMonths($this->end) + 1;
    }

    /**
     * Check if this range spans a single day.
     */
    public function isSingleDay(): bool
    {
        return $this->start->isSameDay($this->end);
    }

    /**
     * Check if this range spans an entire month (first to last day).
     */
    public function isFullMonth(): bool
    {
        return $this->start->day === 1
            && $this->end->isSameDay($this->end->endOfMonth());
    }

    /**
     * Format for display (Indonesian locale).
     */
    public function format(string $format = 'd M Y'): string
    {
        if ($this->isSingleDay()) {
            return $this->start->format($format);
        }

        return $this->start->format($format).' - '.$this->end->format($format);
    }

    /**
     * Get as array of dates.
     *
     * @return array<CarbonImmutable>
     */
    public function toDates(): array
    {
        $dates = [];
        $current = $this->start;

        while ($current->lte($this->end)) {
            $dates[] = $current;
            $current = $current->addDay();
        }

        return $dates;
    }

    public function jsonSerialize(): array
    {
        return [
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
            'days' => $this->days(),
        ];
    }

    public function __toString(): string
    {
        return $this->start->toDateString().' to '.$this->end->toDateString();
    }
}
