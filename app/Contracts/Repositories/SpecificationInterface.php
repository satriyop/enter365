<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use Illuminate\Database\Eloquent\Builder;

/**
 * Specification pattern for reusable query conditions.
 *
 * Specifications encapsulate business rules that can be composed
 * and reused across different queries.
 */
interface SpecificationInterface
{
    /**
     * Apply specification to query builder.
     */
    public function apply(Builder $query): Builder;

    /**
     * Combine with AND.
     */
    public function and(SpecificationInterface $other): SpecificationInterface;

    /**
     * Combine with OR.
     */
    public function or(SpecificationInterface $other): SpecificationInterface;

    /**
     * Negate this specification.
     */
    public function not(): SpecificationInterface;
}
