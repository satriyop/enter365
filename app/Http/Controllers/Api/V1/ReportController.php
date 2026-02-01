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
     */
    public function projectProfitabilityDetail(Project $project): JsonResponse
    {
        Gate::authorize('reports.project');

        $report = $this->reports->project()->getProjectProfitabilityDetail($project);

        return $this->success($report);
    }

    /**
     * Laporan Analisis Biaya Proyek.
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
     */
    public function subcontractorRetention(): JsonResponse
    {
        Gate::authorize('reports.manufacturing');

        $report = $this->reports->subcontractor()->getRetentionSummary();

        return $this->success($report);
    }

    /**
     * Laporan Perubahan Ekuitas (Statement of Changes in Equity).
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
