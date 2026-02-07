<?php

declare(strict_types=1);

namespace App\Contracts\Tax;

use App\Domain\Tax\ValueObjects\PphCalculationResult;
use App\Enums\PphCategory;
use App\Models\Purchasing\Bill;

interface PphCalculationServiceInterface
{
    /**
     * Calculate PPh withholding for a bill payment.
     *
     * @param  int  $paymentAmount  The total payment amount (PPh will be withheld from this)
     * @return PphCalculationResult|null Null if PPh is disabled or not applicable
     */
    public function calculateForBillPayment(
        Bill $bill,
        int $paymentAmount,
        ?PphCategory $categoryOverride = null,
        ?float $rateOverride = null
    ): ?PphCalculationResult;
}
