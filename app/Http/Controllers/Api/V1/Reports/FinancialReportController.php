<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\Controller;
use App\Services\Accounting\Reports\ReportServiceFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialReportController extends Controller
{
    public function __construct(
        private ReportServiceFactory $reports
    ) {}

    /**
     * Neraca Saldo (Trial Balance).
     */
    public function trialBalance(Request $request): JsonResponse
    {
        $this->authorize('reports.financial');

        $asOfDate = $request->input('as_of_date');
        $journalId = $request->integer('journal_id') ?: null;
        $postedOnly = $request->boolean('posted_only', true);
        $trialBalance = $this->reports->balance()->getTrialBalance($asOfDate, $journalId, $postedOnly);

        $totalDebit = $trialBalance->sum('debit_balance');
        $totalCredit = $trialBalance->sum('credit_balance');

        return $this->success([
            'report_name' => 'Neraca Saldo',
            'as_of_date' => $asOfDate ?? now()->toDateString(),
            'journal_id' => $journalId,
            'posted_only' => $postedOnly,
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
        $this->authorize('reports.financial');

        $asOfDate = $request->input('as_of_date');
        $compareTo = $request->input('compare_to');
        $journalId = $request->integer('journal_id') ?: null;
        $postedOnly = $request->boolean('posted_only', true);

        if ($compareTo) {
            $comparative = $this->reports->financial()->getComparativeBalanceSheet($asOfDate, $compareTo);

            return $this->success($comparative);
        }

        $balanceSheet = $this->reports->financial()->getBalanceSheet($asOfDate, false, $journalId, $postedOnly);

        return $this->success([
            'report_name' => 'Laporan Posisi Keuangan',
            'journal_id' => $journalId,
            'posted_only' => $postedOnly,
            ...$balanceSheet,
        ]);
    }

    /**
     * Laporan Laba Rugi (Income Statement).
     */
    public function incomeStatement(Request $request): JsonResponse
    {
        $this->authorize('reports.financial');

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $comparePreviousPeriod = $request->boolean('compare_previous_period', false);
        $journalId = $request->integer('journal_id') ?: null;
        $postedOnly = $request->boolean('posted_only', true);

        if ($comparePreviousPeriod) {
            $comparative = $this->reports->financial()->getComparativeIncomeStatement(
                $startDate,
                $endDate,
                $request->input('previous_start_date'),
                $request->input('previous_end_date')
            );

            return $this->success($comparative);
        }

        $incomeStatement = $this->reports->financial()->getIncomeStatement($startDate, $endDate, false, $journalId, $postedOnly);

        return $this->success([
            'report_name' => 'Laporan Laba Rugi',
            'journal_id' => $journalId,
            'posted_only' => $postedOnly,
            ...$incomeStatement,
        ]);
    }

    /**
     * Buku Besar (General Ledger).
     *
     * @response array{data: array{report_name: string, start_date: string|null, end_date: string|null, accounts: list<array{id: int, code: string, name: string, type: string, opening_balance: int, entries: list<array{id: int, journal_entry_id: int, date: string, entry_number: string, description: string, reference: string|null, debit: int, credit: int, balance: int}>, closing_balance: int}>}}
     */
    public function generalLedger(Request $request): JsonResponse
    {
        $this->authorize('reports.financial');

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $journalId = $request->integer('journal_id') ?: null;
        $analyticAccountId = $request->integer('analytic_account_id') ?: null;
        $postedOnly = $request->boolean('posted_only', true);
        $generalLedger = $this->reports->financial()->getGeneralLedger($startDate, $endDate, $journalId, $analyticAccountId, $postedOnly);

        return $this->success([
            'report_name' => 'Buku Besar',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'journal_id' => $journalId,
            'analytic_account_id' => $analyticAccountId,
            'posted_only' => $postedOnly,
            'accounts' => $generalLedger->values(),
        ]);
    }

    /**
     * Buku Besar Partner (Partner Ledger).
     *
     * @queryParam start_date string Filter lines on/after this date. Example: 2026-01-01
     * @queryParam end_date string Filter lines on/before this date. Example: 2026-12-31
     * @queryParam contact_id int Filter to one partner. Example: 12
     * @queryParam account_id int Filter to one account. Example: 4
     * @queryParam journal_id int Filter to one journal. Example: 1
     */
    public function partnerLedger(Request $request): JsonResponse
    {
        $this->authorize('reports.financial');

        $contactId = $request->integer('contact_id') ?: null;
        $accountId = $request->integer('account_id') ?: null;
        $journalId = $request->integer('journal_id') ?: null;

        return $this->success($this->reports->financial()->getPartnerLedger(
            $request->input('start_date'),
            $request->input('end_date'),
            $contactId,
            $accountId,
            $journalId,
        ));
    }

    /**
     * Laporan Perubahan Ekuitas (Statement of Changes in Equity).
     *
     * @response array{data: array{report_name: string, period_start: string, period_end: string, opening_equity: array{items: list<array{account_id: int, code: string, name: string, subtype: string, balance: int}>, total: int}, changes: array{capital_additions: int, capital_withdrawals: int, net_income: int, dividends: int, other_adjustments: int, total_changes: int}, closing_equity: array{items: list<array{account_id: int, code: string, name: string, subtype: string, balance: int}>, total: int}}}
     */
    public function changesInEquity(Request $request): JsonResponse
    {
        $this->authorize('reports.financial');

        $report = $this->reports->financial()->getStatementOfChangesInEquityForApi(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return $this->success($report);
    }
}
