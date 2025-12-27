<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateSubcontractorInvoiceRequest;
use App\Http\Resources\Api\V1\SubcontractorInvoiceResource;
use App\Models\Accounting\SubcontractorInvoice;
use App\Services\Accounting\SubcontractorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubcontractorInvoiceController extends Controller
{
    public function __construct(
        private SubcontractorService $subcontractorService
    ) {}

    /**
     * List all subcontractor invoices.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SubcontractorInvoice::query()
            ->with(['subcontractorWorkOrder', 'subcontractor', 'bill'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->subcontractor_id, fn ($q, $s) => $q->where('subcontractor_id', $s))
            ->when(
                $request->subcontractor_work_order_id,
                fn ($q, $w) => $q->where('subcontractor_work_order_id', $w)
            )
            ->orderByDesc('invoice_date');

        $invoices = $request->per_page
            ? $query->paginate($request->per_page)
            : $query->get();

        return SubcontractorInvoiceResource::collection($invoices);
    }

    /**
     * Get a single invoice.
     */
    public function show(SubcontractorInvoice $subcontractorInvoice): SubcontractorInvoiceResource
    {
        $subcontractorInvoice->load([
            'subcontractorWorkOrder',
            'subcontractor',
            'bill',
        ]);

        return new SubcontractorInvoiceResource($subcontractorInvoice);
    }

    /**
     * Update an invoice.
     */
    public function update(
        UpdateSubcontractorInvoiceRequest $request,
        SubcontractorInvoice $subcontractorInvoice
    ): SubcontractorInvoiceResource {
        $invoice = $this->subcontractorService->updateInvoice(
            $subcontractorInvoice,
            $request->validated()
        );

        return new SubcontractorInvoiceResource($invoice);
    }

    /**
     * Approve an invoice.
     */
    public function approve(SubcontractorInvoice $subcontractorInvoice): SubcontractorInvoiceResource
    {
        $invoice = $this->subcontractorService->approveInvoice($subcontractorInvoice);

        return new SubcontractorInvoiceResource($invoice);
    }

    /**
     * Reject an invoice.
     */
    public function reject(
        Request $request,
        SubcontractorInvoice $subcontractorInvoice
    ): SubcontractorInvoiceResource {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $invoice = $this->subcontractorService->rejectInvoice(
            $subcontractorInvoice,
            $request->input('reason')
        );

        return new SubcontractorInvoiceResource($invoice);
    }

    /**
     * Convert invoice to bill.
     */
    public function convertToBill(SubcontractorInvoice $subcontractorInvoice): JsonResponse
    {
        $bill = $this->subcontractorService->convertToBill($subcontractorInvoice);

        return response()->json([
            'message' => 'Invoice berhasil dikonversi ke bill.',
            'bill' => [
                'id' => $bill->id,
                'bill_number' => $bill->bill_number,
            ],
        ]);
    }
}
