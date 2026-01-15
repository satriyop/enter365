<?php

declare(strict_types=1);

namespace App\Traits;

use App\Filters\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait to make models filterable.
 *
 * Add this trait to any Eloquent model to enable query filtering:
 *
 * ```php
 * class Product extends Model
 * {
 *     use Filterable;
 * }
 *
 * // In controller:
 * Product::query()->filter($filter)->paginate();
 * ```
 */
trait Filterable
{
    /**
     * Apply filters to the query.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFilter(Builder $query, QueryFilter $filter): Builder
    {
        return $filter->apply($query);
    }
}
