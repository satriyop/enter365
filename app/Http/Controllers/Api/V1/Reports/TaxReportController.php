<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\Controller;
use App\Services\Accounting\Reports\ReportServiceFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxReportController extends Controller
{
    public function __construct(
        private ReportServiceFactory $reports
    ) {}

    /**
     * Laporan PPN (VAT Report).
     *
     * @response array{data: array{report_name: string, period: array{start: string, end: string}, output_tax: array{count: int, base: int, tax: int}, input_tax: array{count: int, base: int, tax: int}, net_tax: int, net_tax_status: string, details: array{invoices: list<array{date: string, number: string, contact: string, npwp: string|null, base: int, tax_rate: float, tax: int}>, bills: list<array{date: string, number: string, vendor_invoice: string|null, contact: string, npwp: string|null, base: int, tax_rate: float, tax: int}>}}}
     */
    public function ppnSummary(Request $request): JsonResponse
    {
        $this->authorize('reports.tax');

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
        $this->authorize('reports.tax');

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
        $this->authorize('reports.tax');

        $startDate = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->input('end_date') ?? now()->endOfMonth()->toDateString();

        $invoices = $this->reports->tax()->getTaxInvoiceList($startDate, $endDate);

        $totalDpp = $invoices->sum('dpp');
        $totalPpn = $invoices->sum('ppn');

        return $this->success([
            'report_name' => 'Daftar Faktur Pajak Keluaran',
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'items' => $invoices->values(),
            'total_dpp' => $totalDpp,
            'total_ppn' => $totalPpn,
            'total' => $totalDpp + $totalPpn,
        ]);
    }

    /**
     * Daftar Faktur Pajak Masukan.
     *
     * @response array{data: array{report_name: string, period: array{start: string, end: string}, bills: list<array{tanggal: string, nomor_faktur_vendor: string, nomor_internal: string, nama_penjual: string, npwp_penjual: string, dpp: int, ppn: int, total: int}>, total_dpp: int, total_ppn: int}}
     */
    public function inputTaxList(Request $request): JsonResponse
    {
        $this->authorize('reports.tax');

        $startDate = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->input('end_date') ?? now()->endOfMonth()->toDateString();

        $bills = $this->reports->tax()->getInputTaxList($startDate, $endDate);

        $totalDpp = $bills->sum('dpp');
        $totalPpn = $bills->sum('ppn');

        return $this->success([
            'report_name' => 'Daftar Faktur Pajak Masukan',
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'items' => $bills->values(),
            'total_dpp' => $totalDpp,
            'total_ppn' => $totalPpn,
            'total' => $totalDpp + $totalPpn,
        ]);
    }
}
