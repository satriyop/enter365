<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasSearchFilter;

/**
 * Filter for Journal (master) queries.
 */
class JournalFilter extends QueryFilter
{
    use HasSearchFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'sequence_prefix'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return ['id', 'name', 'type', 'sequence_prefix', 'created_at', 'updated_at'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedIncludes(): array
    {
        return ['defaultAccount'];
    }

    public function type(string $value): void
    {
        $this->builder->where('type', $value);
    }

    public function isActive(bool|string $value): void
    {
        $this->builder->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }
}
