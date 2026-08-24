<?php

declare(strict_types=1);

namespace App\Domain\Pos;

/**
 * Moka "Add tax and gratuity" bill: menu subtotal, then service, then tax on (subtotal + service).
 */
readonly class PosAddOnBill
{
    public function __construct(
        public int $subtotal,
        public int $serviceAmount,
        public int $taxAmount,
        public int $payable,
    ) {}

    public static function of(int $subtotal, float $serviceRate, float $taxRate): self
    {
        if ($subtotal < 0) {
            throw new \InvalidArgumentException('Subtotal cannot be negative.');
        }

        $service = (int) round($subtotal * $serviceRate / 100);
        $tax = (int) round(($subtotal + $service) * $taxRate / 100);

        return new self($subtotal, $service, $tax, $subtotal + $service + $tax);
    }
}
