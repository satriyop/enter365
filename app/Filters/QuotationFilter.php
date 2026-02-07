<?php

declare(strict_types=1);

namespace App\Filters;

use App\Domain\Sales\Quotations\Enums\QuotationType;
use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;
use App\Models\Sales\Quotation;

/**
 * Filter for Quotation queries.
 *
 * Supported filters:
 * - status: Quotation status
 * - contact_id: Filter by contact/customer
 * - quotation_type: Single or multi-option
 * - start_date: Quotation date from
 * - end_date: Quotation date to
 * - expired_only: Only expired quotations
 * - active_only: Only active (not expired/converted/rejected)
 * - multi_option_only: Only multi-option quotations
 * - search: Search by number, subject, reference, or contact name
 */
class QuotationFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['quotation_number', 'subject', 'reference', 'contact.name'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateField(): string
    {
        return 'quotation_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSortField(): ?string
    {
        return 'quotation_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            'quotation_number',
            'quotation_date',
            'valid_until',
            'status',
            'total_amount',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedIncludes(): array
    {
        return [
            'contact',
            'items',
            'items.product',
            'variantGroup',
            'selectedVariant',
            'assignedTo',
            'revisions',
            'convertedInvoice',
            'activities',
            'variantOptions',
        ];
    }

    /**
     * Filter by contact/customer.
     */
    public function contactId(int|string $value): void
    {
        $this->builder->where('contact_id', $value);
    }

    /**
     * Filter by quotation type.
     */
    public function quotationType(string $value): void
    {
        $this->builder->where('quotation_type', $value);
    }

    /**
     * Filter only expired quotations.
     */
    public function expiredOnly(bool|string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->builder->expired();
        }
    }

    /**
     * Filter only active quotations.
     */
    public function activeOnly(bool|string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->builder->active();
        }
    }

    /**
     * Filter only multi-option quotations.
     */
    public function multiOptionOnly(bool|string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->builder->where('quotation_type', QuotationType::MultiOption->value);
        }
    }
}
