<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Sales\InvoiceServiceInterface;
use App\Filters\InvoiceFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MakeRecurringRequest;
use App\Http\Requests\Api\V1\StoreInvoiceRequest;
use App\Http\Requests\Api\V1\UpdateInvoiceRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Resources\Api\V1\RecurringTemplateResource;
use App\Models\Sales\Invoice;
use App\Services\Sales\RecurringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceServiceInterface $invoiceService,
        private RecurringService $recurringService
    ) {}

    /**
     * Display a listing of invoices.
     */
    public function index(InvoiceFilter $filter): AnonymousResourceCollection
    {
        $invoices = Invoice::query()
            ->with(['contact', 'items'])
            ->filter($filter)
            ->paginate($filter->getRequest()->input('per_page', 25));

        return InvoiceResource::collection($invoices);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->create($request->validated());

        return (new InvoiceResource($invoice))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource(
            $invoice->load(['contact', 'items.revenueAccount', 'journalEntry.lines.account', 'payments'])
        );
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        $invoice = $this->invoiceService->update($invoice, $request->validated());

        return new InvoiceResource($invoice);
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->invoiceService->delete($invoice);

        return response()->json(['message' => 'Faktur berhasil dihapus.']);
    }

    public function post(Invoice $invoice): InvoiceResource
    {
        $invoice = $this->invoiceService->post($invoice);

        return new InvoiceResource($invoice);
    }

    public function makeRecurring(MakeRecurringRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice->load('items');

        $template = $this->recurringService->createTemplateFromInvoice($invoice, $request->validated());

        return response()->json([
            'message' => 'Template recurring berhasil dibuat dari faktur.',
            'data' => new RecurringTemplateResource($template->load('contact')),
        ], 201);
    }
}
