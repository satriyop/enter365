<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

readonly class CurrencyCode
{
    private const VALID_CODES = [
        'IDR' => 'Indonesian Rupiah',
        'USD' => 'US Dollar',
        'EUR' => 'Euro',
        'GBP' => 'British Pound',
        'JPY' => 'Japanese Yen',
        'CNY' => 'Chinese Yuan',
        'SGD' => 'Singapore Dollar',
        'MYR' => 'Malaysian Ringgit',
        'THB' => 'Thai Baht',
        'AUD' => 'Australian Dollar',
        'CAD' => 'Canadian Dollar',
        'CHF' => 'Swiss Franc',
        'HKD' => 'Hong Kong Dollar',
        'NZD' => 'New Zealand Dollar',
        'KRW' => 'South Korean Won',
        'INR' => 'Indian Rupee',
        'PHP' => 'Philippine Peso',
        'VND' => 'Vietnamese Dong',
    ];

    public function __construct(
        public readonly string $code
    ) {
        $normalizedCode = strtoupper(trim($code));

        if (! isset(self::VALID_CODES[$normalizedCode])) {
            throw new InvalidArgumentException(
                "Invalid currency code: {$code}. Valid codes are: ".
                implode(', ', array_keys(self::VALID_CODES))
            );
        }

        if ($normalizedCode !== $code) {
            throw new InvalidArgumentException(
                "Currency code must be uppercase. Got: '{$code}', expected: '{$normalizedCode}'"
            );
        }
    }

    public static function IDR(): self
    {
        return new self('IDR');
    }

    public static function USD(): self
    {
        return new self('USD');
    }

    public static function EUR(): self
    {
        return new self('EUR');
    }

    public static function fromString(string $code): self
    {
        return new self(strtoupper(trim($code)));
    }

    public static function isValid(string $code): bool
    {
        return isset(self::VALID_CODES[strtoupper(trim($code))]);
    }

    public function getName(): string
    {
        return self::VALID_CODES[$this->code];
    }

    public function isIDR(): bool
    {
        return $this->code === 'IDR';
    }

    public function isUSD(): bool
    {
        return $this->code === 'USD';
    }

    public function isEUR(): bool
    {
        return $this->code === 'EUR';
    }

    public function isSameAs(CurrencyCode $other): bool
    {
        return $this->code === $other->code;
    }

    public function isSupported(): bool
    {
        return true;
    }

    public function getSymbol(): string
    {
        return match ($this->code) {
            'IDR' => 'Rp',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CNY' => '¥',
            default => $this->code,
        };
    }

    public function formatMoney(int $amount): string
    {
        $symbol = $this->getSymbol();

        if ($this->isIDR()) {
            return $symbol.' '.number_format($amount, 0, ',', '.');
        }

        return $symbol.' '.number_format($amount / 100, 2, '.', ',');
    }

    public function convertTo(float $amount, CurrencyCode $target, float $exchangeRate): int
    {
        if ($this->code === $target->code) {
            return (int) round($amount);
        }

        if ($exchangeRate <= 0) {
            throw new InvalidArgumentException('Exchange rate must be greater than zero');
        }

        $converted = $amount * $exchangeRate;

        return (int) round($converted);
    }

    public function toIDR(float $exchangeRate): self
    {
        if ($this->isIDR()) {
            return $this;
        }

        if ($exchangeRate <= 0) {
            throw new InvalidArgumentException('Exchange rate must be greater than zero');
        }

        return new self('IDR');
    }

    public static function getSupportedCodes(): array
    {
        return self::VALID_CODES;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
