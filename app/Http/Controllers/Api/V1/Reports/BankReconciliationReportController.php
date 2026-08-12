<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\Accounting\Account;
use App\Services\Accounting\Reports\ReportServiceFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankReconciliationReportController extends Controller
{
    public function __construct(
        private ReportServiceFactory $reports
    ) {}

    /**
     * Laporan Rekonsiliasi Bank.
     *
     * @response array{data: array{report_name: string, account: array{id: int, code: string, name: string}, as_of_date: string, book_balance: int, bank_balance: int, adjustments_to_book: array{items: list<array{id: int, date: string, description: string, reference: string|null, amount: int, type: string}>, total: int}, adjustments_to_bank: array{items: list<array{id: int, type: string, date: string, number: string, description: string|null, amount: int}>, total: int}, adjusted_book_balance: int, adjusted_bank_balance: int, difference: int, is_reconciled: bool, reconciliation_summary: array{total: int, reconciled: int, matched: int, unmatched: int}}}
     */
    public function bankReconciliation(Request $request, Account $account): JsonResponse
    {
        $this->authorize('reports.financial');

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
        $this->authorize('reports.financial');

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
}
