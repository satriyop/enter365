<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for Bill queries.
 *
 * Supported filters:
 * - status: Bill status (draft, submitted, approved, partial, paid, overdue, cancelled)
 * - contact_id: Filter by supplier contact
 * - start_date: Bill date from
 * - end_date: Bill date to
 * - search: Search by bill number, vendor invoice number, description, or contact name
 */
class BillFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['bill_number', 'vendor_invoice_number', 'description', 'contact.name'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateField(): string
    {
        return 'bill_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSortField(): ?string
    {
        return 'bill_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            'bill_number',
            'bill_date',
            'due_date',
            'status',
            'total_amount',
            'amount_paid',
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
            'items.expenseAccount',
            'journalEntry',
            'journalEntry.lines',
            'payments',
            'createdByUser',
        ];
    }

    /**
     * Filter by supplier contact.
     */
    public function contactId(int|string $value): void
    {
        $this->builder->where('contact_id', $value);
    }
}
