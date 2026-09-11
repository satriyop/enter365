<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Accounting\DeferredEntry;

class DeferredRevenueController extends AbstractDeferredEntryController
{
    protected function kind(): string
    {
        return DeferredEntry::KIND_REVENUE;
    }

    protected function deletedMessage(): string
    {
        return 'Pendapatan tangguhan berhasil dihapus.';
    }
}
