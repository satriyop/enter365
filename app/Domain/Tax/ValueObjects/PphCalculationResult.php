<?php

declare(strict_types=1);

namespace App\Domain\Tax\ValueObjects;

use App\Enums\PphCategory;

readonly class PphCalculationResult
{
    public function __construct(
        public PphCategory $category,
        public float $rate,
        public int $baseAmount,
        public int $pphAmount,
        public int $cashAmount,
        public string $accountCode,
        public bool $noNpwpSurcharge = false
    ) {}

    /**
     * The total payment amount = cash + pph.
     */
    public function totalAmount(): int
    {
        return $this->cashAmount + $this->pphAmount;
    }
}
