<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasSearchFilter;

/**
 * Filter for Payment queries.
 *
 * Supported filters:
 * - type: Payment type (receive, send)
 * - contact_id: Filter by contact
 * - is_voided: Filter by voided status
 * - start_date: Payment date from
 * - end_date: Payment date to
 * - search: Search by payment number, reference, or contact name
 */
class PaymentFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasSearchFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['payment_number', 'reference', 'contact.name'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateField(): string
    {
        return 'payment_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSortField(): ?string
    {
        return 'payment_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            'payment_number',
            'payment_date',
            'type',
            'amount',
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
            'payable',
        ];
    }

    /**
     * Filter by payment type (receive, send).
     */
    public function type(string $value): void
    {
        $this->builder->where('type', $value);
    }

    /**
     * Filter by contact.
     */
    public function contactId(int|string $value): void
    {
        $this->builder->where('contact_id', $value);
    }

    /**
     * Filter by voided status.
     */
    public function isVoided(bool|string $value): void
    {
        $this->builder->where('is_voided', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }
}
