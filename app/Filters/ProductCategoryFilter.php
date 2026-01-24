<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for ProductCategory queries.
 */
class ProductCategoryFilter extends QueryFilter
{
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['code', 'name'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return ['id', 'code', 'name', 'sort_order', 'created_at', 'updated_at'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedIncludes(): array
    {
        return [
            'parent',
            'children',
            'descendants',
            'products',
        ];
    }

    /**
     * Filter by parent category.
     * Use 'null' to get root categories.
     */
    public function parentId(string|int $value): void
    {
        if ($value === 'null' || $value === '') {
            $this->builder->whereNull('parent_id');
        } else {
            $this->builder->where('parent_id', $value);
        }
    }

    /**
     * Filter only root categories.
     */
    public function rootOnly(bool|string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->builder->whereNull('parent_id');
        }
    }
}
