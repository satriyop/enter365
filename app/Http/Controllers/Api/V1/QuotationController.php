<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreQuotationFromBomRequest;
use App\Http\Requests\Api\V1\StoreQuotationRequest;
use App\Http\Requests\Api\V1\UpdateQuotationRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Resources\Api\V1\QuotationResource;
use App\Http\Resources\Api\V1\QuotationVariantOptionResource;
use App\Models\Accounting\Quotation;
use App\Models\Accounting\QuotationVariantOption;
use App\Services\Accounting\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class QuotationController extends Controller
{
    public function __construct(
        private QuotationService $quotationService
    ) {}

    /**
     * Display a listing of quotations.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Quotation::query()->with(['contact', 'items']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('contact_id')) {
            $query->where('contact_id', $request->input('contact_id'));
        }

        if ($request->has('quotation_type')) {
            $query->where('quotation_type', $request->input('quotation_type'));
        }

        if ($request->has('start_date')) {
            $query->where('quotation_date', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $query->where('quotation_date', '<=', $request->input('end_date'));
        }

        if ($request->boolean('expired_only')) {
            $query->expired();
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        if ($request->boolean('multi_option_only')) {
            $query->where('quotation_type', Quotation::TYPE_MULTI_OPTION);
        }

        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(quotation_number) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(subject) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(reference) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('contact', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]));
            });
        }

        $quotations = $query->orderByDesc('quotation_date')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 25));

        return QuotationResource::collection($quotations);
    }

    /**
     * Store a newly created quotation.
     */
    public function store(StoreQuotationRequest $request): JsonResponse
    {
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
        try {
            $quotation = $this->quotationService->createFromBom($request->validated());

            return (new QuotationResource($quotation))
                ->response()
                ->setStatusCode(201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Display the specified quotation.
     */
    public function show(Quotation $quotation): QuotationResource
    {
        $relations = ['contact', 'items.product', 'revisions', 'convertedInvoice'];

        // Load variant relationships for multi-option quotations
        if ($quotation->isMultiOption()) {
            $relations = array_merge($relations, [
                'variantGroup',
                'selectedVariant',
                'variantOptions.bom',
            ]);
        }

        return new QuotationResource($quotation->load($relations));
    }

    /**
     * Update the specified quotation.
     */
    public function update(UpdateQuotationRequest $request, Quotation $quotation): QuotationResource|JsonResponse
    {
        try {
            $quotation = $this->quotationService->update($quotation, $request->validated());

            return new QuotationResource($quotation);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove the specified quotation.
     */
    public function destroy(Quotation $quotation): JsonResponse
    {
        if (! $quotation->isEditable()) {
            return response()->json([
                'message' => 'Hanya penawaran draft yang dapat dihapus.',
            ], 422);
        }

        $quotation->delete();

        return response()->json(['message' => 'Penawaran berhasil dihapus.']);
    }

    /**
     * Submit quotation for approval.
     */
    public function submit(Quotation $quotation): QuotationResource|JsonResponse
    {
        try {
            $quotation = $this->quotationService->submit($quotation);

            return new QuotationResource($quotation);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Approve a quotation.
     */
    public function approve(Quotation $quotation): QuotationResource|JsonResponse
    {
        try {
            $quotation = $this->quotationService->approve($quotation);

            return new QuotationResource($quotation);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Reject a quotation.
     */
    public function reject(Request $request, Quotation $quotation): QuotationResource|JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Alasan penolakan harus diisi.',
        ]);

        try {
            $quotation = $this->quotationService->reject($quotation, $request->input('reason'));

            return new QuotationResource($quotation);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Create a revision of a quotation.
     */
    public function revise(Quotation $quotation): QuotationResource|JsonResponse
    {
        try {
            $newQuotation = $this->quotationService->revise($quotation);

            return (new QuotationResource($newQuotation))
                ->response()
                ->setStatusCode(201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Convert quotation to invoice.
     */
    public function convertToInvoice(Quotation $quotation): JsonResponse
    {
        try {
            $invoice = $this->quotationService->convertToInvoice($quotation);

            return response()->json([
                'message' => 'Penawaran berhasil dikonversi menjadi faktur.',
                'invoice' => new InvoiceResource($invoice),
                'quotation' => new QuotationResource($quotation->fresh(['contact', 'items'])),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Duplicate a quotation.
     */
    public function duplicate(Quotation $quotation): JsonResponse
    {
        $newQuotation = $this->quotationService->duplicate($quotation);

        return response()->json([
            'message' => 'Penawaran berhasil diduplikasi.',
            'data' => new QuotationResource($newQuotation),
        ], 201);
    }

    /**
     * Generate PDF (placeholder).
     */
    public function pdf(Quotation $quotation): JsonResponse
    {
        // Placeholder for PDF generation
        return response()->json([
            'message' => 'Fitur PDF belum tersedia.',
            'quotation_number' => $quotation->getFullNumber(),
        ], 501);
    }

    /**
     * Get quotation statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $statistics = $this->quotationService->getStatistics(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json(['data' => $statistics]);
    }

    /**
     * Get variant options for a multi-option quotation.
     */
    public function variantOptions(Quotation $quotation): JsonResponse
    {
        if (! $quotation->isMultiOption()) {
            return response()->json([
                'message' => 'Penawaran ini bukan tipe multi-option.',
            ], 422);
        }

        $options = $quotation->variantOptions()
            ->with('bom')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => QuotationVariantOptionResource::collection($options),
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
        if (! $quotation->isEditable()) {
            return response()->json([
                'message' => 'Penawaran ini tidak dapat diubah.',
            ], 422);
        }

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

        // Update quotation type to multi-option if not already
        if (! $quotation->isMultiOption()) {
            $quotation->update(['quotation_type' => Quotation::TYPE_MULTI_OPTION]);
        }

        // Sync variant options
        $quotation->variantOptions()->delete();

        $options = collect($validated['options'])->map(function ($option, $index) use ($quotation) {
            return $quotation->variantOptions()->create([
                'bom_id' => $option['bom_id'],
                'display_name' => $option['display_name'],
                'tagline' => $option['tagline'] ?? null,
                'is_recommended' => $option['is_recommended'] ?? false,
                'selling_price' => $option['selling_price'],
                'features' => $option['features'] ?? null,
                'specifications' => $option['specifications'] ?? null,
                'warranty_terms' => $option['warranty_terms'] ?? null,
                'sort_order' => $index,
            ]);
        });

        // Reload with BOM relationship
        $savedOptions = $quotation->variantOptions()->with('bom')->orderBy('sort_order')->get();

        return response()->json([
            'message' => 'Opsi varian berhasil disimpan.',
            'data' => QuotationVariantOptionResource::collection($savedOptions),
        ]);
    }

    /**
     * Select a variant for a multi-option quotation.
     */
    public function selectVariant(Request $request, Quotation $quotation): QuotationResource|JsonResponse
    {
        if (! $quotation->isMultiOption()) {
            return response()->json([
                'message' => 'Penawaran ini bukan tipe multi-option.',
            ], 422);
        }

        $validated = $request->validate([
            'variant_option_id' => ['required', 'exists:quotation_variant_options,id'],
        ], [
            'variant_option_id.required' => 'Pilihan varian harus dipilih.',
            'variant_option_id.exists' => 'Pilihan varian tidak ditemukan.',
        ]);

        /** @var QuotationVariantOption $variantOption */
        $variantOption = QuotationVariantOption::findOrFail($validated['variant_option_id']);

        // Verify the variant option belongs to this quotation
        if ($variantOption->quotation_id !== $quotation->id) {
            return response()->json([
                'message' => 'Pilihan varian tidak valid untuk penawaran ini.',
            ], 422);
        }

        $quotation->update([
            'selected_variant_id' => $variantOption->bom_id,
            'total' => $variantOption->selling_price,
        ]);

        return new QuotationResource(
            $quotation->fresh(['contact', 'items', 'variantGroup', 'selectedVariant', 'variantOptions.bom'])
        );
    }

    /**
     * Get variant comparison data for customer-facing display.
     */
    public function variantComparison(Quotation $quotation): JsonResponse
    {
        if (! $quotation->isMultiOption()) {
            return response()->json([
                'message' => 'Penawaran ini bukan tipe multi-option.',
            ], 422);
        }

        $comparison = $quotation->getVariantComparison();

        return response()->json([
            'data' => [
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
            ],
        ]);
    }
}
