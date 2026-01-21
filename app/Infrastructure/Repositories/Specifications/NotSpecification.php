<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Specifications;

use App\Contracts\Repositories\SpecificationInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Negates a specification.
 */
class NotSpecification extends AbstractSpecification
{
    public function __construct(
        private SpecificationInterface $specification
    ) {}

    public function apply(Builder $query): Builder
    {
        return $query->whereNot(function (Builder $q) {
            $this->specification->apply($q);
        });
    }
}
