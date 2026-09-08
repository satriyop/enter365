<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Models\Accounting\Journal;
use App\Services\Base\BaseService;

class JournalMasterService extends BaseService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * @param  array{name: string, type: string, sequence_prefix: string, default_account_id?: int|null, suspense_account_id?: int|null, outstanding_receipts_account_id?: int|null, outstanding_payments_account_id?: int|null, profit_account_id?: int|null, loss_account_id?: int|null, bank_account_number?: string|null, dedicated_payment_sequence?: bool, currency?: string|null, is_active?: bool}  $data
     */
    public function create(array $data): Journal
    {
        return $this->executeInTransaction('create_journal', function () use ($data) {
            return Journal::create($data);
        }, ['name' => $data['name']]);
    }

    /**
     * @param  array{name?: string, type?: string, sequence_prefix?: string, default_account_id?: int|null, suspense_account_id?: int|null, outstanding_receipts_account_id?: int|null, outstanding_payments_account_id?: int|null, profit_account_id?: int|null, loss_account_id?: int|null, bank_account_number?: string|null, dedicated_payment_sequence?: bool, currency?: string|null, is_active?: bool}  $data
     */
    public function update(Journal $journal, array $data): Journal
    {
        return $this->executeInTransaction('update_journal', function () use ($journal, $data) {
            $journal->update($data);

            return $journal->fresh([
                'defaultAccount',
                'suspenseAccount',
                'outstandingReceiptsAccount',
                'outstandingPaymentsAccount',
                'profitAccount',
                'lossAccount',
            ]);
        }, ['journal_id' => $journal->id]);
    }

    public function delete(Journal $journal): void
    {
        $this->executeInTransaction('delete_journal', function () use ($journal) {
            if ($journal->journalEntries()->exists()) {
                throw new \Exception('Tidak bisa menghapus jurnal yang sudah digunakan pada entri jurnal.');
            }

            $journal->delete();
        }, ['journal_id' => $journal->id]);
    }
}
