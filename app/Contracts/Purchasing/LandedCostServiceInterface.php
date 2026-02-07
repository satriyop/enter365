<?php

declare(strict_types=1);

namespace App\Contracts\Purchasing;

use App\Models\Purchasing\GoodsReceiptNote;
use App\Models\Purchasing\LandedCost;

/**
 * Landed Cost Service Interface.
 *
 * Distributes indirect purchasing costs (freight, insurance, customs)
 * across GRN items to adjust inventory cost. Creates JE:
 * Dr Inventory (1-1400) / Cr Expense account.
 */
interface LandedCostServiceInterface
{
    /**
     * Create a landed cost record (draft).
     *
     * @param  array{
     *     description: string,
     *     cost_type: string,
     *     total_amount: int,
     *     allocation_method?: string,
     *     expense_account_id: int,
     * } $data
     */
    public function create(GoodsReceiptNote $grn, array $data): LandedCost;

    /**
     * Apply the landed cost: allocate across GRN items, adjust cost layers, create JE.
     */
    public function apply(LandedCost $landedCost): LandedCost;

    /**
     * Reverse an applied landed cost: restore original costs, create reversal JE.
     */
    public function reverse(LandedCost $landedCost): LandedCost;
}
