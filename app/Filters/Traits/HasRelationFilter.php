<?php

declare(strict_types=1);

namespace App\Filters\Traits;

/**
 * Trait for relationship filtering.
 *
 * Provides helpers for filtering by related models:
 * - contactId, projectId, warehouseId, etc.
 * - Generic filterByRelation method
 *
 * Use in filter classes that need to filter by foreign keys.
 */
trait HasRelationFilter
{
    /**
     * Filter by contact ID.
     */
    public function contactId(int|string $value): void
    {
        $this->builder->where('contact_id', $value);
    }

    /**
     * Filter by project ID.
     */
    public function projectId(int|string $value): void
    {
        $this->builder->where('project_id', $value);
    }

    /**
     * Filter by warehouse ID.
     */
    public function warehouseId(int|string $value): void
    {
        $this->builder->where('warehouse_id', $value);
    }

    /**
     * Filter by product ID.
     */
    public function productId(int|string $value): void
    {
        $this->builder->where('product_id', $value);
    }

    /**
     * Filter by category ID.
     */
    public function categoryId(int|string $value): void
    {
        $this->builder->where('category_id', $value);
    }

    /**
     * Filter by BOM ID.
     */
    public function bomId(int|string $value): void
    {
        $this->builder->where('bom_id', $value);
    }

    /**
     * Filter by created by user.
     */
    public function createdBy(int|string $value): void
    {
        $this->builder->where('created_by', $value);
    }

    /**
     * Generic filter by foreign key.
     */
    protected function filterByForeignKey(string $column, int|string $value): void
    {
        $this->builder->where($column, $value);
    }

    /**
     * Filter by relationship existence with optional condition.
     */
    protected function filterByRelation(string $relation, ?callable $callback = null): void
    {
        if ($callback) {
            $this->builder->whereHas($relation, $callback);
        } else {
            $this->builder->has($relation);
        }
    }

    /**
     * Filter by relationship non-existence.
     */
    protected function filterByMissingRelation(string $relation): void
    {
        $this->builder->doesntHave($relation);
    }
}
