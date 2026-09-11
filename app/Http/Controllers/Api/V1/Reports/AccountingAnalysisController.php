<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\Controller;
use App\Services\Accounting\Reports\AccountingAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingAnalysisController extends Controller
{
    public function __construct(
        private AccountingAnalysisService $analysis,
    ) {}

    /**
     * Monthly VAT filing workspace (Odoo Accounting › Tax Returns).
     *
     * @queryParam year int Calendar year. Example: 2026
     */
    public function taxReturns(Request $request): JsonResponse
    {
        $this->authorize('reports.tax');

        $year = (int) $request->input('year', now()->year);

        return $this->success($this->analysis->taxReturns($year > 0 ? $year : (int) now()->year));
    }

    /**
     * Open foreign-currency AR/AP vs closing rate (Odoo Review › Unrealized Currencies).
     *
     * @queryParam as_of_date date Inclusive as-of. Example: 2026-03-31
     * @queryParam rates object Closing rates keyed by ISO code. Example: {"USD":16000}
     */
    public function unrealizedCurrencies(Request $request): JsonResponse
    {
        $this->authorize('reports.financial');

        $asOf = $request->string('as_of_date')->trim()->toString() ?: now()->toDateString();
        $rates = $request->input('rates', []);

        return $this->success($this->analysis->unrealizedCurrencies(
            $asOf,
            is_array($rates) ? $rates : [],
        ));
    }

    /**
     * Posted invoice analysis (Odoo Reporting › Invoice Analysis).
     *
     * @queryParam from date Inclusive start. Example: 2026-01-01
     * @queryParam to date Inclusive end. Example: 2026-03-31
     * @queryParam group_by string month, partner, or status. Example: month
     */
    public function invoiceAnalysis(Request $request): JsonResponse
    {
        $this->authorize('reports.financial');

        $from = $request->string('from')->trim()->toString() ?: now()->startOfYear()->toDateString();
        $to = $request->string('to')->trim()->toString() ?: now()->toDateString();
        $groupBy = $request->string('group_by')->trim()->toString() ?: 'month';

        return $this->success($this->analysis->invoiceAnalysis($from, $to, $groupBy));
    }

    /**
     * Analytic profitability from posted journal distributions (Odoo Reporting › Analytic Report).
     *
     * @queryParam from date Inclusive start. Example: 2026-01-01
     * @queryParam to date Inclusive end. Example: 2026-03-31
     */
    public function analyticReport(Request $request): JsonResponse
    {
        $this->authorize('reports.financial');

        $from = $request->string('from')->trim()->toString() ?: now()->startOfYear()->toDateString();
        $to = $request->string('to')->trim()->toString() ?: now()->toDateString();

        return $this->success($this->analysis->analyticReport($from, $to));
    }

    /**
     * Period KPI pack (Odoo Reporting › Executive Summary).
     *
     * @queryParam from date Inclusive start. Example: 2026-01-01
     * @queryParam to date Inclusive end. Example: 2026-03-31
     */
    public function executiveSummary(Request $request): JsonResponse
    {
        $this->authorize('reports.financial');

        $from = $request->string('from')->trim()->toString() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->trim()->toString() ?: now()->toDateString();

        return $this->success($this->analysis->executiveSummary($from, $to));
    }

    /**
     * Company budgets vs posted actuals (Odoo Reporting › Budget Report).
     *
     * @queryParam as_of_date date Restrict to fiscal periods covering this date. Example: 2026-03-31
     */
    public function budgetReport(Request $request): JsonResponse
    {
        $this->authorize('reports.financial');

        $asOf = $request->string('as_of_date')->trim()->toString();

        return $this->success($this->analysis->budgetReport($asOf === '' ? null : $asOf));
    }
}
