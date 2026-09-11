<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\AccountingTransferServiceInterface;
use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Shared\DocumentNumbers;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingTransfer;
use App\Models\Accounting\Journal;
use App\Models\Accounting\JournalEntry;
use App\Services\Base\BaseService;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class AccountingTransferService extends BaseService implements AccountingTransferServiceInterface
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        private JournalServiceInterface $journalService,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): AccountingTransfer
    {
        return $this->executeInTransaction('create_accounting_transfer', function () use ($data) {
            $resolved = $this->resolveAccounts($data);
            $this->assertTransfer($resolved);

            $date = Carbon::parse((string) $resolved['transfer_date']);

            return AccountingTransfer::query()->create([
                'transfer_number' => DocumentNumbers::generate(
                    'TRF-'.$date->format('Ym').'-',
                    'accounting_transfers',
                    'transfer_number'
                ),
                'transfer_date' => $date->toDateString(),
                'from_journal_id' => $resolved['from_journal_id'],
                'to_journal_id' => $resolved['to_journal_id'],
                'from_account_id' => $resolved['from_account_id'],
                'to_account_id' => $resolved['to_account_id'],
                'amount' => $resolved['amount'],
                'status' => AccountingTransfer::STATUS_DRAFT,
                'memo' => $resolved['memo'] ?? null,
                'created_by' => $this->getUserId(),
            ]);
        }, ['from_journal_id' => $data['from_journal_id'] ?? null]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AccountingTransfer $transfer, array $data): AccountingTransfer
    {
        return $this->executeInTransaction('update_accounting_transfer', function () use ($transfer, $data) {
            $transfer = AccountingTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $this->assertDraft($transfer);

            $merged = array_merge($transfer->only([
                'transfer_date',
                'from_journal_id',
                'to_journal_id',
                'from_account_id',
                'to_account_id',
                'amount',
                'memo',
            ]), $data);
            $resolved = $this->resolveAccounts($merged);
            $this->assertTransfer($resolved);

            $transfer->update(Arr::only($resolved, [
                'transfer_date',
                'from_journal_id',
                'to_journal_id',
                'from_account_id',
                'to_account_id',
                'amount',
                'memo',
            ]));

            return $this->freshTransfer($transfer);
        }, ['accounting_transfer_id' => $transfer->id]);
    }

    public function delete(AccountingTransfer $transfer): void
    {
        $this->executeInTransaction('delete_accounting_transfer', function () use ($transfer) {
            $transfer = AccountingTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $this->assertDraft($transfer);
            $transfer->delete();
        }, ['accounting_transfer_id' => $transfer->id]);
    }

    public function post(AccountingTransfer $transfer): AccountingTransfer
    {
        return $this->executeInTransaction('post_accounting_transfer', function () use ($transfer) {
            $transfer = AccountingTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $this->assertDraft($transfer);
            $this->assertTransfer($transfer->only([
                'from_account_id',
                'to_account_id',
                'amount',
            ]));

            $date = Carbon::parse($transfer->transfer_date)->toDateString();
            $memo = filled($transfer->memo)
                ? $transfer->memo
                : 'Transfer '.$transfer->transfer_number;

            $entry = $this->journalService->createEntry([
                'journal_id' => $transfer->from_journal_id,
                'entry_date' => $date,
                'description' => $memo,
                'reference' => $transfer->transfer_number,
                'source_type' => JournalEntry::SOURCE_ACCOUNTING_TRANSFER,
                'source_id' => $transfer->id,
                'lines' => [
                    [
                        'account_id' => $transfer->to_account_id,
                        'debit' => $transfer->amount,
                        'credit' => 0,
                        'description' => $memo,
                    ],
                    [
                        'account_id' => $transfer->from_account_id,
                        'debit' => 0,
                        'credit' => $transfer->amount,
                        'description' => $memo,
                    ],
                ],
            ], true);

            $transfer->update([
                'status' => AccountingTransfer::STATUS_POSTED,
                'journal_entry_id' => $entry->id,
            ]);

            return $this->freshTransfer($transfer);
        }, ['accounting_transfer_id' => $transfer->id]);
    }

    public function cancel(AccountingTransfer $transfer, ?string $reason = null): AccountingTransfer
    {
        return $this->executeInTransaction('cancel_accounting_transfer', function () use ($transfer, $reason) {
            $transfer = AccountingTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if (! $transfer->isPosted()) {
                throw new BusinessRuleException(
                    'Hanya transfer yang sudah diposting yang bisa dibatalkan.',
                    ['accounting_transfer_id' => $transfer->id]
                );
            }

            if ($transfer->journal_entry_id) {
                $entry = JournalEntry::query()->find($transfer->journal_entry_id);
                if ($entry !== null && $entry->is_posted && ! $entry->is_reversed) {
                    $this->journalService->reverseEntry(
                        $entry,
                        $reason ?: 'Pembatalan transfer '.$transfer->transfer_number
                    );
                }
            }

            $transfer->update([
                'status' => AccountingTransfer::STATUS_CANCELLED,
            ]);

            return $this->freshTransfer($transfer);
        }, ['accounting_transfer_id' => $transfer->id]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveAccounts(array $data): array
    {
        $fromJournal = Journal::query()->findOrFail((int) $data['from_journal_id']);
        $toJournal = Journal::query()->findOrFail((int) $data['to_journal_id']);

        $fromAccountId = (int) ($data['from_account_id'] ?? 0);
        $toAccountId = (int) ($data['to_account_id'] ?? 0);

        if ($fromAccountId === 0) {
            $fromAccountId = (int) ($fromJournal->default_account_id ?? 0);
        }
        if ($toAccountId === 0) {
            $toAccountId = (int) ($toJournal->default_account_id ?? 0);
        }

        if ($fromAccountId === 0 || $toAccountId === 0) {
            throw new BusinessRuleException(
                'Akun asal dan akun tujuan wajib diisi, atau jurnal harus punya akun default.'
            );
        }

        Account::query()->findOrFail($fromAccountId);
        Account::query()->findOrFail($toAccountId);

        $data['from_account_id'] = $fromAccountId;
        $data['to_account_id'] = $toAccountId;
        $data['amount'] = (int) ($data['amount'] ?? 0);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertTransfer(array $data): void
    {
        if ((int) ($data['amount'] ?? 0) < 1) {
            throw new BusinessRuleException('Nilai transfer harus lebih dari 0.');
        }

        if ((int) ($data['from_account_id'] ?? 0) === (int) ($data['to_account_id'] ?? 0)) {
            throw new BusinessRuleException('Akun asal dan akun tujuan tidak boleh sama.');
        }
    }

    private function assertDraft(AccountingTransfer $transfer): void
    {
        if (! $transfer->isDraft()) {
            throw new BusinessRuleException(
                'Hanya transfer draf yang bisa diubah atau dihapus.',
                ['accounting_transfer_id' => $transfer->id]
            );
        }
    }

    private function freshTransfer(AccountingTransfer $transfer): AccountingTransfer
    {
        return $transfer->fresh([
            'fromJournal',
            'toJournal',
            'fromAccount',
            'toAccount',
            'journalEntry',
        ]) ?? $transfer;
    }
}
