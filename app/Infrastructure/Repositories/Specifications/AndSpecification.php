<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Specifications;

use App\Contracts\Repositories\SpecificationInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Combines two specifications with AND logic.
 */
class AndSpecification extends AbstractSpecification
{
    public function __construct(
        private SpecificationInterface $left,
        private SpecificationInterface $right
    ) {}

    public function apply(Builder $query): Builder
    {
        $this->left->apply($query);
        $this->right->apply($query);

        return $query;
    }
}
