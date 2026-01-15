<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for PurchaseReturn queries.
 *
 * Supported filters:
 * - status: Return status (draft, submitted, approved, completed, rejected, cancelled)
 * - contact_id: Filter by supplier contact
 * - bill_id: Filter by related bill
 * - reason: Filter by return reason
 * - start_date: Return date from
 * - end_date: Return date to
 * - search: Search by return number, contact name, or bill number
 */
class PurchaseReturnFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['return_number', 'contact.name', 'bill.bill_number'];
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
     * Filter by supplier contact.
     */
    public function contactId(int|string $value): void
    {
        $this->builder->where('contact_id', $value);
    }

    /**
     * Filter by related bill.
     */
    public function billId(int|string $value): void
    {
        $this->builder->where('bill_id', $value);
    }

    /**
     * Filter by return reason.
     */
    public function reason(string $value): void
    {
        $this->builder->where('reason', $value);
    }
}
