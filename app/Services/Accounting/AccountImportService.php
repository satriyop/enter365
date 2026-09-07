<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Imports\Accounting\AccountImport;
use App\Models\Accounting\Account;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class AccountImportService
{
    public function __construct(
        private AccountService $accountService
    ) {}

    /**
     * Import Chart of Accounts from a CSV/XLSX upload.
     *
     * Valid rows are created via AccountService; invalid rows are reported
     * with clear per-row messages (StoreAccountRequest-equivalent rules).
     *
     * @return array{
     *   created_count: int,
     *   error_count: int,
     *   errors: array<int, array{row: int, messages: array<int, string>}>,
     *   accounts: array<int, Account>
     * }
     */
    public function import(UploadedFile $file): array
    {
        $import = new AccountImport($this->accountService);
        Excel::import($import, $file);

        return $import->getResults();
    }
}
