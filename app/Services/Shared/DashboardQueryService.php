<?php

namespace App\Services\Shared;

use App\Models\Accounting\Account;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard query service for complex database aggregations.
 *
 * Uses DB::table() for performance - avoids Eloquent hydration overhead
 * when dealing with large result sets and aggregations.
 */
class DashboardQueryService
{
    /**
     * Get cash flow daily movement for specified accounts.
     *
     * @param  Collection<int, int>  $cashAccountIds
     */
    public function getCashFlowDailyMovement(Collection $cashAccountIds, Carbon $startDate, Carbon $endDate): Collection
    {
        return DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('jel.account_id', $cashAccountIds)
            ->where('je.is_posted', true)
            ->where('je.entry_date', '>=', $startDate)
            ->where('je.entry_date', '<=', $endDate)
            ->select(
                DB::raw('DATE(je.entry_date) as date'),
                DB::raw('SUM(jel.debit) as inflow'),
                DB::raw('SUM(jel.credit) as outflow')
            )
            ->groupBy(DB::raw('DATE(je.entry_date)'))
            ->orderBy('date')
            ->get();
    }

    /**
     * Get account balance for a specific period.
     */
    public function getAccountBalanceForPeriod(Account $account, string $startDate, string $endDate): int
    {
        $movements = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('jel.account_id', $account->id)
            ->where('je.is_posted', true)
            ->whereBetween('je.entry_date', [$startDate, $endDate])
            ->whereNull('je.deleted_at')
            ->selectRaw('COALESCE(SUM(jel.debit), 0) as total_debit, COALESCE(SUM(jel.credit), 0) as total_credit')
            ->first();

        $totalDebit = (int) ($movements->total_debit ?? 0);
        $totalCredit = (int) ($movements->total_credit ?? 0);

        return $account->isDebitNormal()
            ? $totalDebit - $totalCredit
            : $totalCredit - $totalDebit;
    }

    /**
     * Get average collection days (database-agnostic).
     */
    public function getAverageCollectionDays(): float
    {
        $driver = DB::getDriverName();
        $avgDaysExpression = match ($driver) {
            'sqlite' => 'AVG(julianday(updated_at) - julianday(invoice_date))',
            'pgsql' => 'AVG(EXTRACT(EPOCH FROM (updated_at - invoice_date)) / 86400)',
            'mysql', 'mariadb' => 'AVG(DATEDIFF(updated_at, invoice_date))',
            default => 'AVG(DATEDIFF(updated_at, invoice_date))',
        };

        return (float) (DB::table('invoices')
            ->whereNotNull('paid_amount')
            ->where('paid_amount', '>', 0)
            ->selectRaw("{$avgDaysExpression} as avg_days")
            ->value('avg_days') ?? 0);
    }

    /**
     * Get top debtors with outstanding amounts.
     *
     * @return array<array{id: int, name: string, outstanding: int}>
     */
    public function getTopDebtors(int $limit, array $contactTypes, array $documentStatuses): array
    {
        return DB::table('contacts')
            ->join('invoices', 'contacts.id', '=', 'invoices.contact_id')
            ->whereIn('contacts.type', $contactTypes)
            ->whereIn('invoices.status', $documentStatuses)
            ->whereNull('contacts.deleted_at')
            ->select(
                'contacts.id',
                'contacts.name',
                DB::raw('SUM(invoices.total_amount - invoices.paid_amount) as outstanding')
            )
            ->groupBy('contacts.id', 'contacts.name')
            ->having(DB::raw('SUM(invoices.total_amount - invoices.paid_amount)'), '>', 0)
            ->orderByDesc('outstanding')
            ->limit($limit)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'outstanding' => (int) $c->outstanding,
            ])
            ->toArray();
    }

    /**
     * Get top creditors with outstanding amounts.
     *
     * @return array<array{id: int, name: string, outstanding: int}>
     */
    public function getTopCreditors(int $limit, array $contactTypes, array $documentStatuses): array
    {
        return DB::table('contacts')
            ->join('bills', 'contacts.id', '=', 'bills.contact_id')
            ->whereIn('contacts.type', $contactTypes)
            ->whereIn('bills.status', $documentStatuses)
            ->whereNull('contacts.deleted_at')
            ->select(
                'contacts.id',
                'contacts.name',
                DB::raw('SUM(bills.total_amount - bills.paid_amount) as outstanding')
            )
            ->groupBy('contacts.id', 'contacts.name')
            ->having(DB::raw('SUM(bills.total_amount - bills.paid_amount)'), '>', 0)
            ->orderByDesc('outstanding')
            ->limit($limit)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'outstanding' => (int) $c->outstanding,
            ])
            ->toArray();
    }
}
