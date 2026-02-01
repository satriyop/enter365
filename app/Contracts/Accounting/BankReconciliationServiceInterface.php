<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\Accounting\BankTransaction;

/**
 * Interface for Bank Reconciliation operations.
 */
interface BankReconciliationServiceInterface
{
    /**
     * Create a bank transaction for reconciliation.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): BankTransaction;

    /**
     * Delete a bank transaction.
     */
    public function delete(BankTransaction $transaction): void;
}
