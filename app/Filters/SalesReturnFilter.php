<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for SalesReturn queries.
 *
 * Supported filters:
 * - status: Return status (draft, submitted, approved, completed, rejected, cancelled)
 * - contact_id: Filter by customer contact
 * - invoice_id: Filter by related invoice
 * - reason: Filter by return reason
 * - start_date: Return date from
 * - end_date: Return date to
 * - search: Search by return number, contact name, or invoice number
 */
class SalesReturnFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['return_number', 'contact.name', 'invoice.invoice_number'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateField(): string
    {
        return 'return_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSortField(): ?string
    {
        return 'return_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            'return_number',
            'return_date',
            'status',
            'total',
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
            'warehouse',
            'items',
            'items.product',
            'invoice',
            'journalEntry',
            'creator',
        ];
    }

    /**
     * Filter by customer contact.
     */
    public function contactId(int|string $value): void
    {
        $this->builder->where('contact_id', $value);
    }

    /**
     * Filter by related invoice.
     */
    public function invoiceId(int|string $value): void
    {
        $this->builder->where('invoice_id', $value);
    }

    /**
     * Filter by return reason.
     */
    public function reason(string $value): void
    {
        $this->builder->where('reason', $value);
    }
}
