<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Specifications;

use App\Contracts\Repositories\SpecificationInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Combines two specifications with OR logic.
 */
class OrSpecification extends AbstractSpecification
{
    public function __construct(
        private SpecificationInterface $left,
        private SpecificationInterface $right
    ) {}

    public function apply(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $leftQuery = clone $q;
            $rightQuery = clone $q;

            $this->left->apply($leftQuery);
            $this->right->apply($rightQuery);

            // Get the where clauses and combine with OR
            $q->where(function ($subQ) {
                $this->left->apply($subQ);
            })->orWhere(function ($subQ) {
                $this->right->apply($subQ);
            });
        });
    }
}
