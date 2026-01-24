<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Sales\InvoiceServiceInterface;
use App\Filters\InvoiceFilter;
use App\Http\Requests\Api\V1\MakeRecurringRequest;
use App\Http\Requests\Api\V1\StoreInvoiceRequest;
use App\Http\Requests\Api\V1\UpdateInvoiceRequest;
use App\Http\Requests\Api\V1\VoidInvoiceRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Resources\Api\V1\RecurringTemplateResource;
use App\Models\Sales\Invoice;
use App\Services\Sales\RecurringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Invoice API Controller.
 *
 * Handles invoice CRUD operations, posting, voiding, and recurring template creation.
 */
class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceServiceInterface $invoiceService,
        private RecurringService $recurringService
    ) {}

    /**
     * List invoices with filtering and pagination.
     *
     * @queryParam status string Filter by status. Example: sent
     * @queryParam contact_id int Filter by contact. Example: 1
     * @queryParam date_from string Filter from date. Example: 2024-01-01
     * @queryParam date_to string Filter to date. Example: 2024-12-31
     * @queryParam search string Search by invoice number or contact name.
     * @queryParam include string Explicitly load relationships. Example: items,contact
     * @queryParam per_page int Items per page. Default: 25
     * @queryParam include_workflow bool Include workflow metadata.
     * @queryParam include_history bool Include status history.
     */
    public function index(InvoiceFilter $filter): AnonymousResourceCollection
    {
        $invoices = Invoice::query()
            ->with(['contact']) // Default eager loads
            ->filter($filter)
            ->paginate($filter->getRequest()->input('per_page', 25));

        return InvoiceResource::collection($invoices);
    }

    /**
     * Create new invoice.
     *
     * @response 201 {
     *   "data": {"id": 1, "invoice_number": "INV-202401-0001", "status": {"value": "draft"}}
     * }
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->create($request->validated());

        return $this->created(
            new InvoiceResource($invoice),
            'Faktur berhasil dibuat.'
        );
    }

    /**
     * Get invoice details.
     *
     * @queryParam include string Explicitly load relationships. Example: items,payments
     * @queryParam include_workflow bool Include workflow metadata.
     * @queryParam include_history bool Include status history.
     */
    public function show(Invoice $invoice, InvoiceFilter $filter): InvoiceResource
    {
        // Use filter to handle ?include= logic
        $filter->apply($invoice->newQuery()); 
        
        // Ensure default loads if not provided via ?include
        $invoice->loadMissing(['contact', 'items.revenueAccount', 'journalEntry.lines.account', 'payments']);

        return new InvoiceResource($invoice);
    }

    /**
     * Update invoice.
     *
     * Only draft invoices can be updated.
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        $invoice = $this->invoiceService->update($invoice, $request->validated());

        return new InvoiceResource($invoice);
    }

    /**
     * Delete invoice.
     *
     * Only draft invoices without payments can be deleted.
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->invoiceService->delete($invoice);

        return $this->deleted('Faktur berhasil dihapus.');
    }

    /**
     * Post invoice (create journal entry, change status to Sent).
     *
     * Creates AR/Revenue journal entry and transitions invoice to Sent status.
     * Invoice must be in Draft status with at least one item.
     */
    public function post(Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoiceService->post($invoice);

        return $this->success(new InvoiceResource($invoice));
    }

    /**
     * Void/cancel invoice.
     *
     * Cancels the invoice and reverses journal entry if exists.
     * Invoice must not be in terminal status (Paid/Cancelled).
     *
     * @bodyParam reason string required Reason for voiding. Example: Customer requested cancellation
     */
    public function void(VoidInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoiceService->void($invoice, $request->validated('reason'));

        return $this->success(new InvoiceResource($invoice));
    }

    /**
     * Create recurring template from invoice.
     *
     * Creates a recurring template based on the invoice for automatic generation.
     */
    public function makeRecurring(MakeRecurringRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice->load('items');

        $template = $this->recurringService->createTemplateFromInvoice($invoice, $request->validated());

        return $this->created(
            new RecurringTemplateResource($template->load('contact')),
            'Template recurring berhasil dibuat dari faktur.'
        );
    }
}
