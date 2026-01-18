<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

readonly class Quantity
{
    public function __construct(
        public readonly float $value,
        public readonly string $unit
    ) {
        if ($value < 0) {
            throw new InvalidArgumentException('Quantity value cannot be negative');
        }

        if (empty($unit)) {
            throw new InvalidArgumentException('Unit cannot be empty');
        }

        if (strlen($unit) > 20) {
            throw new InvalidArgumentException('Unit cannot exceed 20 characters');
        }
    }

    public static function of(float $value, string $unit = 'unit'): self
    {
        return new self($value, $unit);
    }

    public static function fromInteger(int $value, string $unit = 'unit'): self
    {
        return new self((float) $value, $unit);
    }

    public function add(Quantity $other): self
    {
        $this->validateUnit($other);

        return new self(
            $this->value + $other->value,
            $this->unit
        );
    }

    public function subtract(Quantity $other): self
    {
        $this->validateUnit($other);

        $result = $this->value - $other->value;

        if ($result < 0) {
            throw new InvalidArgumentException(
                "Cannot subtract {$other->value} from {$this->value}: result would be negative"
            );
        }

        return new self($result, $this->unit);
    }

    public function multiply(float $factor): self
    {
        if ($factor < 0) {
            throw new InvalidArgumentException('Multiplication factor cannot be negative');
        }

        return new self(
            $this->value * $factor,
            $this->unit
        );
    }

    public function divide(float $divisor): self
    {
        if ($divisor <= 0) {
            throw new InvalidArgumentException('Divisor must be greater than zero');
        }

        $result = $this->value / $divisor;

        return new self($result, $this->unit);
    }

    public function isZero(): bool
    {
        return $this->value === 0.0;
    }

    public function isWholeNumber(): bool
    {
        return $this->value === floor($this->value);
    }

    public function greaterThan(Quantity $other): bool
    {
        $this->validateUnit($other);

        return $this->value > $other->value;
    }

    public function greaterThanOrEqual(Quantity $other): bool
    {
        $this->validateUnit($other);

        return $this->value >= $other->value;
    }

    public function lessThan(Quantity $other): bool
    {
        $this->validateUnit($other);

        return $this->value < $other->value;
    }

    public function lessThanOrEqual(Quantity $other): bool
    {
        $this->validateUnit($other);

        return $this->value <= $other->value;
    }

    public function equals(Quantity $other): bool
    {
        return $this->unit === $other->unit
            && abs($this->value - $other->value) < 0.0001;
    }

    public function isSameUnit(Quantity $other): bool
    {
        return $this->unit === $other->unit;
    }

    public function toUnit(string $targetUnit, float $conversionFactor = 1.0): self
    {
        if ($conversionFactor <= 0) {
            throw new InvalidArgumentException('Conversion factor must be greater than zero');
        }

        return new self(
            $this->value * $conversionFactor,
            $targetUnit
        );
    }

    public function format(): string
    {
        $formatted = $this->isWholeNumber()
            ? number_format($this->value, 0, ',', '.')
            : number_format($this->value, 4, ',', '.');

        return $formatted.' '.$this->unit;
    }

    public function toInteger(): int
    {
        if (! $this->isWholeNumber()) {
            throw new InvalidArgumentException(
                "Cannot convert non-whole quantity {$this->value} to integer"
            );
        }

        return (int) $this->value;
    }

    private function validateUnit(Quantity $other): void
    {
        if ($this->unit !== $other->unit) {
            throw new InvalidArgumentException(
                "Cannot perform operation on different units: {$this->unit} and {$other->unit}"
            );
        }
    }
}
