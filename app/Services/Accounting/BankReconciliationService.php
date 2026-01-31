<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Enums\BankTransactionStatus;
use App\Models\Accounting\BankTransaction;
use App\Services\Base\BaseService;

class BankReconciliationService extends BaseService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a new bank transaction.
     *
     * @param  array{account_id: int, transaction_date: string, description: string, reference?: string, debit: int, credit: int, import_batch?: string}  $data
     */
    public function create(array $data): BankTransaction
    {
        return $this->executeInTransaction('create_bank_transaction', function () use ($data) {
            return BankTransaction::create([
                ...$data,
                'status' => BankTransactionStatus::Unmatched,
                'created_by' => $this->getUserId(),
            ]);
        }, ['account_id' => $data['account_id']]);
    }

    /**
     * Delete a bank transaction.
     */
    public function delete(BankTransaction $transaction): void
    {
        $this->executeInTransaction('delete_bank_transaction', function () use ($transaction) {
            if ($transaction->isReconciled()) {
                throw new \Exception('Tidak dapat menghapus transaksi yang sudah direkonsiliasi.');
            }

            $transaction->delete();
        }, ['transaction_id' => $transaction->id]);
    }
}
