<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\AccountReconcileServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountReconciliation;
use App\Models\Accounting\JournalEntryLine;
use App\Services\Base\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountReconcileService extends BaseService implements AccountReconcileServiceInterface
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * @return Collection<int, mixed>
     */
    public function accounts(): Collection
    {
        $outstanding = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.is_posted', true)
            ->whereNull('je.deleted_at')
            ->whereRaw('(jel.debit + jel.credit) > jel.reconciled_amount')
            ->groupBy('jel.account_id')
            ->select('jel.account_id')
            ->selectRaw('COUNT(*) as unreconciled_count')
            ->selectRaw('SUM(jel.debit + jel.credit - jel.reconciled_amount) as unreconciled_amount')
            ->get()
            ->keyBy('account_id');

        return Account::query()
            ->where('allow_reconciliation', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->map(function (Account $account) use ($outstanding): array {
                $row = $outstanding->get($account->id);

                return [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'unreconciled_count' => (int) data_get($row, 'unreconciled_count', 0),
                    'unreconciled_amount' => (int) data_get($row, 'unreconciled_amount', 0),
                ];
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, mixed>
     */
    public function lines(array $filters): Collection
    {
        $accountId = (int) ($filters['account_id'] ?? 0);
        if ($accountId < 1) {
            throw new BusinessRuleException('Akun rekonsiliasi wajib dipilih.');
        }

        $query = JournalEntryLine::query()
            ->with(['journalEntry', 'account', 'partner'])
            ->where('account_id', $accountId)
            ->whereHas('journalEntry', function ($inner): void {
                $inner->where('is_posted', true);
            })
            ->whereRaw('(debit + credit) > reconciled_amount')
            ->orderBy('id');

        if (! empty($filters['partner_id'])) {
            $query->where('partner_id', (int) $filters['partner_id']);
        }

        return $query->get()->map(fn (JournalEntryLine $line): array => $this->serializeLine($line));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function history(array $filters): LengthAwarePaginator
    {
        $query = AccountReconciliation::query()
            ->with(['account', 'partner', 'items.journalEntryLine.journalEntry'])
            ->orderByDesc('id');

        if (! empty($filters['account_id'])) {
            $query->where('account_id', (int) $filters['account_id']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 50));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function reconcile(array $data): AccountReconciliation
    {
        return $this->executeInTransaction('reconcile_account_lines', function () use ($data) {
            $account = Account::query()->lockForUpdate()->findOrFail((int) $data['account_id']);
            if (! $account->allow_reconciliation) {
                throw new BusinessRuleException(
                    'Akun ini tidak diizinkan untuk rekonsiliasi.',
                    ['account_id' => $account->id]
                );
            }

            /** @var list<array<string, mixed>> $rawItems */
            $rawItems = array_values($data['items'] ?? []);
            if (count($rawItems) < 2) {
                throw new BusinessRuleException('Pilih minimal dua baris jurnal untuk direkonsiliasi.');
            }

            $lineIds = array_map(fn (array $item): int => (int) ($item['journal_entry_line_id'] ?? 0), $rawItems);
            $lines = JournalEntryLine::query()
                ->with('journalEntry')
                ->whereIn('id', $lineIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $debitTotal = 0;
            $creditTotal = 0;
            $resolved = [];
            $partnerIds = [];

            foreach ($rawItems as $item) {
                $line = $lines->get((int) $item['journal_entry_line_id']);
                if ($line === null) {
                    throw new BusinessRuleException('Baris jurnal tidak ditemukan.');
                }
                if ((int) $line->account_id !== $account->id) {
                    throw new BusinessRuleException('Semua baris harus dari akun yang sama.');
                }
                if (! $line->journalEntry?->is_posted) {
                    throw new BusinessRuleException('Hanya baris jurnal yang sudah diposting yang bisa direkonsiliasi.');
                }

                $residual = $line->residualAmount();
                $amount = array_key_exists('amount', $item) && $item['amount'] !== null && $item['amount'] !== ''
                    ? (int) $item['amount']
                    : $residual;

                if ($amount < 1 || $amount > $residual) {
                    throw new BusinessRuleException(
                        'Nilai rekonsiliasi melebihi sisa baris jurnal.',
                        ['journal_entry_line_id' => $line->id, 'residual' => $residual]
                    );
                }

                if ($line->isDebit()) {
                    $debitTotal += $amount;
                } else {
                    $creditTotal += $amount;
                }

                if ($line->partner_id) {
                    $partnerIds[$line->partner_id] = true;
                }

                $resolved[] = ['line' => $line, 'amount' => $amount];
            }

            if ($debitTotal < 1 || $creditTotal < 1) {
                throw new BusinessRuleException('Rekonsiliasi harus mencakup debit dan kredit.');
            }
            if ($debitTotal !== $creditTotal) {
                throw new BusinessRuleException(
                    'Total debit dan kredit yang dipilih harus sama.',
                    ['debit' => $debitTotal, 'credit' => $creditTotal]
                );
            }

            $partnerId = $data['partner_id'] ?? null;
            if ($partnerId === null && count($partnerIds) === 1) {
                $partnerId = array_key_first($partnerIds);
            }

            $reconciliation = AccountReconciliation::query()->create([
                'account_id' => $account->id,
                'partner_id' => $partnerId,
                'amount' => $debitTotal,
                'notes' => $data['notes'] ?? null,
                'reconciled_at' => now(),
                'reconciled_by' => $this->getUserId(),
            ]);

            foreach ($resolved as $row) {
                /** @var JournalEntryLine $line */
                $line = $row['line'];
                $amount = (int) $row['amount'];
                $line->increment('reconciled_amount', $amount);
                $reconciliation->items()->create([
                    'journal_entry_line_id' => $line->id,
                    'amount' => $amount,
                ]);
            }

            return $reconciliation->fresh(['account', 'partner', 'items.journalEntryLine.journalEntry'])
                ?? $reconciliation;
        }, ['account_id' => $data['account_id'] ?? null]);
    }

    public function unreconcile(AccountReconciliation $reconciliation): void
    {
        $this->executeInTransaction('unreconcile_account_lines', function () use ($reconciliation) {
            $reconciliation = AccountReconciliation::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($reconciliation->id);

            foreach ($reconciliation->items as $item) {
                $line = JournalEntryLine::query()->lockForUpdate()->find($item->journal_entry_line_id);
                if ($line === null) {
                    continue;
                }

                $next = max(0, (int) $line->reconciled_amount - (int) $item->amount);
                $line->update(['reconciled_amount' => $next]);
            }

            $reconciliation->items()->delete();
            $reconciliation->delete();
        }, ['account_reconciliation_id' => $reconciliation->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLine(JournalEntryLine $line): array
    {
        $entry = $line->journalEntry;

        return [
            'id' => $line->id,
            'journal_entry_id' => $line->journal_entry_id,
            'account_id' => $line->account_id,
            'partner_id' => $line->partner_id,
            'entry_number' => $entry?->entry_number,
            'entry_date' => $entry?->entry_date?->toDateString(),
            'description' => $line->description ?: $entry?->description,
            'debit' => (int) $line->debit,
            'credit' => (int) $line->credit,
            'reconciled_amount' => (int) $line->reconciled_amount,
            'residual' => $line->residualAmount(),
            'side' => $line->isDebit() ? 'debit' : 'credit',
            'partner' => $line->partner === null ? null : [
                'id' => $line->partner->id,
                'code' => $line->partner->code,
                'name' => $line->partner->name,
            ],
        ];
    }
}
