<?php

declare(strict_types=1);

namespace App\Policies;

class FixedAssetPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'journals';
    }
}
