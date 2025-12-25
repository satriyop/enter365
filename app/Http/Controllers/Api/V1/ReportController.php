<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Contact;
use App\Services\Accounting\AccountBalanceService;
use App\Services\Accounting\AgingReportService;
use App\Services\Accounting\CashFlowReportService;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\TaxReportService;
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
        private CashFlowReportService $cashFlowService
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
            'report_name' => 'Laporan Umur - ' . $contact->name,
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
}
