<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\QuotationFilter;
use App\Http\Requests\Api\V1\CancelQuotationRequest;
use App\Http\Requests\Api\V1\StoreQuotationFromBomRequest;
use App\Http\Requests\Api\V1\StoreQuotationRequest;
use App\Http\Requests\Api\V1\UpdateQuotationRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Resources\Api\V1\QuotationResource;
use App\Http\Resources\Api\V1\QuotationVariantOptionResource;
use App\Models\Sales\Quotation;
use App\Services\Sales\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuotationController extends Controller
{
    public function __construct(
        private QuotationService $quotationService
    ) {}

    /**
     * Display a listing of quotations.
     *
     * @queryParam page int Page number. Example: 1
     * @queryParam per_page int Items per page. Example: 25
     * @queryParam search string Search by number, subject, reference, or contact name.
     * @queryParam status string Filter by status (draft, sent, approved, rejected, converted).
     * @queryParam contact_id int Filter by contact ID.
     * @queryParam start_date string Filter by date from (YYYY-MM-DD).
     * @queryParam end_date string Filter by date to (YYYY-MM-DD).
     * @queryParam quotation_type string Filter by type (single_option, multi_option).
     * @queryParam active_only boolean Filter active quotations only.
     * @queryParam expired_only boolean Filter expired quotations only.
     * @queryParam include string Explicitly load relationships. Example: items,contact
     */
    public function index(QuotationFilter $filter): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Quotation::class);

        $quotations = Quotation::query()
            ->with(['contact'])
            ->filter($filter)
            ->paginate($filter->getRequest()->input('per_page', 25));

        return QuotationResource::collection($quotations);
    }

    /**
     * Store a newly created quotation.
     */
    public function store(StoreQuotationRequest $request): JsonResponse
    {
        $this->authorize('create', Quotation::class);

        $quotation = $this->quotationService->create($request->validated());

        return (new QuotationResource($quotation))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Create a quotation from a BOM.
     *
     * Allows salespeople to pick a specific BOM (e.g., from a variant group)
     * and auto-generate a quotation with proper pricing.
     *
     * Options:
     * - margin_percent: Add margin on top of BOM cost (default: 20%)
     * - selling_price: Override with direct selling price
     * - expand_items: true = expand BOM items as quotation lines, false = single line item
     */
    public function fromBom(StoreQuotationFromBomRequest $request): JsonResponse
    {
        $this->authorize('create', Quotation::class);

        $quotation = $this->quotationService->createFromBom($request->validated());

        return (new QuotationResource($quotation))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified quotation.
     */
    public function show(Quotation $quotation, QuotationFilter $filter): QuotationResource
    {
        $this->authorize('view', $quotation);

        $filter->apply($quotation->newQuery());

        $relations = ['contact', 'items.product', 'revisions', 'convertedInvoice'];

        // Load variant relationships for multi-option quotations
        if ($quotation->isMultiOption()) {
            $relations = array_merge($relations, [
                'variantGroup',
                'selectedVariant',
                'variantOptions.bom',
            ]);
        }

        $quotation->loadMissing($relations);

        return new QuotationResource($quotation);
    }

    /**
     * Update the specified quotation.
     */
    public function update(UpdateQuotationRequest $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('update', $quotation);

        $quotation = $this->quotationService->update($quotation, $request->validated());

        return new QuotationResource($quotation);
    }

    /**
     * Remove the specified quotation.
     */
    public function destroy(Quotation $quotation): JsonResponse
    {
        $this->authorize('delete', $quotation);

        if (! $quotation->isEditable()) {
            return $this->error('Hanya penawaran draft yang dapat dihapus.', 422);
        }

        $quotation->delete();

        return $this->deleted('Penawaran berhasil dihapus.');
    }

    /**
     * Submit quotation for approval.
     */
    public function submit(Quotation $quotation): QuotationResource
    {
        $this->authorize('manage', $quotation);

        $quotation = $this->quotationService->submit($quotation);

        return new QuotationResource($quotation);
    }

    /**
     * Approve a quotation.
     */
    public function approve(Quotation $quotation): QuotationResource
    {
        $this->authorize('approve', $quotation);

        $quotation = $this->quotationService->approve($quotation);

        return new QuotationResource($quotation);
    }

    /**
     * Reject a quotation.
     */
    public function reject(Request $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('manage', $quotation);

        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Alasan penolakan harus diisi.',
        ]);

        $quotation = $this->quotationService->reject($quotation, $request->input('reason'));

        return new QuotationResource($quotation);
    }

    /**
     * Cancel a quotation.
     *
     * Cancels a Draft, Submitted, or Approved quotation.
     * Cancelled quotations can be revised to create a new draft.
     */
    public function cancel(CancelQuotationRequest $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('manage', $quotation);

        $quotation = $this->quotationService->cancel(
            $quotation,
            $request->input('reason')
        );

        return new QuotationResource($quotation);
    }

    /**
     * Mark quotation as sent to customer.
     *
     * Sent quotations will NOT auto-expire when past valid_until date.
     * This protects quotations that customers have seen from silent expiration.
     */
    public function markAsSent(Request $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('manage', $quotation);

        $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'via' => ['nullable', 'string', 'in:email,print,portal'],
        ]);

        $quotation = $this->quotationService->markAsSent(
            $quotation,
            $request->input('email'),
            $request->input('via', 'email')
        );

        return new QuotationResource($quotation);
    }

    /**
     * Create a revision of a quotation.
     */
    public function revise(Quotation $quotation): JsonResponse
    {
        $this->authorize('manage', $quotation);

        $newQuotation = $this->quotationService->revise($quotation);

        return (new QuotationResource($newQuotation))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Convert quotation to invoice.
     */
    public function convertToInvoice(Quotation $quotation): JsonResponse
    {
        $this->authorize('manage', $quotation);

        $invoice = $this->quotationService->convertToInvoice($quotation);

        return $this->success([
            'invoice' => new InvoiceResource($invoice),
            'quotation' => new QuotationResource($quotation->fresh(['contact', 'items'])),
        ], 'Penawaran berhasil dikonversi menjadi faktur.', 201);
    }

    /**
     * Duplicate a quotation.
     */
    public function duplicate(Quotation $quotation): JsonResponse
    {
        $this->authorize('manage', $quotation);

        $newQuotation = $this->quotationService->duplicate($quotation);

        return $this->created(
            new QuotationResource($newQuotation),
            'Penawaran berhasil diduplikasi.'
        );
    }

    /**
     * Generate PDF (placeholder).
     */
    public function pdf(Quotation $quotation): JsonResponse
    {
        $this->authorize('view', $quotation);

        return $this->error('Fitur PDF belum tersedia.', 501);
    }

    /**
     * Get quotation statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Quotation::class);

        $statistics = $this->quotationService->getStatistics(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return $this->success($statistics);
    }

    /**
     * Get variant options for a multi-option quotation.
     */
    public function variantOptions(Quotation $quotation): JsonResponse
    {
        $this->authorize('view', $quotation);

        if (! $quotation->isMultiOption()) {
            return $this->error('Penawaran ini bukan tipe multi-option.', 422);
        }

        $options = $quotation->variantOptions()
            ->with('bom')
            ->orderBy('sort_order')
            ->get();

        return $this->success([
            'options' => QuotationVariantOptionResource::collection($options),
            'meta' => [
                'quotation_id' => $quotation->id,
                'quotation_number' => $quotation->getFullNumber(),
                'variant_group_id' => $quotation->variant_group_id,
                'selected_variant_id' => $quotation->selected_variant_id,
                'has_selected_variant' => $quotation->hasSelectedVariant(),
            ],
        ]);
    }

    /**
     * Add or update variant options for a multi-option quotation.
     */
    public function syncVariantOptions(Request $request, Quotation $quotation): JsonResponse
    {
        $this->authorize('manage', $quotation);

        $validated = $request->validate([
            'options' => ['required', 'array', 'min:2'],
            'options.*.bom_id' => ['required', 'exists:boms,id'],
            'options.*.display_name' => ['required', 'string', 'max:255'],
            'options.*.tagline' => ['nullable', 'string', 'max:255'],
            'options.*.is_recommended' => ['boolean'],
            'options.*.selling_price' => ['required', 'integer', 'min:0'],
            'options.*.features' => ['nullable', 'array'],
            'options.*.features.*' => ['string'],
            'options.*.specifications' => ['nullable', 'array'],
            'options.*.warranty_terms' => ['nullable', 'string', 'max:500'],
        ], [
            'options.required' => 'Opsi varian harus diisi.',
            'options.min' => 'Minimal 2 opsi varian diperlukan.',
            'options.*.bom_id.required' => 'BOM harus dipilih untuk setiap opsi.',
            'options.*.display_name.required' => 'Nama tampilan harus diisi.',
            'options.*.selling_price.required' => 'Harga jual harus diisi.',
        ]);

        $savedOptions = $this->quotationService->syncVariantOptions($quotation, $validated['options']);

        return $this->success(
            QuotationVariantOptionResource::collection($savedOptions),
            'Opsi varian berhasil disimpan.'
        );
    }

    /**
     * Select a variant for a multi-option quotation.
     */
    public function selectVariant(Request $request, Quotation $quotation): QuotationResource|JsonResponse
    {
        $this->authorize('manage', $quotation);

        // Check quotation type BEFORE validation for better UX
        if (! $quotation->isMultiOption()) {
            return $this->error('Penawaran ini bukan tipe multi-option.', 422);
        }

        $validated = $request->validate([
            'variant_option_id' => ['required', 'exists:quotation_variant_options,id'],
        ], [
            'variant_option_id.required' => 'Pilihan varian harus dipilih.',
            'variant_option_id.exists' => 'Pilihan varian tidak ditemukan.',
        ]);

        $quotation = $this->quotationService->selectVariant($quotation, $validated['variant_option_id']);

        return new QuotationResource(
            $quotation->load(['variantGroup', 'selectedVariant', 'variantOptions.bom'])
        );
    }

    /**
     * Get variant comparison data for customer-facing display.
     */
    public function variantComparison(Quotation $quotation): JsonResponse
    {
        $this->authorize('view', $quotation);

        if (! $quotation->isMultiOption()) {
            return $this->error('Penawaran ini bukan tipe multi-option.', 422);
        }

        $comparison = $quotation->getVariantComparison();

        return $this->success([
            'quotation' => [
                'id' => $quotation->id,
                'quotation_number' => $quotation->getFullNumber(),
                'subject' => $quotation->subject,
                'contact' => $quotation->contact ? [
                    'id' => $quotation->contact->id,
                    'name' => $quotation->contact->name,
                ] : null,
                'selected_variant_id' => $quotation->selected_variant_id,
            ],
            'options' => $comparison['options'] ?? [],
            'price_range' => $comparison['price_range'] ?? null,
        ]);
    }
}
