<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

readonly class Percentage
{
    public function __construct(
        public readonly float $value
    ) {
        if ($value < 0) {
            throw new InvalidArgumentException('Percentage value cannot be negative');
        }

        if ($value > 100) {
            throw new InvalidArgumentException('Percentage value cannot exceed 100');
        }
    }

    public static function of(float $value): self
    {
        return new self($value);
    }

    public static function fromDecimal(float $decimal): self
    {
        return new self($decimal * 100);
    }

    public static function zero(): self
    {
        return new self(0.0);
    }

    public static function ten(): self
    {
        return new self(10.0);
    }

    public static function eleven(): self
    {
        return new self(11.0);
    }

    public function toDecimal(): float
    {
        return $this->value / 100;
    }

    public function calculate(int $amount): int
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative');
        }

        return (int) round($amount * $this->toDecimal());
    }

    public function calculateMoney(int $amount): Money
    {
        return Money::of($this->calculate($amount));
    }

    public function add(Percentage $other): self
    {
        $result = $this->value + $other->value;

        if ($result > 100) {
            throw new InvalidArgumentException(
                "Sum of percentages ({$result}%) cannot exceed 100%"
            );
        }

        return new self($result);
    }

    public function subtract(Percentage $other): self
    {
        $result = $this->value - $other->value;

        if ($result < 0) {
            throw new InvalidArgumentException(
                "Difference of percentages ({$result}%) cannot be negative"
            );
        }

        return new self($result);
    }

    public function isZero(): bool
    {
        return $this->value === 0.0;
    }

    public function isSameAs(Percentage $other): bool
    {
        return abs($this->value - $other->value) < 0.001;
    }

    public function isGreaterThan(Percentage $other): bool
    {
        return $this->value > $other->value;
    }

    public function isLessThan(Percentage $other): bool
    {
        return $this->value < $other->value;
    }

    public function format(): string
    {
        return number_format($this->value, 2, ',', '.').'%';
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
