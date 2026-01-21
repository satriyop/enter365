<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Specifications;

use App\Contracts\Repositories\SpecificationInterface;

/**
 * Base specification with composition methods.
 */
abstract class AbstractSpecification implements SpecificationInterface
{
    public function and(SpecificationInterface $other): SpecificationInterface
    {
        return new AndSpecification($this, $other);
    }

    public function or(SpecificationInterface $other): SpecificationInterface
    {
        return new OrSpecification($this, $other);
    }

    public function not(): SpecificationInterface
    {
        return new NotSpecification($this);
    }
}
