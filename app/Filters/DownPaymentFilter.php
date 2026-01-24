<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for DownPayment queries.
 */
class DownPaymentFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['dp_number', 'reference', 'description', 'contact.name'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateField(): string
    {
        return 'dp_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            'dp_number',
            'dp_date',
            'status',
            'amount',
            'applied_amount',
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
            'cashAccount',
            'journalEntry',
            'applications',
            'applications.invoice',
            'applications.bill',
            'creator',
        ];
    }

    /**
     * Filter by contact ID.
     */
    public function contactId(int|string $value): void
    {
        $this->builder->where('contact_id', $value);
    }

    /**
     * Filter by type (receivable, payable).
     */
    public function type(string $value): void
    {
        $this->builder->where('type', $value);
    }

    /**
     * Filter available only (has remaining balance).
     */
    public function availableOnly(bool|string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->builder->where('status', \App\Enums\DocumentStatus::Active)
                ->whereRaw('applied_amount < amount');
        }
    }
}
