<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\Controller;
use App\Services\Accounting\Reports\ReportServiceFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashFlowReportController extends Controller
{
    public function __construct(
        private ReportServiceFactory $reports
    ) {}

    /**
     * Laporan Arus Kas (Cash Flow Statement).
     *
     * @response array{data: array{report_name: string, period: array{start: string, end: string}, operating_activities: array{items: list<array{description: string, amount: int}>, total: int}, investing_activities: array{items: list<array{description: string, amount: int}>, total: int}, financing_activities: array{items: list<array{description: string, amount: int}>, total: int}, net_cash_change: int, opening_balance: int, closing_balance: int}}
     */
    public function cashFlow(Request $request): JsonResponse
    {
        $this->authorize('reports.cash_flow');

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
        $this->authorize('reports.cash_flow');

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
}
