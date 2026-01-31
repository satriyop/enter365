<?php

declare(strict_types=1);

namespace App\Policies;

class AccountPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'accounts';
    }
}
