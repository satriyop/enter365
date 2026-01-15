<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Interface for financial calculation operations.
 *
 * Used by services that handle documents with monetary values:
 * Quotations, Invoices, Bills, PurchaseOrders, etc.
 */
interface FinancialCalculationInterface
{
    /**
     * Calculate and update document totals.
     *
     * Typically calculates: subtotal, discount_amount, tax_amount, total
     */
    public function calculateTotals(Model $document): void;

    /**
     * Apply discount to a document.
     *
     * @param  string  $type  'percentage' or 'fixed'
     * @param  float  $value  Discount value
     */
    public function applyDiscount(Model $document, string $type, float $value): Model;
}
