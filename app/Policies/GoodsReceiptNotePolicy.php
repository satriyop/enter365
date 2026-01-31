<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Purchasing\GoodsReceiptNote;
use App\Models\User;

class GoodsReceiptNotePolicy extends BaseResourcePolicy
{
    protected function permissionGroup(): string
    {
        return 'goods_receipt_notes';
    }

    public function receive(User $user, GoodsReceiptNote $grn): bool
    {
        return $this->check($user, 'receive');
    }
}
