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

        // Calculate running balance
        $runningBalance = (int) $account->opening_balance;

        // If start date is provided, calculate opening balance as of that date
        if ($startDate) {
            $priorMovements = DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->where('jel.account_id', $account->id)
                ->where('je.is_posted', true)
                ->where('je.entry_date', '<', $startDate)
                ->whereNull('je.deleted_at')
                ->selectRaw('COALESCE(SUM(jel.debit), 0) as total_debit, COALESCE(SUM(jel.credit), 0) as total_credit')
                ->first();

            $priorBalance = $account->isDebitNormal()
                ? ($priorMovements->total_debit ?? 0) - ($priorMovements->total_credit ?? 0)
                : ($priorMovements->total_credit ?? 0) - ($priorMovements->total_debit ?? 0);

            $runningBalance = (int) $account->opening_balance + $priorBalance;
        }

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

        // Calculate opening balances for all accounts
        $openingBalances = [];
        if ($startDate) {
            $priorMovements = DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->whereIn('jel.account_id', $accountIds)
                ->where('je.is_posted', true)
                ->where('je.entry_date', '<', $startDate)
                ->whereNull('je.deleted_at')
                ->selectRaw('jel.account_id, SUM(jel.debit) as total_debit, SUM(jel.credit) as total_credit')
                ->groupBy('jel.account_id')
                ->get()
                ->keyBy('account_id');

            foreach ($accounts as $account) {
                $movement = $priorMovements->get($account->id);
                $totalDebit = $movement ? (int) $movement->total_debit : 0;
                $totalCredit = $movement ? (int) $movement->total_credit : 0;
                $priorBalance = $account->isDebitNormal()
                    ? ($totalDebit - $totalCredit)
                    : ($totalCredit - $totalDebit);

                $openingBalances[$account->id] = (int) $account->opening_balance + $priorBalance;
            }
        } else {
            foreach ($accounts as $account) {
                $openingBalances[$account->id] = (int) $account->opening_balance;
            }
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
     * Get trial balance for all active accounts.
     *
     * @return Collection<int, array{account_id: int, code: string, name: string, type: string, debit_balance: int, credit_balance: int}>
     */
    public function getTrialBalance(?string $asOfDate = null): Collection
    {
        $asOfDate = $asOfDate ?? now()->toDateString();

        $accounts = Account::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        // Get all balances in one query
        $balances = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.is_posted', true)
            ->where('je.entry_date', '<=', $asOfDate)
            ->whereNull('je.deleted_at')
            ->selectRaw('jel.account_id, SUM(jel.debit) as total_debit, SUM(jel.credit) as total_credit')
            ->groupBy('jel.account_id')
            ->get()
            ->keyBy('account_id');

        return $accounts->map(function (Account $account) use ($balances) {
            $movement = $balances->get($account->id);
            $totalDebit = (int) ($movement->total_debit ?? 0);
            $totalCredit = (int) ($movement->total_credit ?? 0);

            $balance = (int) $account->opening_balance + ($account->isDebitNormal()
                ? $totalDebit - $totalCredit
                : $totalCredit - $totalDebit);

            return [
                'account_id' => (int) $account->id,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'type' => (string) $account->type,
                'debit_balance' => (int) ($account->isDebitNormal() ? max(0, $balance) : max(0, -$balance)),
                'credit_balance' => (int) ($account->isCreditNormal() ? max(0, $balance) : max(0, -$balance)),
            ];
        })->filter(fn ($row) => $row['debit_balance'] > 0 || $row['credit_balance'] > 0 || (isset($balances[$row['account_id']]) && ($balances[$row['account_id']]->total_debit > 0 || $balances[$row['account_id']]->total_credit > 0)));
    }
}
