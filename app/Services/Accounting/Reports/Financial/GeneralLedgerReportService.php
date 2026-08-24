<?php

namespace App\Services\Accounting\Reports\Financial;

use App\Models\Accounting\Account;
use App\Services\Accounting\AccountBalanceService;
use Illuminate\Support\Collection;

class GeneralLedgerReportService
{
    public function __construct(
        private AccountBalanceService $balanceService
    ) {}

    /**
     * Get General Ledger (Buku Besar).
     */
    public function getGeneralLedger(?string $startDate = null, ?string $endDate = null): Collection
    {
        $accounts = Account::query()
            ->orderBy('code')
            ->get();

        return $this->balanceService->getLedgers($accounts, $startDate, $endDate)
            ->filter(fn ($item) => ! empty($item->entries));
    }
}
