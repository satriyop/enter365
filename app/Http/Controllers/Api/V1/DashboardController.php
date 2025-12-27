<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\Contact;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use App\Services\Accounting\AccountBalanceService;
use App\Services\Accounting\AgingReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        private AccountBalanceService $balanceService,
        private AgingReportService $agingService
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'receivables' => $this->getReceivablesSummary(),
            'payables' => $this->getPayablesSummary(),
            'cash_position' => $this->getCashPosition(),
            'recent_activity' => $this->getRecentActivity(),
            'monthly_comparison' => $this->getMonthlyComparison(),
        ]);
    }

    public function receivables(): JsonResponse
    {
        $invoices = Invoice::query()
            ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])
            ->with('contact')
            ->orderBy('due_date')
            ->get();

        $total = $invoices->sum(fn ($inv) => $inv->total_amount - $inv->paid_amount);
        $overdue = $invoices->where('status', Invoice::STATUS_OVERDUE)->sum(fn ($inv) => $inv->total_amount - $inv->paid_amount);

        return response()->json([
            'total_outstanding' => $total,
            'total_overdue' => $overdue,
            'count' => $invoices->count(),
            'overdue_count' => $invoices->where('status', Invoice::STATUS_OVERDUE)->count(),
            'aging' => $this->agingService->getReceivableAging(),
            'top_debtors' => $this->getTopDebtors(5),
        ]);
    }

    public function payables(): JsonResponse
    {
        $bills = Bill::query()
            ->whereIn('status', [Bill::STATUS_RECEIVED, Bill::STATUS_PARTIAL, Bill::STATUS_OVERDUE])
            ->with('contact')
            ->orderBy('due_date')
            ->get();

        $total = $bills->sum(fn ($bill) => $bill->total_amount - $bill->paid_amount);
        $overdue = $bills->where('status', Bill::STATUS_OVERDUE)->sum(fn ($bill) => $bill->total_amount - $bill->paid_amount);

        return response()->json([
            'total_outstanding' => $total,
            'total_overdue' => $overdue,
            'count' => $bills->count(),
            'overdue_count' => $bills->where('status', Bill::STATUS_OVERDUE)->count(),
            'aging' => $this->agingService->getPayableAging(),
            'top_creditors' => $this->getTopCreditors(5),
        ]);
    }

    public function cashFlow(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);
        $startDate = now()->subDays($days);

        $cashAccounts = Account::where('type', Account::TYPE_ASSET)
            ->where('code', 'like', '1-1%')
            ->pluck('id');

        $dailyMovement = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('jel.account_id', $cashAccounts)
            ->where('je.is_posted', true)
            ->where('je.entry_date', '>=', $startDate)
            ->select(
                DB::raw('DATE(je.entry_date) as date'),
                DB::raw('SUM(jel.debit) as inflow'),
                DB::raw('SUM(jel.credit) as outflow')
            )
            ->groupBy(DB::raw('DATE(je.entry_date)'))
            ->orderBy('date')
            ->get();

        return response()->json([
            'period_days' => $days,
            'total_inflow' => $dailyMovement->sum('inflow'),
            'total_outflow' => $dailyMovement->sum('outflow'),
            'net_flow' => $dailyMovement->sum('inflow') - $dailyMovement->sum('outflow'),
            'daily_movement' => $dailyMovement,
        ]);
    }

    public function profitLoss(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        // Calculate revenue and expense from account balances
        $revenueAccounts = Account::where('type', Account::TYPE_REVENUE)->where('is_active', true)->get();
        $expenseAccounts = Account::where('type', Account::TYPE_EXPENSE)->where('is_active', true)->get();

        $totalRevenue = $revenueAccounts->sum(fn ($acc) => abs($this->getAccountBalanceForPeriod($acc, $startDate, $endDate)));
        $totalExpense = $expenseAccounts->sum(fn ($acc) => abs($this->getAccountBalanceForPeriod($acc, $startDate, $endDate)));
        $netIncome = $totalRevenue - $totalExpense;

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'total_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
            'net_income' => $netIncome,
            'profit_margin' => $totalRevenue > 0 ? round(($netIncome / $totalRevenue) * 100, 2) : 0,
        ]);
    }

    protected function getAccountBalanceForPeriod(Account $account, string $startDate, string $endDate): int
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

    public function kpis(): JsonResponse
    {
        $currentMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        // Current month revenue
        $currentRevenue = Invoice::where('status', Invoice::STATUS_PAID)
            ->whereMonth('invoice_date', $currentMonth->month)
            ->whereYear('invoice_date', $currentMonth->year)
            ->sum('total_amount');

        // Last month revenue
        $lastRevenue = Invoice::where('status', Invoice::STATUS_PAID)
            ->whereMonth('invoice_date', $lastMonth->month)
            ->whereYear('invoice_date', $lastMonth->year)
            ->sum('total_amount');

        // Average collection period (days) - database-agnostic
        $driver = DB::getDriverName();
        $avgDaysExpression = match ($driver) {
            'sqlite' => 'AVG(julianday(updated_at) - julianday(invoice_date))',
            'pgsql' => 'AVG(EXTRACT(EPOCH FROM (updated_at - invoice_date)) / 86400)',
            'mysql', 'mariadb' => 'AVG(DATEDIFF(updated_at, invoice_date))',
            default => 'AVG(DATEDIFF(updated_at, invoice_date))',
        };

        $avgCollectionDays = DB::table('invoices')
            ->whereNotNull('paid_amount')
            ->where('paid_amount', '>', 0)
            ->selectRaw("{$avgDaysExpression} as avg_days")
            ->value('avg_days') ?? 0;

        return response()->json([
            'revenue' => [
                'current_month' => $currentRevenue,
                'last_month' => $lastRevenue,
                'growth_percent' => $lastRevenue > 0
                    ? round((($currentRevenue - $lastRevenue) / $lastRevenue) * 100, 2)
                    : 0,
            ],
            'collection' => [
                'average_days' => round($avgCollectionDays),
                'overdue_invoices' => Invoice::where('status', Invoice::STATUS_OVERDUE)->count(),
            ],
            'customers' => [
                'total' => Contact::where('type', '!=', Contact::TYPE_SUPPLIER)->count(),
                'active_this_month' => Contact::whereHas('invoices', function ($q) use ($currentMonth) {
                    $q->whereMonth('invoice_date', $currentMonth->month)
                        ->whereYear('invoice_date', $currentMonth->year);
                })->count(),
            ],
        ]);
    }

    protected function getReceivablesSummary(): array
    {
        $outstanding = Invoice::query()
            ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])
            ->selectRaw('SUM(total_amount - paid_amount) as total, COUNT(*) as count')
            ->first();

        $overdue = Invoice::query()
            ->where('status', Invoice::STATUS_OVERDUE)
            ->selectRaw('SUM(total_amount - paid_amount) as total, COUNT(*) as count')
            ->first();

        return [
            'outstanding' => $outstanding->total ?? 0,
            'outstanding_count' => $outstanding->count ?? 0,
            'overdue' => $overdue->total ?? 0,
            'overdue_count' => $overdue->count ?? 0,
        ];
    }

    protected function getPayablesSummary(): array
    {
        $outstanding = Bill::query()
            ->whereIn('status', [Bill::STATUS_RECEIVED, Bill::STATUS_PARTIAL, Bill::STATUS_OVERDUE])
            ->selectRaw('SUM(total_amount - paid_amount) as total, COUNT(*) as count')
            ->first();

        $overdue = Bill::query()
            ->where('status', Bill::STATUS_OVERDUE)
            ->selectRaw('SUM(total_amount - paid_amount) as total, COUNT(*) as count')
            ->first();

        return [
            'outstanding' => $outstanding->total ?? 0,
            'outstanding_count' => $outstanding->count ?? 0,
            'overdue' => $overdue->total ?? 0,
            'overdue_count' => $overdue->count ?? 0,
        ];
    }

    protected function getCashPosition(): array
    {
        $cashAccounts = Account::where('type', Account::TYPE_ASSET)
            ->where('code', 'like', '1-1%')
            ->get();

        $totalCash = 0;
        $breakdown = [];

        foreach ($cashAccounts as $account) {
            $balance = $account->getBalance();
            $totalCash += $balance;
            $breakdown[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'balance' => $balance,
            ];
        }

        return [
            'total' => $totalCash,
            'accounts' => $breakdown,
        ];
    }

    protected function getRecentActivity(): array
    {
        $recentInvoices = Invoice::with('contact')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($inv) => [
                'type' => 'invoice',
                'id' => $inv->id,
                'number' => $inv->invoice_number,
                'contact' => $inv->contact->name,
                'amount' => $inv->total_amount,
                'status' => $inv->status,
                'date' => $inv->created_at->toIso8601String(),
            ]);

        $recentPayments = Payment::with('contact')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($pay) => [
                'type' => 'payment',
                'id' => $pay->id,
                'number' => $pay->payment_number,
                'contact' => $pay->contact->name,
                'amount' => $pay->amount,
                'status' => $pay->is_voided ? 'voided' : 'completed',
                'date' => $pay->created_at->toIso8601String(),
            ]);

        return $recentInvoices->concat($recentPayments)
            ->sortByDesc('date')
            ->take(10)
            ->values()
            ->toArray();
    }

    protected function getMonthlyComparison(): array
    {
        $months = collect();

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            $revenue = Invoice::query()
                ->where('status', Invoice::STATUS_PAID)
                ->whereBetween('invoice_date', [$monthStart, $monthEnd])
                ->sum('total_amount');

            $expenses = Bill::query()
                ->where('status', Bill::STATUS_PAID)
                ->whereBetween('bill_date', [$monthStart, $monthEnd])
                ->sum('total_amount');

            $months->push([
                'month' => $date->format('Y-m'),
                'label' => $date->format('M Y'),
                'revenue' => $revenue,
                'expenses' => $expenses,
                'profit' => $revenue - $expenses,
            ]);
        }

        return $months->toArray();
    }

    protected function getTopDebtors(int $limit): array
    {
        return DB::table('contacts')
            ->join('invoices', 'contacts.id', '=', 'invoices.contact_id')
            ->whereIn('contacts.type', [Contact::TYPE_CUSTOMER, Contact::TYPE_BOTH])
            ->whereIn('invoices.status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])
            ->whereNull('contacts.deleted_at')
            ->select(
                'contacts.id',
                'contacts.name',
                DB::raw('SUM(invoices.total_amount - invoices.paid_amount) as outstanding')
            )
            ->groupBy('contacts.id', 'contacts.name')
            ->having('outstanding', '>', 0)
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

    protected function getTopCreditors(int $limit): array
    {
        return DB::table('contacts')
            ->join('bills', 'contacts.id', '=', 'bills.contact_id')
            ->whereIn('contacts.type', [Contact::TYPE_SUPPLIER, Contact::TYPE_BOTH])
            ->whereIn('bills.status', [Bill::STATUS_RECEIVED, Bill::STATUS_PARTIAL, Bill::STATUS_OVERDUE])
            ->whereNull('contacts.deleted_at')
            ->select(
                'contacts.id',
                'contacts.name',
                DB::raw('SUM(bills.total_amount - bills.paid_amount) as outstanding')
            )
            ->groupBy('contacts.id', 'contacts.name')
            ->having('outstanding', '>', 0)
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
