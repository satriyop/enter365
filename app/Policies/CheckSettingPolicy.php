<?php

namespace App\Policies;

class CheckSettingPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'journals';
    }
}
