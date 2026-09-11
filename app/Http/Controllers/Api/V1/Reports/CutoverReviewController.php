<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\Controller;
use App\Services\Accounting\Reports\CutoverReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CutoverReviewController extends Controller
{
    public function __construct(
        private CutoverReviewService $cutover,
    ) {}

    /**
     * Period-end review: goods received, vendor bill not posted (Odoo Review › Bill to Receive).
     *
     * @queryParam as_of_date date Inclusive cut-off. Example: 2026-03-31
     */
    public function billToReceive(Request $request): JsonResponse
    {
        return $this->show($request, 'bill-to-receive');
    }

    /**
     * Period-end review: vendor bill posted, goods not received (Odoo Review › Billed Not Received).
     *
     * @queryParam as_of_date date Inclusive cut-off. Example: 2026-03-31
     */
    public function billedNotReceived(Request $request): JsonResponse
    {
        return $this->show($request, 'billed-not-received');
    }

    /**
     * Period-end review: goods delivered, customer invoice not posted (Odoo Review › Invoices to Be Issued).
     *
     * @queryParam as_of_date date Inclusive cut-off. Example: 2026-03-31
     */
    public function invoicesToBeIssued(Request $request): JsonResponse
    {
        return $this->show($request, 'invoices-to-be-issued');
    }

    /**
     * Period-end review: customer invoice posted, goods not delivered (Odoo Review › Invoiced Not Delivered).
     *
     * @queryParam as_of_date date Inclusive cut-off. Example: 2026-03-31
     */
    public function invoicedNotDelivered(Request $request): JsonResponse
    {
        return $this->show($request, 'invoiced-not-delivered');
    }

    private function show(Request $request, string $kind): JsonResponse
    {
        $this->authorize('reports.financial');

        $asOf = $request->string('as_of_date')->trim()->toString() ?: null;

        return $this->success($this->cutover->report($kind, $asOf));
    }
}
