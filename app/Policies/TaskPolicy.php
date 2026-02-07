<?php

declare(strict_types=1);

namespace App\Policies;

class TaskPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'project_tasks';
    }
}
