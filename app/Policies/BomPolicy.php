<?php

declare(strict_types=1);

namespace App\Policies;

class BomPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'boms';
    }
}
