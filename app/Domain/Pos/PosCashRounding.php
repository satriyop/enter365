<?php

declare(strict_types=1);

namespace App\Domain\Pos;

/**
 * Cash-only nearest-unit rounding so the drawer never asks for coins below the till denoms.
 * QRIS stays exact. Bill (payable) is unchanged; cashDue is what Kas receives.
 */
readonly class PosCashRounding
{
    public function __construct(
        public int $payable,
        public int $cashDue,
        public int $roundingAmount,
    ) {
        if ($this->payable < 0) {
            throw new \InvalidArgumentException('Payable cannot be negative.');
        }

        if ($this->cashDue < 0) {
            throw new \InvalidArgumentException('Cash due cannot be negative.');
        }

        if ($this->cashDue - $this->payable !== $this->roundingAmount) {
            throw new \InvalidArgumentException('Rounding amount must equal cash due minus payable.');
        }
    }

    public static function none(int $payable): self
    {
        return new self($payable, $payable, 0);
    }

    public static function nearest(int $payable, int $unit = 100): self
    {
        if ($payable < 0) {
            throw new \InvalidArgumentException('Payable cannot be negative.');
        }

        if ($unit < 1) {
            return self::none($payable);
        }

        $remainder = $payable % $unit;
        if ($remainder === 0) {
            return self::none($payable);
        }

        $cashDue = $remainder >= intdiv($unit, 2)
            ? $payable - $remainder + $unit
            : $payable - $remainder;

        return new self($payable, $cashDue, $cashDue - $payable);
    }
}
