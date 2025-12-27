<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\Contact;
use App\Models\Accounting\Product;
use App\Models\Accounting\Project;
use App\Models\Accounting\WorkOrder;
use App\Services\Accounting\AccountBalanceService;
use App\Services\Accounting\AgingReportService;
use App\Services\Accounting\BankReconciliationReportService;
use App\Services\Accounting\CashFlowReportService;
use App\Services\Accounting\COGSReportService;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\ProjectReportService;
use App\Services\Accounting\SubcontractorReportService;
use App\Services\Accounting\TaxReportService;
use App\Services\Accounting\WorkOrderReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private AccountBalanceService $balanceService,
        private FinancialReportService $reportService,
        private AgingReportService $agingService,
        private TaxReportService $taxService,
        private CashFlowReportService $cashFlowService,
        private ProjectReportService $projectReportService,
        private WorkOrderReportService $workOrderReportService,
        private SubcontractorReportService $subcontractorReportService,
        private BankReconciliationReportService $bankReconciliationService,
        private COGSReportService $cogsService
    ) {}

    /**
     * Neraca Saldo (Trial Balance).
     */
    public function trialBalance(Request $request): JsonResponse
    {
        $asOfDate = $request->input('as_of_date');
        $trialBalance = $this->balanceService->getTrialBalance($asOfDate);

        $totalDebit = $trialBalance->sum('debit_balance');
        $totalCredit = $trialBalance->sum('credit_balance');

        return response()->json([
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
        $asOfDate = $request->input('as_of_date');
        $compareTo = $request->input('compare_to');

        // If compare_to is provided, return comparative report
        if ($compareTo) {
            $comparative = $this->reportService->getComparativeBalanceSheet($asOfDate, $compareTo);

            return response()->json($comparative);
        }

        $balanceSheet = $this->reportService->getBalanceSheet($asOfDate);

        return response()->json([
            'report_name' => 'Laporan Posisi Keuangan',
            ...$balanceSheet,
        ]);
    }

    /**
     * Laporan Laba Rugi (Income Statement).
     */
    public function incomeStatement(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $comparePreviousPeriod = $request->boolean('compare_previous_period', false);

        // If compare_previous_period is true, return comparative report
        if ($comparePreviousPeriod) {
            $comparative = $this->reportService->getComparativeIncomeStatement(
                $startDate,
                $endDate,
                $request->input('previous_start_date'),
                $request->input('previous_end_date')
            );

            return response()->json($comparative);
        }

        $incomeStatement = $this->reportService->getIncomeStatement($startDate, $endDate);

        return response()->json([
            'report_name' => 'Laporan Laba Rugi',
            ...$incomeStatement,
        ]);
    }

    /**
     * Buku Besar (General Ledger).
     */
    public function generalLedger(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $generalLedger = $this->reportService->getGeneralLedger($startDate, $endDate);

        return response()->json([
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
        $asOfDate = $request->input('as_of_date')
            ? Carbon::parse($request->input('as_of_date'))
            : null;

        $report = $this->agingService->getReceivableAging($asOfDate);

        return response()->json([
            'report_name' => 'Laporan Umur Piutang',
            ...$report,
        ]);
    }

    /**
     * Laporan Umur Hutang (Accounts Payable Aging).
     */
    public function payableAging(Request $request): JsonResponse
    {
        $asOfDate = $request->input('as_of_date')
            ? Carbon::parse($request->input('as_of_date'))
            : null;

        $report = $this->agingService->getPayableAging($asOfDate);

        return response()->json([
            'report_name' => 'Laporan Umur Hutang',
            ...$report,
        ]);
    }

    /**
     * Laporan Umur Piutang/Hutang per Kontak.
     */
    public function contactAging(Request $request, Contact $contact): JsonResponse
    {
        $asOfDate = $request->input('as_of_date')
            ? Carbon::parse($request->input('as_of_date'))
            : null;

        $report = $this->agingService->getContactAging($contact, $asOfDate);

        return response()->json([
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
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : now()->startOfMonth();
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : now()->endOfMonth();

        $report = $this->taxService->getPpnSummary($startDate, $endDate);

        return response()->json([
            'report_name' => 'Laporan PPN',
            ...$report,
        ]);
    }

    /**
     * Laporan PPN Bulanan per Tahun.
     */
    public function ppnMonthly(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', now()->year);

        $report = $this->taxService->getMonthlyPpnSummary($year);

        return response()->json([
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
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : now()->startOfMonth();
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : now()->endOfMonth();

        $invoices = $this->taxService->getTaxInvoiceList($startDate, $endDate);

        return response()->json([
            'report_name' => 'Daftar Faktur Pajak Keluaran',
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
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
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : now()->startOfMonth();
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : now()->endOfMonth();

        $bills = $this->taxService->getInputTaxList($startDate, $endDate);

        return response()->json([
            'report_name' => 'Daftar Faktur Pajak Masukan',
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
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
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : now()->startOfMonth();
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : now()->endOfMonth();

        $report = $this->cashFlowService->generateCashFlow($startDate, $endDate);

        return response()->json([
            'report_name' => 'Laporan Arus Kas',
            ...$report,
        ]);
    }

    /**
     * Pergerakan Kas Harian.
     */
    public function dailyCashMovement(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : now()->startOfMonth();
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : now()->endOfMonth();

        $movements = $this->cashFlowService->getDailyCashMovement($startDate, $endDate);

        return response()->json([
            'report_name' => 'Pergerakan Kas Harian',
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
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
        $report = $this->projectReportService->getProjectProfitabilitySummary(
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('status')
        );

        return response()->json($report);
    }

    /**
     * Laporan Detail Profitabilitas Proyek.
     */
    public function projectProfitabilityDetail(Project $project): JsonResponse
    {
        $report = $this->projectReportService->getProjectProfitabilityDetail($project);

        return response()->json($report);
    }

    /**
     * Laporan Analisis Biaya Proyek.
     */
    public function projectCostAnalysis(Request $request): JsonResponse
    {
        $report = $this->projectReportService->getProjectCostAnalysis(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json($report);
    }

    /**
     * Laporan Biaya Work Order.
     */
    public function workOrderCosts(Request $request): JsonResponse
    {
        $report = $this->workOrderReportService->getWorkOrderCostSummary(
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('status'),
            $request->input('project_id')
        );

        return response()->json($report);
    }

    /**
     * Laporan Detail Biaya Work Order.
     */
    public function workOrderCostDetail(WorkOrder $workOrder): JsonResponse
    {
        $report = $this->workOrderReportService->getWorkOrderCostDetail($workOrder);

        return response()->json($report);
    }

    /**
     * Laporan Variansi Biaya.
     */
    public function costVariance(Request $request): JsonResponse
    {
        $report = $this->workOrderReportService->getCostVarianceReport(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json($report);
    }

    /**
     * Laporan Subkontraktor.
     */
    public function subcontractorSummary(Request $request): JsonResponse
    {
        $report = $this->subcontractorReportService->getSubcontractorSummary(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json($report);
    }

    /**
     * Laporan Detail Subkontraktor.
     */
    public function subcontractorDetail(Request $request, Contact $contact): JsonResponse
    {
        $report = $this->subcontractorReportService->getSubcontractorDetail(
            $contact,
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json($report);
    }

    /**
     * Laporan Retensi Subkontraktor.
     */
    public function subcontractorRetention(): JsonResponse
    {
        $report = $this->subcontractorReportService->getRetentionSummary();

        return response()->json($report);
    }

    /**
     * Laporan Perubahan Ekuitas (Statement of Changes in Equity).
     */
    public function changesInEquity(Request $request): JsonResponse
    {
        $report = $this->reportService->getStatementOfChangesInEquity(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json([
            'report_name' => 'Laporan Perubahan Ekuitas',
            ...$report,
        ]);
    }

    /**
     * Laporan Rekonsiliasi Bank.
     */
    public function bankReconciliation(Request $request, Account $account): JsonResponse
    {
        $report = $this->bankReconciliationService->getReconciliationReport(
            $account,
            $request->input('as_of_date')
        );

        return response()->json([
            'report_name' => 'Laporan Rekonsiliasi Bank',
            ...$report,
        ]);
    }

    /**
     * Item Outstanding untuk Rekonsiliasi Bank.
     */
    public function bankReconciliationOutstanding(Request $request, Account $account): JsonResponse
    {
        $report = $this->bankReconciliationService->getOutstandingItems(
            $account,
            $request->input('as_of_date')
        );

        return response()->json([
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
        $report = $this->cogsService->getCOGSSummary(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json([
            'report_name' => 'Laporan Harga Pokok Penjualan',
            ...$report,
        ]);
    }

    /**
     * Laporan HPP per Produk.
     */
    public function cogsByProduct(Request $request): JsonResponse
    {
        $products = $this->cogsService->getCOGSByProduct(
            $request->input('start_date'),
            $request->input('end_date')
        );

        $startDate = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->input('end_date') ?? now()->endOfMonth()->toDateString();

        return response()->json([
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
        $categories = $this->cogsService->getCOGSByCategory(
            $request->input('start_date'),
            $request->input('end_date')
        );

        $startDate = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->input('end_date') ?? now()->endOfMonth()->toDateString();

        return response()->json([
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
        $year = (int) $request->input('year', now()->year);
        $months = $this->cogsService->getMonthlyCOGSTrend($year);

        return response()->json([
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
        $details = $this->cogsService->getProductCOGSDetail(
            $product,
            $request->input('start_date'),
            $request->input('end_date')
        );

        $startDate = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->input('end_date') ?? now()->endOfMonth()->toDateString();

        return response()->json([
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
