<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for PurchaseOrder queries.
 *
 * Supported filters:
 * - status: PO status (draft, submitted, approved, partial, received, cancelled)
 * - contact_id: Filter by supplier contact
 * - start_date: PO date from
 * - end_date: PO date to
 * - outstanding_only: Only POs with outstanding items to receive
 * - active_only: Only active (approved/partial) POs
 * - search: Search by PO number, subject, reference, or contact name
 */
class PurchaseOrderFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['po_number', 'subject', 'reference', 'contact.name'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateField(): string
    {
        return 'po_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSortField(): ?string
    {
        return 'po_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            'po_number',
            'po_date',
            'expected_date',
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
     * Filter only POs with outstanding items to receive.
     */
    public function outstandingOnly(bool|string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->builder->outstanding();
        }
    }

    /**
     * Filter only active (approved/partial) POs.
     */
    public function activeOnly(bool|string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->builder->active();
        }
    }
}
