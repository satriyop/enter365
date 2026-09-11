<?php

declare(strict_types=1);

namespace App\Policies;

class AnalyticPlanPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'journals';
    }
}
