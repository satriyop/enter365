<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Accounting\Reports\ReportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ExportController extends Controller
{
    public function __construct(
        private ReportExportService $exportService
    ) {}

    public function trialBalance(Request $request): Response|JsonResponse
    {
        Gate::authorize('reports.export');

        return $this->exportService->trialBalance(
            $request->input('date', now()->toDateString()),
            $request->input('format', 'csv')
        );
    }

    public function balanceSheet(Request $request): Response|JsonResponse
    {
        Gate::authorize('reports.export');

        return $this->exportService->balanceSheet(
            $request->input('date', now()->toDateString()),
            $request->input('format', 'csv')
        );
    }

    public function incomeStatement(Request $request): Response|JsonResponse
    {
        Gate::authorize('reports.export');

        return $this->exportService->incomeStatement(
            $request->input('start_date', now()->startOfMonth()->toDateString()),
            $request->input('end_date', now()->toDateString()),
            $request->input('format', 'csv')
        );
    }

    public function generalLedger(Request $request): Response|JsonResponse
    {
        Gate::authorize('reports.export');

        $accountId = $request->input('account_id');

        return $this->exportService->generalLedger(
            $accountId !== null ? (int) $accountId : null,
            $request->input('start_date', now()->startOfMonth()->toDateString()),
            $request->input('end_date', now()->toDateString()),
            $request->input('format', 'csv')
        );
    }

    public function receivableAging(Request $request): Response|JsonResponse
    {
        Gate::authorize('reports.export');

        return $this->exportService->receivableAging(
            $request->input('format', 'csv')
        );
    }

    public function payableAging(Request $request): Response|JsonResponse
    {
        Gate::authorize('reports.export');

        return $this->exportService->payableAging(
            $request->input('format', 'csv')
        );
    }

    public function invoices(Request $request): Response|JsonResponse
    {
        Gate::authorize('reports.export');

        return $this->exportService->invoices(
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('status'),
            $request->input('format', 'csv')
        );
    }

    public function bills(Request $request): Response|JsonResponse
    {
        Gate::authorize('reports.export');

        return $this->exportService->bills(
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('status'),
            $request->input('format', 'csv')
        );
    }

    public function taxReport(Request $request): Response|JsonResponse
    {
        Gate::authorize('reports.export');

        return $this->exportService->taxReport(
            $request->input('month', now()->month),
            $request->input('year', now()->year),
            $request->input('format', 'csv')
        );
    }
}
