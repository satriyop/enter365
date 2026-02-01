<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Manufacturing\MrpRun;
use App\Models\User;

/**
 * Authorization for MRP runs and planning.
 */
class MrpRunPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'mrp';
    }

    public function execute(User $user, MrpRun $mrpRun): bool
    {
        return $this->check($user, 'execute');
    }
}
