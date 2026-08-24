<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountBalanceService
{
    /**
     * Get the balance for a single account.
     */
    public function getBalance(Account $account, ?string $asOfDate = null): int
    {
        return $account->getBalance($asOfDate);
    }

    /**
     * Get balances for multiple accounts.
     *
     * @param  Collection<int, Account>|array<Account>  $accounts
     * @return array<int, int> Account ID => Balance
     */
    public function getBalances(Collection|array $accounts, ?string $asOfDate = null): array
    {
        $balances = [];
        foreach ($accounts as $account) {
            $balances[$account->id] = $account->getBalance($asOfDate);
        }

        return $balances;
    }

    /**
     * Get ledger entries for an account.
     *
     * @return Collection<int, array{
     *     id: int,
     *     journal_entry_id: int,
     *     date: string,
     *     entry_number: string,
     *     description: string,
     *     reference: string|null,
     *     debit: int,
     *     credit: int,
     *     balance: int
     * }>
     */
    public function getLedger(Account $account, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('jel.account_id', $account->id)
            ->where('je.is_posted', true)
            ->whereNull('je.deleted_at')
            ->select([
                'jel.id',
                'je.id as journal_entry_id',
                'je.entry_date as date',
                'je.entry_number',
                'je.description as entry_description',
                'je.reference',
                'jel.description as line_description',
                'jel.debit',
                'jel.credit',
            ])
            ->orderBy('je.entry_date')
            ->orderBy('je.id');

        if ($startDate) {
            $query->where('je.entry_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('je.entry_date', '<=', $endDate);
        }

        $entries = $query->get();

        $runningBalance = $startDate
            ? $this->postedNetBefore($account, $startDate)
            : 0;

        return $entries->map(function (\stdClass $entry) use ($account, &$runningBalance) {
            $movement = $account->isDebitNormal()
                ? (int) $entry->debit - (int) $entry->credit
                : (int) $entry->credit - (int) $entry->debit;

            $runningBalance += $movement;

            return [
                'id' => (int) $entry->id,
                'journal_entry_id' => (int) $entry->journal_entry_id,
                'date' => (string) $entry->date,
                'entry_number' => (string) $entry->entry_number,
                'description' => (string) ($entry->line_description ?? $entry->entry_description),
                'reference' => $entry->reference ? (string) $entry->reference : null,
                'debit' => (int) $entry->debit,
                'credit' => (int) $entry->credit,
                'balance' => (int) $runningBalance,
            ];
        });
    }

    /**
     * Get ledger entries for multiple accounts.
     *
     * @param  Collection<int, Account>  $accounts
     */
    public function getLedgers(Collection $accounts, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $accountIds = $accounts->pluck('id')->toArray();

        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('jel.account_id', $accountIds)
            ->where('je.is_posted', true)
            ->whereNull('je.deleted_at')
            ->select([
                'jel.id',
                'jel.account_id',
                'je.id as journal_entry_id',
                'je.entry_date as date',
                'je.entry_number',
                'je.description as entry_description',
                'je.reference',
                'jel.description as line_description',
                'jel.debit',
                'jel.credit',
            ])
            ->orderBy('je.entry_date')
            ->orderBy('je.id');

        if ($startDate) {
            $query->where('je.entry_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('je.entry_date', '<=', $endDate);
        }

        $allEntries = $query->get()->groupBy('account_id');

        $openingBalances = [];
        foreach ($accounts as $account) {
            $openingBalances[$account->id] = $startDate
                ? $this->postedNetBefore($account, $startDate)
                : 0;
        }

        return $accounts->map(function (Account $account) use ($allEntries, $openingBalances) {
            $entries = $allEntries->get($account->id, collect());
            $runningBalance = $openingBalances[$account->id];

            $transformedEntries = $entries->map(function (\stdClass $entry) use ($account, &$runningBalance) {
                $movement = $account->isDebitNormal()
                    ? (int) $entry->debit - (int) $entry->credit
                    : (int) $entry->credit - (int) $entry->debit;

                $runningBalance += $movement;

                return [
                    'id' => (int) $entry->id,
                    'journal_entry_id' => (int) $entry->journal_entry_id,
                    'date' => (string) $entry->date,
                    'entry_number' => (string) $entry->entry_number,
                    'description' => (string) ($entry->line_description ?? $entry->entry_description),
                    'reference' => $entry->reference ? (string) $entry->reference : null,
                    'debit' => (int) $entry->debit,
                    'credit' => (int) $entry->credit,
                    'balance' => (int) $runningBalance,
                ];
            });

            return (object) [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'opening_balance' => $openingBalances[$account->id],
                'entries' => $transformedEntries->values()->all(),
                'closing_balance' => $runningBalance,
            ];
        });
    }

    /**
     * Get trial balance from posted journal lines.
     *
     * Includes inactive accounts that still hold movements. Opening is the
     * ledger, not accounts.opening_balance.
     *
     * @return Collection<int, mixed>
     */
    public function getTrialBalance(?string $asOfDate = null): Collection
    {
        $asOfDate = $asOfDate ?? now()->toDateString();

        $balances = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.is_posted', true)
            ->where('je.entry_date', '<=', $asOfDate.' 23:59:59')
            ->whereNull('je.deleted_at')
            ->selectRaw('jel.account_id, SUM(jel.debit) as total_debit, SUM(jel.credit) as total_credit')
            ->groupBy('jel.account_id')
            ->get()
            ->keyBy('account_id');

        $accounts = Account::query()
            ->whereIn('id', $balances->keys())
            ->orderBy('code')
            ->get();

        return $accounts->map(function (Account $account) use ($balances) {
            $movement = $balances->get($account->id);
            $totalDebit = (int) ($movement->total_debit ?? 0);
            $totalCredit = (int) ($movement->total_credit ?? 0);

            $balance = $account->isDebitNormal()
                ? $totalDebit - $totalCredit
                : $totalCredit - $totalDebit;

            return [
                'account_id' => (int) $account->id,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'type' => (string) $account->type,
                'is_active' => (bool) $account->is_active,
                'debit_balance' => (int) ($account->isDebitNormal() ? max(0, $balance) : max(0, -$balance)),
                'credit_balance' => (int) ($account->isCreditNormal() ? max(0, $balance) : max(0, -$balance)),
            ];
        })->filter(fn ($row) => $row['debit_balance'] > 0 || $row['credit_balance'] > 0);
    }

    /**
     * Posted net movement (in the account's normal direction) before a date.
     */
    public function postedNetBefore(Account $account, string $beforeDate): int
    {
        $priorMovements = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('jel.account_id', $account->id)
            ->where('je.is_posted', true)
            ->where('je.entry_date', '<', $beforeDate)
            ->whereNull('je.deleted_at')
            ->selectRaw('COALESCE(SUM(jel.debit), 0) as total_debit, COALESCE(SUM(jel.credit), 0) as total_credit')
            ->first();

        $totalDebit = (int) ($priorMovements->total_debit ?? 0);
        $totalCredit = (int) ($priorMovements->total_credit ?? 0);

        return $account->isDebitNormal()
            ? $totalDebit - $totalCredit
            : $totalCredit - $totalDebit;
    }
}
