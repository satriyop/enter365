<?php

declare(strict_types=1);

namespace App\Policies;

class ProductPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'products';
    }
}
