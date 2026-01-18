<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

readonly class Money
{
    public function __construct(
        public readonly int $amount,
        public readonly string $currency
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative');
        }

        if (empty($currency)) {
            throw new InvalidArgumentException('Currency cannot be empty');
        }
    }

    public static function of(int $amount, string $currency = 'IDR'): self
    {
        return new self($amount, $currency);
    }

    public static function fromFloat(float $amount, string $currency = 'IDR'): self
    {
        return new self((int) round($amount), $currency);
    }

    public function add(Money $other): self
    {
        $this->validateCurrency($other);

        return new self(
            $this->amount + $other->amount,
            $this->currency
        );
    }

    public function subtract(Money $other): self
    {
        $this->validateCurrency($other);

        $result = $this->amount - $other->amount;

        if ($result < 0) {
            throw new InvalidArgumentException(
                "Cannot subtract {$other->amount} from {$this->amount}: result would be negative"
            );
        }

        return new self($result, $this->currency);
    }

    public function multiply(float $factor): self
    {
        if ($factor < 0) {
            throw new InvalidArgumentException('Multiplication factor cannot be negative');
        }

        return new self(
            (int) round($this->amount * $factor),
            $this->currency
        );
    }

    public function divide(float $divisor): self
    {
        if ($divisor <= 0) {
            throw new InvalidArgumentException('Divisor must be greater than zero');
        }

        return new self(
            (int) round($this->amount / $divisor),
            $this->currency
        );
    }

    public function percentage(float $percent): self
    {
        return $this->multiply($percent / 100);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function greaterThan(Money $other): bool
    {
        $this->validateCurrency($other);

        return $this->amount > $other->amount;
    }

    public function greaterThanOrEqual(Money $other): bool
    {
        $this->validateCurrency($other);

        return $this->amount >= $other->amount;
    }

    public function lessThan(Money $other): bool
    {
        $this->validateCurrency($other);

        return $this->amount < $other->amount;
    }

    public function lessThanOrEqual(Money $other): bool
    {
        $this->validateCurrency($other);

        return $this->amount <= $other->amount;
    }

    public function equals(Money $other): bool
    {
        return $this->currency === $other->currency
            && $this->amount === $other->amount;
    }

    public function isIDR(): bool
    {
        return $this->currency === 'IDR';
    }

    public function toIDR(float $exchangeRate): self
    {
        if ($this->isIDR()) {
            return $this;
        }

        if ($exchangeRate <= 0) {
            throw new InvalidArgumentException('Exchange rate must be greater than zero');
        }

        return new self(
            (int) round($this->amount * $exchangeRate),
            'IDR'
        );
    }

    public function toCurrency(float $exchangeRate, string $targetCurrency): self
    {
        if ($targetCurrency === $this->currency) {
            return $this;
        }

        if ($exchangeRate <= 0) {
            throw new InvalidArgumentException('Exchange rate must be greater than zero');
        }

        if ($targetCurrency === 'IDR' && ! $this->isIDR()) {
            return $this->toIDR($exchangeRate);
        }

        return new self(
            (int) round($this->amount / $exchangeRate),
            $targetCurrency
        );
    }

    public function format(): string
    {
        if ($this->isIDR()) {
            return 'Rp '.number_format($this->amount, 0, ',', '.');
        }

        return $this->currency.' '.number_format($this->amount, 2, '.', ',');
    }

    private function validateCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot perform operation on different currencies: {$this->currency} and {$other->currency}"
            );
        }
    }
}
