<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasSearchFilter;

/**
 * Filter for JournalEntry queries.
 */
class JournalEntryFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasSearchFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['entry_number', 'description', 'reference'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateField(): string
    {
        return 'entry_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return ['id', 'entry_number', 'entry_date', 'created_at', 'updated_at'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedIncludes(): array
    {
        return [
            'lines',
            'lines.account',
            'fiscalPeriod',
            'reversedBy',
            'reversalOf',
        ];
    }

    /**
     * Filter by posted status.
     */
    public function isPosted(bool|string $value): void
    {
        $this->builder->where('is_posted', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * Filter by source type.
     */
    public function sourceType(string $value): void
    {
        $this->builder->where('source_type', $value);
    }
}
