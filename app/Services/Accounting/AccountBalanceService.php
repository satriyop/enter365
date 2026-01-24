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
     *     running_balance: int
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

        return $entries->map(function ($entry) use ($account, &$runningBalance) {
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
                'running_balance' => (int) $runningBalance,
            ];
        });
    }

    /**
     * Get trial balance (Neraca Saldo).
     *
     * @return Collection<int, array{
     *     account_id: int,
     *     code: string,
     *     name: string,
     *     type: string,
     *     debit_balance: int,
     *     credit_balance: int
     * }>
     */
    public function getTrialBalance(?string $asOfDate = null): Collection
    {
        $accounts = Account::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return $accounts->map(function ($account) use ($asOfDate) {
            $balance = $account->getBalance($asOfDate);

            return [
                'account_id' => (int) $account->id,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'type' => (string) $account->type,
                'debit_balance' => (int) ($account->isDebitNormal() ? max(0, $balance) : max(0, -$balance)),
                'credit_balance' => (int) ($account->isCreditNormal() ? max(0, $balance) : max(0, -$balance)),
            ];
        })->filter(fn ($row) => $row['debit_balance'] > 0 || $row['credit_balance'] > 0);
    }
}
