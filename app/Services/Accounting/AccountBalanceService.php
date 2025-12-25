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
     * @param Collection<int, Account>|array<Account> $accounts
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
     * @return Collection<int, object{
     *     date: string,
     *     entry_number: string,
     *     description: string,
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
                'je.entry_date as date',
                'je.entry_number',
                'je.description',
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
        $runningBalance = $account->opening_balance;
        
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
            
            $runningBalance = $account->opening_balance + $priorBalance;
        }

        return $entries->map(function ($entry) use ($account, &$runningBalance) {
            $movement = $account->isDebitNormal()
                ? $entry->debit - $entry->credit
                : $entry->credit - $entry->debit;
            
            $runningBalance += $movement;

            return (object) [
                'date' => $entry->date,
                'entry_number' => $entry->entry_number,
                'description' => $entry->line_description ?? $entry->description,
                'debit' => (int) $entry->debit,
                'credit' => (int) $entry->credit,
                'balance' => $runningBalance,
            ];
        });
    }

    /**
     * Get trial balance (Neraca Saldo).
     *
     * @return Collection<int, object{
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
            
            return (object) [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'debit_balance' => $account->isDebitNormal() ? max(0, $balance) : max(0, -$balance),
                'credit_balance' => $account->isCreditNormal() ? max(0, $balance) : max(0, -$balance),
            ];
        })->filter(fn ($row) => $row->debit_balance > 0 || $row->credit_balance > 0);
    }
}
