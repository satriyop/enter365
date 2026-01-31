<?php

declare(strict_types=1);

namespace App\Policies;

class ContactPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'contacts';
    }
}
