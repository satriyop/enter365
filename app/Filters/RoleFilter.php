<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasSearchFilter;

/**
 * Filter for Role queries.
 */
class RoleFilter extends QueryFilter
{
    use HasSearchFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'display_name', 'description'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return ['id', 'name', 'display_name', 'created_at', 'updated_at'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedIncludes(): array
    {
        return ['permissions', 'users'];
    }

    /**
     * Filter by system status.
     */
    public function isSystem(bool|string $value): void
    {
        $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $this->builder->where('is_system', $boolValue);
    }
}
