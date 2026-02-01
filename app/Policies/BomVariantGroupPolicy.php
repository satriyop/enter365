<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * Authorization for BOM Variant Groups.
 *
 * Reuses the 'boms' permission group since variant groups
 * are a sub-feature of BOM management.
 */
class BomVariantGroupPolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'boms';
    }
}
