<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Accounting\DeferredEntry;

class DeferredExpenseController extends AbstractDeferredEntryController
{
    protected function kind(): string
    {
        return DeferredEntry::KIND_EXPENSE;
    }

    protected function deletedMessage(): string
    {
        return 'Biaya tangguhan berhasil dihapus.';
    }
}
