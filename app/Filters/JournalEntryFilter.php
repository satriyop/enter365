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
            'lines.partner',
            'journal',
            'journal.defaultAccount',
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

    /**
     * Filter by journal id.
     */
    public function journalId(int|string $value): void
    {
        $this->builder->where('journal_id', $value);
    }

    /**
     * Filter by journal type (sales|purchase|bank|cash|miscellaneous).
     */
    public function journalType(string $value): void
    {
        $this->builder->whereHas('journal', function ($query) use ($value) {
            $query->where('type', $value);
        });
    }

    /**
     * Filter entries that have a line for the given partner (contact).
     */
    public function partnerId(int|string $value): void
    {
        $this->builder->whereHas('lines', function ($query) use ($value) {
            $query->where('partner_id', $value);
        });
    }
}
