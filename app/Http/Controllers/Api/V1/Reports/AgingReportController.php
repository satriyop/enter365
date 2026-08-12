<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\Contacts\Contact;
use App\Services\Accounting\Reports\ReportServiceFactory;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgingReportController extends Controller
{
    public function __construct(
        private ReportServiceFactory $reports
    ) {}

    /**
     * Laporan Umur Piutang (Accounts Receivable Aging).
     *
     * @response array{data: array{report_name: string, as_of_date: string, buckets: list<array{label: string, min: int, max: int|null}>, contacts: list<array{id: int, code: string, name: string, current: int, days_1_30: int, days_31_60: int, days_61_90: int, over_90: int, total: int, invoice_count: int}>, totals: array{current: int, days_1_30: int, days_31_60: int, days_61_90: int, over_90: int, total: int}}}
     */
    public function receivableAging(Request $request): JsonResponse
    {
        $this->authorize('reports.aging');

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
     * @response array{data: array{report_name: string, as_of_date: string, buckets: list<array{label: string, min: int, max: int|null}>, contacts: list<array{id: int, code: string, name: string, current: int, days_1_30: int, days_31_60: int, days_61_90: int, over_90: int, total: int, bill_count: int}>, totals: array{current: int, days_1_30: int, days_31_60: int, days_61_90: int, over_90: int, total: int}}}
     */
    public function payableAging(Request $request): JsonResponse
    {
        $this->authorize('reports.aging');

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
        $this->authorize('reports.aging');

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
}
