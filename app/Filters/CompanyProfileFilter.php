<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for CompanyProfile queries.
 */
class CompanyProfileFilter extends QueryFilter
{
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'slug', 'email', 'phone'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return ['id', 'name', 'slug', 'created_at', 'updated_at'];
    }

    /**
     * Filter by active status.
     */
    public function isActive(bool|string $value): void
    {
        $this->builder->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }
}
