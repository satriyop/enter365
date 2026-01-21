<?php

declare(strict_types=1);

namespace App\Contracts\Sales;

use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;

/**
 * Interface for quotation conversion operations.
 *
 * Handles converting approved quotations to invoices and other document types.
 */
interface QuotationConversionServiceInterface
{
    /**
     * Convert an approved quotation to an invoice.
     *
     * Creates a new draft invoice with items copied from the quotation.
     * The quotation status transitions to Converted and outcome is marked as Won.
     *
     * @throws \InvalidArgumentException If quotation cannot be converted
     */
    public function convertToInvoice(Quotation $quotation): Invoice;

    /**
     * Check if a quotation can be converted to invoice.
     *
     * Returns true if:
     * - Quotation status is Approved
     * - Quotation has not already been converted
     * - For multi-option quotations: a variant has been selected
     */
    public function canConvertToInvoice(Quotation $quotation): bool;

    /**
     * Get conversion status for a quotation.
     *
     * Provides detailed information about whether conversion is possible,
     * whether the quotation has already been converted, and if not
     * convertible, the reason why.
     *
     * @return array{can_convert: bool, converted: bool, converted_to: array|null, reason: string|null}
     */
    public function getConversionStatus(Quotation $quotation): array;
}
