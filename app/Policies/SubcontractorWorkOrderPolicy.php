<?php

declare(strict_types=1);

namespace App\Policies;

class SubcontractorWorkOrderPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'subcontractor_work_orders';
    }
}
