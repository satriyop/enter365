<?php

declare(strict_types=1);

namespace App\Policies;

class EmployeeExpensePolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'bills';
    }
}
