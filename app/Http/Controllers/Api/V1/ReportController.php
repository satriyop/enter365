<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Accounting\Account;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Projects\Project;
use App\Services\Accounting\Reports\ReportServiceFactory;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function __construct(
        private ReportServiceFactory $reports
    ) {}

    /**
     * Neraca Saldo (Trial Balance).
     */
    public function trialBalance(Request $request): JsonResponse
    {
        Gate::authorize('reports.financial');

        $asOfDate = $request->input('as_of_date');
        $trialBalance = $this->reports->balance()->getTrialBalance($asOfDate);

        $totalDebit = $trialBalance->sum('debit_balance');
        $totalCredit = $trialBalance->sum('credit_balance');

        return $this->success([
            'report_name' => 'Neraca Saldo',
            'as_of_date' => $asOfDate ?? now()->toDateString(),
            'accounts' => $trialBalance->values(),
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => $totalDebit === $totalCredit,
        ]);
    }

    /**
     * Laporan Posisi Keuangan (Balance Sheet).
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        Gate::authorize('reports.financial');

        $asOfDate = $request->input('as_of_date');
        $compareTo = $request->input('compare_to');

        if ($compareTo) {
            $comparative = $this->reports->financial()->getComparativeBalanceSheet($asOfDate, $compareTo);

            return $this->success($comparative);
        }

        $balanceSheet = $this->reports->financial()->getBalanceSheet($asOfDate);

        return $this->success([
            'report_name' => 'Laporan Posisi Keuangan',
            ...$balanceSheet,
        ]);
    }

    /**
     * Laporan Laba Rugi (Income Statement).
     */
    public function incomeStatement(Request $request): JsonResponse
    {
        Gate::authorize('reports.financial');

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $comparePreviousPeriod = $request->boolean('compare_previous_period', false);

        if ($comparePreviousPeriod) {
            $comparative = $this->reports->financial()->getComparativeIncomeStatement(
                $startDate,
                $endDate,
                $request->input('previous_start_date'),
                $request->input('previous_end_date')
            );

            return $this->success($comparative);
        }

        $incomeStatement = $this->reports->financial()->getIncomeStatement($startDate, $endDate);

        return $this->success([
            'report_name' => 'Laporan Laba Rugi',
            ...$incomeStatement,
        ]);
    }

    /**
     * Buku Besar (General Ledger).
     *
     * @response array{data: array{report_name: string, start_date: string|null, end_date: string|null, accounts: list<array{account_id: int, code: string, name: string, type: string, opening_balance: int, entries: list<array{id: int, journal_entry_id: int, date: string, entry_number: string, description: string, reference: string|null, debit: int, credit: int, running_balance: int}>, closing_balance: int}>}}
     */
    public function generalLedger(Request $request): JsonResponse
    {
        Gate::authorize('reports.financial');

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $generalLedger = $this->reports->financial()->getGeneralLedger($startDate, $endDate);

        return $this->success([
            'report_name' => 'Buku Besar',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'accounts' => $generalLedger->values(),
        ]);
    }

    /**
     * Laporan Umur Piutang (Accounts Receivable Aging).
     *
     * @response array{data: array{report_name: string, as_of_date: string, buckets: list<array{label: string, min: int, max: int|null}>, contacts: list<array{contact_id: int, contact_code: string, contact_name: string, buckets: array<string, int>, invoice_count: int}>, totals: array<string, int>}}
     */
    public function receivableAging(Request $request): JsonResponse
    {
        Gate::authorize('reports.aging');

        $asOfDate = $request->input('as_of_date')
            ? Carbon::parse($request->input('as_of_date'))
            : null;

        $report = $this->reports->aging()->getReceivableAging($asOfDate);

        return $this->success([
            'report_name' => 'Laporan Umur Piutang',
            ...$report,
        ]);
    }

    /**
     * Laporan Umur Hutang (Accounts Payable Aging).
     *
     * @response array{data: array{report_name: string, as_of_date: string, buckets: list<array{label: string, min: int, max: int|null}>, contacts: list<array{contact_id: int, contact_code: string, contact_name: string, buckets: array<string, int>, bill_count: int}>, totals: array<string, int>}}
     */
    public function payableAging(Request $request): JsonResponse
    {
        Gate::authorize('reports.aging');

        $asOfDate = $request->input('as_of_date')
            ? Carbon::parse($request->input('as_of_date'))
            : null;

        $report = $this->reports->aging()->getPayableAging($asOfDate);

        return $this->success([
            'report_name' => 'Laporan Umur Hutang',
            ...$report,
        ]);
    }

    /**
     * Laporan Umur Piutang/Hutang per Kontak.
     */
    public function contactAging(Request $request, Contact $contact): JsonResponse
    {
        Gate::authorize('reports.aging');

        $asOfDate = $request->input('as_of_date')
            ? Carbon::parse($request->input('as_of_date'))
            : null;

        $report = $this->reports->aging()->getContactAging($contact, $asOfDate);

        return $this->success([
            'report_name' => 'Laporan Umur - '.$contact->name,
            'contact' => [
                'id' => $contact->id,
                'code' => $contact->code,
                'name' => $contact->name,
            ],
            'as_of_date' => ($asOfDate ?? now())->format('Y-m-d'),
            ...$report,
        ]);
    }

    /**
     * Laporan PPN (VAT Report).
     *
     * @response array{data: array{report_name: string, period: array{start: string, end: string}, output_tax: array{count: int, base: int, tax: int}, input_tax: array{count: int, base: int, tax: int}, net_tax: int, net_tax_status: string, details: array{invoices: list<array{date: string, number: string, contact: string, npwp: string|null, base: int, tax_rate: float, tax: int}>, bills: list<array{date: string, number: string, vendor_invoice: string|null, contact: string, npwp: string|null, base: int, tax_rate: float, tax: int}>}}}
     */
    public function ppnSummary(Request $request): JsonResponse
    {
        Gate::authorize('reports.tax');

        $startDate = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->input('end_date') ?? now()->endOfMonth()->toDateString();

        $report = $this->reports->tax()->getPpnSummary($startDate, $endDate);

        return $this->success([
            'report_name' => 'Laporan PPN',
            ...$report,
        ]);
    }

    /**
     * Laporan PPN Bulanan per Tahun.
     *
     * @response array{data: array{report_name: string, year: int, months: list<array{month: string, month_name: string, output: int, input: int, net: int}>, total_output: int, total_input: int, total_net: int}}
     */
    public function ppnMonthly(Request $request): JsonResponse
    {
        Gate::authorize('reports.tax');

        $year = (int) $request->input('year', now()->year);

        $report = $this->reports->tax()->getMonthlyPpnSummary($year);

        return $this->success([
            'report_name' => "Laporan PPN Tahun {$year}",
            'year' => $year,
            'months' => $report,
            'total_output' => $report->sum('output'),
            'total_input' => $report->sum('input'),
            'total_net' => $report->sum('net'),
        ]);
    }

    /**
     * Daftar Faktur Pajak Keluaran.
     *
     * @response array{data: array{report_name: string, period: array{start: string, end: string}, invoices: list<array{tanggal: string, nomor_faktur: string, nama_pembeli: string, npwp_pembeli: string, alamat: string, dpp: int, ppn: int, total: int}>, total_dpp: int, total_ppn: int}}
     */
    public function taxInvoiceList(Request $request): JsonResponse
    {
        Gate::authorize('reports.tax');

        $startDate = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->input('end_date') ?? now()->endOfMonth()->toDateString();

        $invoices = $this->reports->tax()->getTaxInvoiceList($startDate, $endDate);

        return $this->success([
            'report_name' => 'Daftar Faktur Pajak Keluaran',
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'invoices' => $invoices,
            'total_dpp' => $invoices->sum('dpp'),
            'total_ppn' => $invoices->sum('ppn'),
        ]);
    }

    /**
     * Daftar Faktur Pajak Masukan.
     *
     * @response array{data: array{report_name: string, period: array{start: string, end: string}, bills: list<array{tanggal: string, nomor_faktur_vendor: string, nomor_internal: string, nama_penjual: string, npwp_penjual: string, dpp: int, ppn: int, total: int}>, total_dpp: int, total_ppn: int}}
     */
    public function inputTaxList(Request $request): JsonResponse
    {
        Gate::authorize('reports.tax');

        $startDate = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->input('end_date') ?? now()->endOfMonth()->toDateString();

        $bills = $this->reports->tax()->getInputTaxList($startDate, $endDate);

        return $this->success([
            'report_name' => 'Daftar Faktur Pajak Masukan',
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'bills' => $bills,
            'total_dpp' => $bills->sum('dpp'),
            'total_ppn' => $bills->sum('ppn'),
        ]);
    }

    /**
     * Laporan Arus Kas (Cash Flow Statement).
     *
     * @response array{data: array{report_name: string, period: array{start: string, end: string}, operating: array{items: list<array{description: string, amount: int}>, subtotal: int}, investing: array{items: list<array{description: string, amount: int}>, subtotal: int}, financing: array{items: list<array{description: string, amount: int}>, subtotal: int}, net_cash_flow: int, beginning_cash: int, ending_cash: int}}
     */
    public function cashFlow(Request $request): JsonResponse
    {
        Gate::authorize('reports.cash_flow');

        $startDate = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->input('end_date') ?? now()->endOfMonth()->toDateString();

        $report = $this->reports->cashFlow()->generateCashFlow($startDate, $endDate);

        return $this->success([
            'report_name' => 'Laporan Arus Kas',
            ...$report,
        ]);
    }

    /**
     * Pergerakan Kas Harian.
     *
     * @response array{data: array{report_name: string, period: array{start: string, end: string}, movements: list<array{date: string, receipts: int, payments: int, net: int, balance: int}>, total_receipts: int, total_payments: int, net_movement: int}}
     */
    public function dailyCashMovement(Request $request): JsonResponse
    {
        Gate::authorize('reports.cash_flow');

        $startDate = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->input('end_date') ?? now()->endOfMonth()->toDateString();

        $movements = $this->reports->cashFlow()->getDailyCashMovement($startDate, $endDate);

        return $this->success([
            'report_name' => 'Pergerakan Kas Harian',
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'movements' => $movements,
            'total_receipts' => $movements->sum('receipts'),
            'total_payments' => $movements->sum('payments'),
            'net_movement' => $movements->sum('net'),
        ]);
    }

    /**
     * Laporan Profitabilitas Proyek (Project Profitability Report).
     *
     * @response array{data: array{report_name: string, period: array{start: string|null, end: string|null}, projects: list<array{id: int, project_number: string, name: string, customer: string|null, status: string, start_date: string|null, end_date: string|null, contract_amount: int, total_revenue: int, costs: array{material: int, labor: int, subcontractor: int, equipment: int, overhead: int, other: int, total: int}, gross_profit: int, profit_margin: float, budget_amount: int, budget_variance: int, budget_utilization: float, is_over_budget: bool, progress_percentage: float}>, totals: array{total_contract: int, total_revenue: int, total_costs: int, total_profit: int, average_margin: float, projects_count: int, profitable_count: int, loss_count: int}}}
     */
    public function projectProfitability(Request $request): JsonResponse
    {
        Gate::authorize('reports.project');

        $report = $this->reports->project()->getProjectProfitabilitySummary(
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('status')
        );

        return $this->success($report);
    }

    /**
     * Laporan Detail Profitabilitas Proyek.
     *
     * @response array{data: array{report_name: string, project: array{id: int, project_number: string, name: string, description: string|null, customer: array{id: int, name: string, code: string}|null, status: string, priority: string|null, location: string|null, manager_id: int|null}, financials: array{contract_amount: int, budget_amount: int, total_revenue: int, total_cost: int, gross_profit: int, profit_margin: float, budget_variance: int, budget_utilization: float, is_over_budget: bool}, cost_breakdown: array{material: int, labor: int, subcontractor: int, equipment: int, overhead: int, other: int, total: int}, revenue_breakdown: array{items: list<array{type: string, total: int, count: int}>, total: int}, timeline: array{planned_start: string|null, planned_end: string|null, actual_start: string|null, actual_end: string|null, duration_days: int, days_until_deadline: int|null, is_overdue: bool}, progress: array{percentage: float, work_orders_count: int, work_orders_completed: int, invoices_count: int, invoices_paid: int}, monthly_costs: array<string, int>, kpis: array{cost_per_progress: float, revenue_per_progress: float, burn_rate: int}}}
     */
    public function projectProfitabilityDetail(Project $project): JsonResponse
    {
        Gate::authorize('reports.project');

        $report = $this->reports->project()->getProjectProfitabilityDetail($project);

        return $this->success($report);
    }

    /**
     * Laporan Analisis Biaya Proyek.
     *
     * @response array{data: array{report_name: string, period: array{start: string|null, end: string|null}, by_type: array<string, array{total: int, count: int, label: string}>, by_project: list<array{project_id: int, project_number: string|null, project_name: string|null, total_cost: int}>, totals: array{grand_total: int, cost_types_count: int, projects_count: int}}}
     */
    public function projectCostAnalysis(Request $request): JsonResponse
    {
        Gate::authorize('reports.project');

        $report = $this->reports->project()->getProjectCostAnalysis(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return $this->success($report);
    }

    /**
     * Laporan Biaya Work Order.
     */
    public function workOrderCosts(Request $request): JsonResponse
    {
        Gate::authorize('reports.manufacturing');

        $report = $this->reports->workOrder()->getWorkOrderCostSummary(
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('status'),
            $request->input('project_id')
        );

        return $this->success($report);
    }

    /**
     * Laporan Detail Biaya Work Order.
     */
    public function workOrderCostDetail(WorkOrder $workOrder): JsonResponse
    {
        Gate::authorize('reports.manufacturing');

        $report = $this->reports->workOrder()->getWorkOrderCostDetail($workOrder);

        return $this->success($report);
    }

    /**
     * Laporan Variansi Biaya.
     *
     * @response array{data: array{report_name: string, period: array{start: string|null, end: string|null}, over_budget: list<array{id: int, wo_number: string, name: string, project_number: string|null, status: string, estimated: int, actual: int, variance: int, variance_percent: float}>, under_budget: list<array{id: int, wo_number: string, name: string, project_number: string|null, status: string, estimated: int, actual: int, variance: int, variance_percent: float}>, on_budget: list<array{id: int, wo_number: string, name: string, project_number: string|null, status: string, estimated: int, actual: int, variance: int, variance_percent: float}>, summary: array{total_work_orders: int, over_budget_count: int, under_budget_count: int, on_budget_count: int, total_estimated: int, total_actual: int, total_variance: int, overall_variance_percent: float}}}
     */
    public function costVariance(Request $request): JsonResponse
    {
        Gate::authorize('reports.manufacturing');

        $report = $this->reports->workOrder()->getCostVarianceReport(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return $this->success($report);
    }

    /**
     * Laporan Subkontraktor.
     *
     * @response array{data: array{report_name: string, period: array{start: string|null, end: string|null}, subcontractors: list<array{id: int, code: string, name: string, work_orders: array{total: int, completed: int, in_progress: int, draft: int}, financials: array{total_agreed: int, total_actual: int, total_invoiced: int, total_paid: int, outstanding: int, retention_held: int}, performance: array{on_time_completion: float, average_completion_days: int}}>, totals: array{total_subcontractors: int, total_agreed: int, total_paid: int, total_outstanding: int, total_retention: int}}}
     */
    public function subcontractorSummary(Request $request): JsonResponse
    {
        Gate::authorize('reports.manufacturing');

        $report = $this->reports->subcontractor()->getSubcontractorSummary(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return $this->success($report);
    }

    /**
     * Laporan Detail Subkontraktor.
     *
     * @response array{data: array{report_name: string, subcontractor: array{id: int, code: string, name: string, phone: string|null, email: string|null, hourly_rate: int|null, daily_rate: int|null}, period: array{start: string|null, end: string|null}, work_orders: list<array{id: int, sc_wo_number: string, name: string, project_number: string|null, project_name: string|null, status: string, agreed_amount: int, actual_amount: int, retention_amount: int, amount_invoiced: int, amount_paid: int, scheduled_start: string|null, scheduled_end: string|null, actual_start: string|null, actual_end: string|null, completion_percentage: float}>, invoices: list<array{id: int, invoice_number: string, invoice_date: string|null, amount: int, status: string, sc_wo_number: string|null}>, summary: array{total_work_orders: int, completed_work_orders: int, total_agreed: int, total_actual: int, total_invoiced: int, total_paid: int, outstanding: int, retention_held: int}}}
     */
    public function subcontractorDetail(Request $request, Contact $contact): JsonResponse
    {
        Gate::authorize('reports.manufacturing');

        $report = $this->reports->subcontractor()->getSubcontractorDetail(
            $contact,
            $request->input('start_date'),
            $request->input('end_date')
        );

        return $this->success($report);
    }

    /**
     * Laporan Retensi Subkontraktor.
     *
     * @response array{data: array{report_name: string, retentions: list<array{id: int, sc_wo_number: string, name: string, subcontractor_name: string|null, project_number: string|null, status: string, agreed_amount: int, retention_percent: float, retention_amount: int, scheduled_end: string|null, actual_end: string|null, is_releasable: bool}>, by_subcontractor: list<array{subcontractor: string, total_retention: int, work_orders_count: int, releasable_amount: int}>, totals: array{total_retention_held: int, releasable_amount: int, pending_amount: int, work_orders_count: int}}}
     */
    public function subcontractorRetention(): JsonResponse
    {
        Gate::authorize('reports.manufacturing');

        $report = $this->reports->subcontractor()->getRetentionSummary();

        return $this->success($report);
    }

    /**
     * Laporan Perubahan Ekuitas (Statement of Changes in Equity).
     *
     * @response array{data: array{report_name: string, period_start: string, period_end: string, opening_equity: array{items: list<array{account_id: int, code: string, name: string, subtype: string, balance: int}>, total: int}, changes: array{capital_additions: int, capital_withdrawals: int, net_income: int, dividends: int, other_adjustments: int, total_changes: int}, closing_equity: array{items: list<array{account_id: int, code: string, name: string, subtype: string, balance: int}>, total: int}}}
     */
    public function changesInEquity(Request $request): JsonResponse
    {
        Gate::authorize('reports.financial');

        $report = $this->reports->financial()->getStatementOfChangesInEquity(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return $this->success([
            'report_name' => 'Laporan Perubahan Ekuitas',
            ...$report,
        ]);
    }

    /**
     * Laporan Rekonsiliasi Bank.
     *
     * @response array{data: array{report_name: string, account: array{id: int, code: string, name: string}, as_of_date: string, book_balance: int, bank_balance: int, adjustments_to_book: array{items: list<array{id: int, date: string, description: string, reference: string|null, amount: int, type: string}>, total: int}, adjustments_to_bank: array{items: list<array{id: int, type: string, date: string, number: string, description: string|null, amount: int}>, total: int}, adjusted_book_balance: int, adjusted_bank_balance: int, difference: int, is_reconciled: bool, reconciliation_summary: array{total: int, reconciled: int, matched: int, unmatched: int}}}
     */
    public function bankReconciliation(Request $request, Account $account): JsonResponse
    {
        Gate::authorize('reports.financial');

        $report = $this->reports->bankReconciliation()->getReconciliationReport(
            $account,
            $request->input('as_of_date')
        );

        return $this->success([
            'report_name' => 'Laporan Rekonsiliasi Bank',
            ...$report,
        ]);
    }

    /**
     * Item Outstanding untuk Rekonsiliasi Bank.
     *
     * @response array{data: array{report_name: string, account: array{id: int, code: string, name: string}, as_of_date: string, outstanding_deposits: list<array{id: int, date: string, number: string, description: string|null, amount: int}>, outstanding_checks: list<array{id: int, date: string, number: string, description: string|null, amount: int}>, unmatched_bank_transactions: list<array{id: int, date: string, description: string, reference: string|null, debit: int, credit: int, net_amount: int}>, unmatched_book_entries: list<array{id: int, journal_entry_id: int, date: string, journal_number: string, description: string, debit: int, credit: int}>}}
     */
    public function bankReconciliationOutstanding(Request $request, Account $account): JsonResponse
    {
        Gate::authorize('reports.financial');

        $report = $this->reports->bankReconciliation()->getOutstandingItems(
            $account,
            $request->input('as_of_date')
        );

        return $this->success([
            'report_name' => 'Item Outstanding Rekonsiliasi',
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
            ],
            'as_of_date' => $request->input('as_of_date') ?? now()->toDateString(),
            ...$report,
        ]);
    }

    /**
     * Laporan Ringkasan HPP (COGS Summary).
     *
     * @response array{data: array{report_name: string, period: array{start: string, end: string}, beginning_inventory: int, purchases: int, goods_available: int, ending_inventory: int, cogs: int, cogs_from_movements: int}}
     */
    public function cogsSummary(Request $request): JsonResponse
    {
        Gate::authorize('reports.cogs');

        $report = $this->reports->cogs()->getCOGSSummary(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return $this->success([
            'report_name' => 'Laporan Harga Pokok Penjualan',
            ...$report,
        ]);
    }

    /**
     * Laporan HPP per Produk.
     *
     * @response array{data: array{report_name: string, period: array{start: string, end: string}, products: list<array{product_id: int, sku: string, name: string, category: string|null, quantity_sold: int, average_unit_cost: int, total_cogs: int, percentage: float}>, total_cogs: int}}
     */
    public function cogsByProduct(Request $request): JsonResponse
    {
        Gate::authorize('reports.cogs');

        $products = $this->reports->cogs()->getCOGSByProduct(
            $request->input('start_date'),
            $request->input('end_date')
        );

        $startDate = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->input('end_date') ?? now()->endOfMonth()->toDateString();

        return $this->success([
            'report_name' => 'Laporan HPP per Produk',
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'products' => $products,
            'total_cogs' => $products->sum('total_cogs'),
        ]);
    }

    /**
     * Laporan HPP per Kategori.
     *
     * @response array{data: array{report_name: string, period: array{start: string, end: string}, categories: list<array{category_id: int|null, category_name: string, product_count: int, quantity_sold: int, total_cogs: int, percentage: float}>, total_cogs: int}}
     */
    public function cogsByCategory(Request $request): JsonResponse
    {
        Gate::authorize('reports.cogs');

        $categories = $this->reports->cogs()->getCOGSByCategory(
            $request->input('start_date'),
            $request->input('end_date')
        );

        $startDate = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->input('end_date') ?? now()->endOfMonth()->toDateString();

        return $this->success([
            'report_name' => 'Laporan HPP per Kategori',
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'categories' => $categories,
            'total_cogs' => $categories->sum('total_cogs'),
        ]);
    }

    /**
     * Laporan Trend HPP Bulanan.
     *
     * @response array{data: array{report_name: string, year: int, months: list<array{month: string, month_name: string, beginning_inventory: int, purchases: int, ending_inventory: int, cogs: int}>, total_cogs: int}}
     */
    public function cogsMonthlyTrend(Request $request): JsonResponse
    {
        Gate::authorize('reports.cogs');

        $year = (int) $request->input('year', now()->year);
        $months = $this->reports->cogs()->getMonthlyCOGSTrend($year);

        return $this->success([
            'report_name' => "Trend HPP Tahun {$year}",
            'year' => $year,
            'months' => $months,
            'total_cogs' => $months->sum('cogs'),
        ]);
    }

    /**
     * Laporan Detail HPP Produk.
     *
     * @response array{data: array{report_name: string, product: array{id: int, sku: string, name: string}, period: array{start: string, end: string}, movements: list<array{id: int, date: string, movement_number: string, reference_type: string|null, reference_id: int|null, quantity: float, unit_cost: int, total_cost: int, notes: string|null}>, total_quantity: float, total_cogs: int}}
     */
    public function productCOGSDetail(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('reports.cogs');

        $details = $this->reports->cogs()->getProductCOGSDetail(
            $product,
            $request->input('start_date'),
            $request->input('end_date')
        );

        $startDate = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->input('end_date') ?? now()->endOfMonth()->toDateString();

        return $this->success([
            'report_name' => 'Detail HPP Produk',
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
            ],
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'movements' => $details,
            'total_quantity' => $details->sum('quantity'),
            'total_cogs' => $details->sum('total_cost'),
        ]);
    }
}
