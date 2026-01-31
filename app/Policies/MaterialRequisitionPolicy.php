<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Manufacturing\MaterialRequisition;
use App\Models\User;

class MaterialRequisitionPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'material_requisitions';
    }

    public function approve(User $user, MaterialRequisition $requisition): bool
    {
        return $this->check($user, 'approve');
    }
}
