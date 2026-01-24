<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for BOM queries.
 */
class BomFilter extends QueryFilter
{
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['bom_number', 'name', 'product.name', 'product.sku'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            'bom_number',
            'name',
            'status',
            'version',
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
            'product',
            'items',
            'items.product',
            'creator',
            'variantGroup',
        ];
    }

    /**
     * Filter by product ID.
     */
    public function productId(int|string $value): void
    {
        $this->builder->where('product_id', $value);
    }

    /**
     * Filter by variant group ID.
     */
    public function variantGroupId(int|string $value): void
    {
        $this->builder->where('variant_group_id', $value);
    }

    /**
     * Filter primary variants only.
     */
    public function primaryOnly(bool|string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->builder->where('is_primary_variant', true);
        }
    }
}
