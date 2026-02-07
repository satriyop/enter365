<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for Invoice queries.
 *
 * Supported filters:
 * - status: Invoice status (draft, sent, partial, paid, overdue, cancelled)
 * - contact_id: Filter by customer contact
 * - start_date: Invoice date from
 * - end_date: Invoice date to
 * - search: Search by invoice number, description, or contact name
 */
class InvoiceFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['invoice_number', 'description', 'contact.name'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateField(): string
    {
        return 'invoice_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSortField(): ?string
    {
        return 'invoice_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            'invoice_number',
            'invoice_date',
            'due_date',
            'status',
            'total_amount',
            'paid_amount',
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
            'items.revenueAccount',
            'payments',
            'journalEntry',
            'journalEntry.lines',
            'journalEntry.lines.account',
        ];
    }

    /**
     * Filter by customer contact.
     */
    public function contactId(int|string $value): void
    {
        $this->builder->where('contact_id', $value);
    }
}
