<?php

declare(strict_types=1);

namespace App\Services\Accounting\Journal;

use App\Contracts\Accounting\AccountLookupServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Services\Base\BaseService;
use Carbon\Carbon;

class JournalEntryService extends BaseService
{
    public function __construct(
        private AccountLookupServiceInterface $accountLookup,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a journal entry with lines.
     *
     * @param array{
     *     entry_date: string,
     *     description: string,
     *     reference?: string,
     *     source_type?: string,
     *     source_id?: int,
     *     lines: array<array{account_id: int, debit?: int, credit?: int, description?: string, currency_code?: string|null, amount_currency?: int|null, exchange_rate?: float|null}>
     * } $data
     */
    public function createEntry(array $data, bool $autoPost = false): JournalEntry
    {
        return $this->executeInTransaction('create_entry', function () use ($data, $autoPost) {
            // Resolve fiscal period by entry_date, not today's date
            $entryDate = Carbon::parse($data['entry_date']);
            $fiscalPeriod = FiscalPeriod::forDate($entryDate);

            if ($fiscalPeriod && $fiscalPeriod->getStatus() === \App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus::Closed) {
                throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                    'membuat jurnal',
                    "Periode fiskal '{$fiscalPeriod->name}' sudah ditutup untuk tanggal {$entryDate->toDateString()}."
                );
            }

            if ($fiscalPeriod && $fiscalPeriod->getStatus() === \App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus::Locked) {
                throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                    'membuat jurnal',
                    "Periode fiskal '{$fiscalPeriod->name}' sedang dikunci untuk tanggal {$entryDate->toDateString()}."
                );
            }

            $entry = JournalEntry::create([
                'entry_number' => $data['entry_number'] ?? \App\Domain\Shared\DocumentNumbers::generate(
                    'JE-'.now()->format('Ym').'-',
                    'journal_entries',
                    'entry_number'
                ),
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
                'reference' => $data['reference'] ?? null,
                'source_type' => $data['source_type'] ?? JournalEntry::SOURCE_MANUAL,
                'source_id' => $data['source_id'] ?? null,
                'fiscal_period_id' => $fiscalPeriod?->id,
                'is_posted' => false,
                'created_by' => $this->getUserId(),
            ]);

            foreach ($data['lines'] as $lineData) {
                // Support both account_id and account_code for flexibility
                $accountId = $lineData['account_id'] ?? null;
                if (! $accountId && isset($lineData['account_code'])) {
                    $account = $this->accountLookup->findByCodeOrFail(
                        $lineData['account_code'],
                        'journal entry line'
                    );
                    $accountId = $account->id;
                }

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $accountId,
                    'description' => $lineData['description'] ?? null,
                    'debit' => $lineData['debit'] ?? 0,
                    'credit' => $lineData['credit'] ?? 0,
                    'currency_code' => $lineData['currency_code'] ?? null,
                    'amount_currency' => $lineData['amount_currency'] ?? null,
                    'exchange_rate' => $lineData['exchange_rate'] ?? null,
                ]);
            }

            if ($autoPost) {
                $this->postEntry($entry);
            }

            return $entry->fresh(['lines', 'lines.account']);
        }, ['source_type' => $data['source_type'] ?? 'manual', 'source_id' => $data['source_id'] ?? null]);
    }

    /**
     * Validate and post a journal entry.
     */
    public function postEntry(JournalEntry $entry): JournalEntry
    {
        if ($entry->is_posted) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($entry, 'Journal entry is already posted.');
        }

        if (! $entry->isBalanced()) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'posting journal entry',
                'Journal entry is not balanced. Debit: '.$entry->getTotalDebit().', Credit: '.$entry->getTotalCredit()
            );
        }

        if ($entry->lines()->count() < 2) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'posting journal entry',
                'Journal entry must have at least two lines'
            );
        }

        // Check fiscal period is open for posting
        if ($entry->fiscalPeriod) {
            if ($entry->fiscalPeriod->getStatus() === \App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus::Closed) {
                throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                    'posting journal entry',
                    "Tidak bisa posting ke periode fiskal '{$entry->fiscalPeriod->name}' yang sudah ditutup."
                );
            }

            if ($entry->fiscalPeriod->getStatus() === \App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus::Locked) {
                throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                    'posting journal entry',
                    "Tidak bisa posting ke periode fiskal '{$entry->fiscalPeriod->name}' yang sedang dikunci."
                );
            }
        }

        $entry->update(['is_posted' => true]);

        return $entry->fresh();
    }

    /**
     * Reverse a posted journal entry.
     *
     * Locks the original row, keeps the caller's reason on the ledger, and
     * posts into an open fiscal period (original date if that period is still
     * open; otherwise today).
     */
    public function reverseEntry(JournalEntry $entry, ?string $description = null): JournalEntry
    {
        return $this->executeInTransaction('reverse_entry', function () use ($entry, $description) {
            $locked = JournalEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if (! $locked->is_posted) {
                throw BusinessRuleException::operationNotAllowed(
                    'reversing journal entry',
                    'Cannot reverse an unposted journal entry'
                );
            }

            if ($locked->is_reversed) {
                throw BusinessRuleException::operationNotAllowed(
                    'reversing journal entry',
                    'Journal entry is already reversed'
                );
            }

            $locked->load('lines');

            $reversalLines = [];
            foreach ($locked->lines as $line) {
                $reversalLines[] = [
                    'account_id' => $line->account_id,
                    'description' => $line->description,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'currency_code' => $line->currency_code,
                    'amount_currency' => $line->amount_currency,
                    'exchange_rate' => $line->exchange_rate,
                ];
            }

            $reversalDescription = ($description !== null && trim($description) !== '')
                ? trim($description)
                : 'Reversal of '.$locked->entry_number.': '.$locked->description;

            $reversalEntry = $this->createEntry([
                'entry_date' => $this->reversalEntryDate($locked),
                'description' => $reversalDescription,
                'reference' => $locked->entry_number,
                'source_type' => JournalEntry::SOURCE_REVERSAL,
                'source_id' => $locked->id,
                'lines' => $reversalLines,
            ], autoPost: true);

            $reversalEntry->update(['reversal_of_id' => $locked->id]);
            $locked->update([
                'is_reversed' => true,
                'reversed_by_id' => $reversalEntry->id,
            ]);

            return $reversalEntry->fresh(['lines', 'lines.account']);
        }, ['entry_id' => $entry->id]);
    }

    /**
     * Same-period reversal when the original period is still open; otherwise
     * post into the current open period so a closed month cannot block a void.
     */
    private function reversalEntryDate(JournalEntry $entry): string
    {
        $originalDate = $entry->entry_date instanceof \DateTimeInterface
            ? Carbon::parse($entry->entry_date)
            : Carbon::parse((string) $entry->entry_date);

        $originalPeriod = FiscalPeriod::forDate($originalDate);

        if ($originalPeriod !== null && $originalPeriod->getStatus() === FiscalPeriodStatus::Open) {
            return $originalDate->toDateString();
        }

        $current = FiscalPeriod::current();

        if ($current === null || $current->getStatus() !== FiscalPeriodStatus::Open) {
            throw BusinessRuleException::operationNotAllowed(
                'membalik jurnal',
                'Tidak ada periode fiskal terbuka untuk mencatat pembalikan.'
            );
        }

        return now()->toDateString();
    }
}
