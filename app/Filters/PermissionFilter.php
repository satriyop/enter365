<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasSearchFilter;

/**
 * Filter for Permission queries.
 */
class PermissionFilter extends QueryFilter
{
    use HasSearchFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'display_name', 'group', 'description'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return ['id', 'name', 'display_name', 'group', 'created_at', 'updated_at'];
    }

    /**
     * Filter by group.
     */
    public function group(string $value): void
    {
        $this->builder->where('group', $value);
    }
}
