<?php

namespace App\Contracts\Accounting;

use App\Models\Accounting\AccountingTransfer;

interface AccountingTransferServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): AccountingTransfer;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AccountingTransfer $transfer, array $data): AccountingTransfer;

    public function delete(AccountingTransfer $transfer): void;

    public function post(AccountingTransfer $transfer): AccountingTransfer;

    public function cancel(AccountingTransfer $transfer, ?string $reason = null): AccountingTransfer;
}
