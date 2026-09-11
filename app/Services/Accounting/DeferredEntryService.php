<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\DeferredEntryServiceInterface;
use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\DeferredEntry;
use App\Models\Accounting\DeferredEntryLine;
use App\Models\Accounting\JournalEntry;
use App\Services\Base\BaseService;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class DeferredEntryService extends BaseService implements DeferredEntryServiceInterface
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
    public function create(string $kind, array $data): DeferredEntry
    {
        return $this->executeInTransaction('create_deferred_entry', function () use ($kind, $data) {
            $this->assertAmount($data);
            $data['kind'] = $kind;
            $data['created_by'] = $this->getUserId();
            $data['status'] = DeferredEntry::STATUS_DRAFT;
            $data['remaining_amount'] = 0;

            return DeferredEntry::query()->create(Arr::only($data, [
                'kind',
                'code',
                'name',
                'contact_id',
                'amount',
                'duration_months',
                'start_date',
                'deferred_account_id',
                'recognition_account_id',
                'counterpart_account_id',
                'journal_id',
                'status',
                'remaining_amount',
                'notes',
                'created_by',
            ]));
        }, ['code' => $data['code'] ?? null, 'kind' => $kind]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DeferredEntry $entry, array $data): DeferredEntry
    {
        return $this->executeInTransaction('update_deferred_entry', function () use ($entry, $data) {
            if (! $entry->isDraft()) {
                throw new BusinessRuleException(
                    'Entri tangguhan yang sudah berjalan tidak bisa diubah.',
                    ['deferred_entry_id' => $entry->id]
                );
            }

            $merged = array_merge($entry->only(['amount', 'duration_months']), $data);
            $this->assertAmount($merged);
            unset($data['kind']);
            $entry->update($data);

            return $entry->fresh(['lines', 'contact']) ?? $entry;
        }, ['deferred_entry_id' => $entry->id]);
    }

    public function delete(DeferredEntry $entry): void
    {
        $this->executeInTransaction('delete_deferred_entry', function () use ($entry) {
            if (! $entry->isDraft()) {
                throw new BusinessRuleException(
                    'Entri tangguhan yang sudah berjalan tidak bisa dihapus.',
                    ['deferred_entry_id' => $entry->id]
                );
            }

            $entry->lines()->delete();
            $entry->delete();
        }, ['deferred_entry_id' => $entry->id]);
    }

    public function confirm(DeferredEntry $entry): DeferredEntry
    {
        return $this->executeInTransaction('confirm_deferred_entry', function () use ($entry) {
            $entry = DeferredEntry::query()->lockForUpdate()->findOrFail($entry->id);
            if (! $entry->isDraft()) {
                throw new BusinessRuleException(
                    'Hanya entri tangguhan draf yang bisa dikonfirmasi.',
                    ['deferred_entry_id' => $entry->id]
                );
            }

            $this->rebuildSchedule($entry);

            $origination = $this->postJournal($entry, [
                'entry_date' => Carbon::parse($entry->start_date)->toDateString(),
                'description' => $this->originationDescription($entry),
                'lines' => $entry->isExpense()
                    ? [
                        [
                            'account_id' => $entry->deferred_account_id,
                            'debit' => $entry->amount,
                            'credit' => 0,
                            'description' => 'Tangguhan '.$entry->code,
                        ],
                        [
                            'account_id' => $entry->counterpart_account_id,
                            'debit' => 0,
                            'credit' => $entry->amount,
                            'description' => 'Pembayaran '.$entry->code,
                        ],
                    ]
                    : [
                        [
                            'account_id' => $entry->counterpart_account_id,
                            'debit' => $entry->amount,
                            'credit' => 0,
                            'description' => 'Penerimaan '.$entry->code,
                        ],
                        [
                            'account_id' => $entry->deferred_account_id,
                            'debit' => 0,
                            'credit' => $entry->amount,
                            'description' => 'Tangguhan '.$entry->code,
                        ],
                    ],
            ]);

            $entry->update([
                'status' => DeferredEntry::STATUS_RUNNING,
                'remaining_amount' => $entry->amount,
                'origination_journal_entry_id' => $origination->id,
            ]);

            return $entry->fresh(['lines', 'contact']) ?? $entry;
        }, ['deferred_entry_id' => $entry->id]);
    }

    public function postNextRecognition(DeferredEntry $entry): DeferredEntry
    {
        return $this->executeInTransaction('post_deferred_recognition', function () use ($entry) {
            $entry = DeferredEntry::query()->lockForUpdate()->findOrFail($entry->id);
            if (! $entry->isRunning()) {
                throw new BusinessRuleException(
                    'Hanya entri tangguhan berjalan yang bisa diposting pengakuannya.',
                    ['deferred_entry_id' => $entry->id]
                );
            }

            $line = $entry->lines()
                ->where('status', DeferredEntryLine::STATUS_DRAFT)
                ->orderBy('sequence')
                ->lockForUpdate()
                ->first();

            if ($line === null) {
                throw new BusinessRuleException(
                    'Tidak ada pengakuan yang menunggu posting.',
                    ['deferred_entry_id' => $entry->id]
                );
            }

            $journal = $this->postJournal($entry, [
                'entry_date' => Carbon::parse($line->recognition_date)->toDateString(),
                'description' => 'Pengakuan '.$entry->code.' #'.$line->sequence,
                'lines' => $entry->isExpense()
                    ? [
                        [
                            'account_id' => $entry->recognition_account_id,
                            'debit' => $line->amount,
                            'credit' => 0,
                            'description' => 'Beban '.$entry->code,
                        ],
                        [
                            'account_id' => $entry->deferred_account_id,
                            'debit' => 0,
                            'credit' => $line->amount,
                            'description' => 'Amortisasi '.$entry->code,
                        ],
                    ]
                    : [
                        [
                            'account_id' => $entry->deferred_account_id,
                            'debit' => $line->amount,
                            'credit' => 0,
                            'description' => 'Amortisasi '.$entry->code,
                        ],
                        [
                            'account_id' => $entry->recognition_account_id,
                            'debit' => 0,
                            'credit' => $line->amount,
                            'description' => 'Pendapatan '.$entry->code,
                        ],
                    ],
            ]);

            $remaining = max(0, (int) $entry->remaining_amount - (int) $line->amount);
            $line->update([
                'status' => DeferredEntryLine::STATUS_POSTED,
                'journal_entry_id' => $journal->id,
                'posted_at' => now(),
            ]);

            $updates = ['remaining_amount' => $remaining];
            if ($remaining === 0) {
                $updates['status'] = DeferredEntry::STATUS_CLOSED;
            }
            $entry->update($updates);

            return $entry->fresh(['lines', 'contact']) ?? $entry;
        }, ['deferred_entry_id' => $entry->id]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertAmount(array $data): void
    {
        if ((int) ($data['amount'] ?? 0) <= 0) {
            throw new BusinessRuleException('Jumlah tangguhan harus lebih dari 0.');
        }
        if ((int) ($data['duration_months'] ?? 0) < 1) {
            throw new BusinessRuleException('Tenor tangguhan minimal 1 bulan.');
        }
    }

    /**
     * @param  array{entry_date: string, description: string, lines: list<array<string, mixed>>}  $payload
     */
    private function postJournal(DeferredEntry $entry, array $payload): JournalEntry
    {
        $payload['reference'] = $entry->code;
        $payload['source_type'] = JournalEntry::SOURCE_DEFERRED_ENTRY;
        $payload['source_id'] = $entry->id;
        if ($entry->journal_id) {
            $payload['journal_id'] = $entry->journal_id;
        }

        $payload['lines'] = array_values(array_filter(
            $payload['lines'],
            fn (array $line): bool => ((int) ($line['debit'] ?? 0) + (int) ($line['credit'] ?? 0)) > 0
        ));

        return $this->journalService->createEntry($payload, true);
    }

    private function rebuildSchedule(DeferredEntry $entry): void
    {
        $entry->lines()->delete();

        $amount = (int) $entry->amount;
        $months = (int) $entry->duration_months;
        $date = Carbon::parse($entry->start_date)->endOfMonth();
        $remaining = $amount;
        $payment = intdiv($amount, $months);

        for ($i = 1; $i <= $months; $i++) {
            $part = $i === $months ? $remaining : $payment;
            $remaining -= $part;
            $entry->lines()->create([
                'sequence' => $i,
                'recognition_date' => $date->toDateString(),
                'amount' => $part,
                'remaining_amount' => $remaining,
                'status' => DeferredEntryLine::STATUS_DRAFT,
            ]);

            $date = $date->copy()->addMonthNoOverflow()->endOfMonth();
        }
    }

    private function originationDescription(DeferredEntry $entry): string
    {
        $label = $entry->isExpense() ? 'Biaya dibayar dimuka' : 'Pendapatan diterima dimuka';

        return $label.' '.$entry->code.' '.$entry->name;
    }
}
