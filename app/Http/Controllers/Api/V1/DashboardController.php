<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Contacts\Contact;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use App\Services\Accounting\Reports\AgingReportService;
use App\Services\Shared\DashboardQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __construct(
        private AgingReportService $agingService,
        private DashboardQueryService $dashboardQuery
    ) {}

    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('dashboard.view');

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        return $this->success([
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
        Gate::authorize('dashboard.financials');

        $invoices = Invoice::query()
            ->whereIn('status', [DocumentStatus::Sent, DocumentStatus::Partial, DocumentStatus::Overdue])
            ->with('contact')
            ->orderBy('due_date')
            ->get();

        $total = $invoices->sum(fn ($inv) => $inv->total_amount - $inv->paid_amount);
        $overdue = $invoices->where('status', DocumentStatus::Overdue)->sum(fn ($inv) => $inv->total_amount - $inv->paid_amount);

        return $this->success([
            'total_outstanding' => $total,
            'total_overdue' => $overdue,
            'count' => $invoices->count(),
            'overdue_count' => $invoices->where('status', DocumentStatus::Overdue)->count(),
            'aging' => $this->agingService->getReceivableAging(),
            'top_debtors' => $this->getTopDebtors(5),
        ]);
    }

    public function payables(): JsonResponse
    {
        Gate::authorize('dashboard.financials');

        $bills = Bill::query()
            ->whereIn('status', [DocumentStatus::Received, DocumentStatus::Partial, DocumentStatus::Overdue])
            ->with('contact')
            ->orderBy('due_date')
            ->get();

        $total = $bills->sum(fn ($bill) => $bill->total_amount - $bill->paid_amount);
        $overdue = $bills->where('status', DocumentStatus::Overdue)->sum(fn ($bill) => $bill->total_amount - $bill->paid_amount);

        return $this->success([
            'total_outstanding' => $total,
            'total_overdue' => $overdue,
            'count' => $bills->count(),
            'overdue_count' => $bills->where('status', DocumentStatus::Overdue)->count(),
            'aging' => $this->agingService->getPayableAging(),
            'top_creditors' => $this->getTopCreditors(5),
        ]);
    }

    public function cashFlow(Request $request): JsonResponse
    {
        Gate::authorize('dashboard.financials');

        $days = (int) $request->input('days', 30);
        $startDate = now()->subDays($days);
        $endDate = now();

        $cashAccounts = Account::where('type', Account::TYPE_ASSET)
            ->where('code', 'like', '1-1%')
            ->pluck('id');

        $dailyMovement = $this->dashboardQuery->getCashFlowDailyMovement(
            $cashAccounts,
            $startDate,
            $endDate
        );

        return $this->success([
            'period_days' => $days,
            'total_inflow' => $dailyMovement->sum('inflow'),
            'total_outflow' => $dailyMovement->sum('outflow'),
            'net_flow' => $dailyMovement->sum('inflow') - $dailyMovement->sum('outflow'),
            'daily_movement' => $dailyMovement,
        ]);
    }

    public function profitLoss(Request $request): JsonResponse
    {
        Gate::authorize('dashboard.financials');

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        // Calculate revenue and expense from account balances
        $revenueAccounts = Account::where('type', Account::TYPE_REVENUE)->where('is_active', true)->get();
        $expenseAccounts = Account::where('type', Account::TYPE_EXPENSE)->where('is_active', true)->get();

        $totalRevenue = $revenueAccounts->sum(fn ($acc) => abs($this->dashboardQuery->getAccountBalanceForPeriod($acc, $startDate, $endDate)));
        $totalExpense = $expenseAccounts->sum(fn ($acc) => abs($this->dashboardQuery->getAccountBalanceForPeriod($acc, $startDate, $endDate)));
        $netIncome = $totalRevenue - $totalExpense;

        return $this->success([
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

    public function kpis(): JsonResponse
    {
        Gate::authorize('dashboard.kpis');

        $currentMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        // Current month revenue
        $currentRevenue = Invoice::where('status', DocumentStatus::Paid)
            ->whereMonth('invoice_date', $currentMonth->month)
            ->whereYear('invoice_date', $currentMonth->year)
            ->sum('total_amount');

        // Last month revenue
        $lastRevenue = Invoice::where('status', DocumentStatus::Paid)
            ->whereMonth('invoice_date', $lastMonth->month)
            ->whereYear('invoice_date', $lastMonth->year)
            ->sum('total_amount');

        $avgCollectionDays = $this->dashboardQuery->getAverageCollectionDays();

        return $this->success([
            'revenue' => [
                'current_month' => $currentRevenue,
                'last_month' => $lastRevenue,
                'growth_percent' => $lastRevenue > 0
                    ? round((($currentRevenue - $lastRevenue) / $lastRevenue) * 100, 2)
                    : 0,
            ],
            'collection' => [
                'average_days' => round($avgCollectionDays),
                'overdue_invoices' => Invoice::where('status', DocumentStatus::Overdue)->count(),
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
            ->whereIn('status', [DocumentStatus::Sent->value, DocumentStatus::Partial->value, DocumentStatus::Overdue->value])
            ->selectRaw('SUM(total_amount - paid_amount) as total, COUNT(*) as count')
            ->first();

        $overdue = Invoice::query()
            ->where('status', DocumentStatus::Overdue->value)
            ->selectRaw('SUM(total_amount - paid_amount) as total, COUNT(*) as count')
            ->first();

        return [
            'outstanding' => (int) ($outstanding->total ?? 0),
            'outstanding_count' => (int) ($outstanding->count ?? 0),
            'overdue' => (int) ($overdue->total ?? 0),
            'overdue_count' => (int) ($overdue->count ?? 0),
        ];
    }

    protected function getPayablesSummary(): array
    {
        $outstanding = Bill::query()
            ->whereIn('status', [DocumentStatus::Received->value, DocumentStatus::Partial->value, DocumentStatus::Overdue->value])
            ->selectRaw('SUM(total_amount - paid_amount) as total, COUNT(*) as count')
            ->first();

        $overdue = Bill::query()
            ->where('status', DocumentStatus::Overdue->value)
            ->selectRaw('SUM(total_amount - paid_amount) as total, COUNT(*) as count')
            ->first();

        return [
            'outstanding' => (int) ($outstanding->total ?? 0),
            'outstanding_count' => (int) ($outstanding->count ?? 0),
            'overdue' => (int) ($overdue->total ?? 0),
            'overdue_count' => (int) ($overdue->count ?? 0),
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
                ->where('status', DocumentStatus::Paid)
                ->whereBetween('invoice_date', [$monthStart, $monthEnd])
                ->sum('total_amount');

            $expenses = Bill::query()
                ->where('status', DocumentStatus::Paid)
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
        return $this->dashboardQuery->getTopDebtors(
            $limit,
            [Contact::TYPE_CUSTOMER, Contact::TYPE_BOTH],
            [DocumentStatus::Sent->value, DocumentStatus::Partial->value, DocumentStatus::Overdue->value]
        );
    }

    protected function getTopCreditors(int $limit): array
    {
        return $this->dashboardQuery->getTopCreditors(
            $limit,
            [Contact::TYPE_SUPPLIER, Contact::TYPE_BOTH],
            [DocumentStatus::Received->value, DocumentStatus::Partial->value, DocumentStatus::Overdue->value]
        );
    }
}
