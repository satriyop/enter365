<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AcceptSolarProposalRequest;
use App\Http\Requests\Api\V1\AttachSolarVariantsRequest;
use App\Http\Requests\Api\V1\StoreSolarProposalRequest;
use App\Http\Requests\Api\V1\UpdateSolarProposalRequest;
use App\Http\Resources\Api\V1\QuotationResource;
use App\Http\Resources\Api\V1\SolarProposalListResource;
use App\Http\Resources\Api\V1\SolarProposalResource;
use App\Models\Accounting\SolarProposal;
use App\Services\Accounting\SolarProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class SolarProposalController extends Controller
{
    public function __construct(
        private SolarProposalService $service
    ) {}

    /**
     * Display a listing of solar proposals.
     *
     * @operationId listSolarProposals
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SolarProposal::query()->with(['contact', 'creator']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('contact_id')) {
            $query->where('contact_id', $request->input('contact_id'));
        }

        if ($request->has('province')) {
            $query->where('province', $request->input('province'));
        }

        if ($request->has('city')) {
            $query->where('city', $request->input('city'));
        }

        if ($request->boolean('expired_only')) {
            $query->where('status', SolarProposal::STATUS_EXPIRED);
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(proposal_number) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(site_name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(site_address) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('contact', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]));
            });
        }

        $proposals = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 25));

        return SolarProposalListResource::collection($proposals);
    }

    /**
     * Store a newly created solar proposal.
     *
     * @operationId createSolarProposal
     */
    public function store(StoreSolarProposalRequest $request): JsonResponse
    {
        $proposal = $this->service->create($request->validated());

        return (new SolarProposalResource($proposal))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified solar proposal.
     *
     * @operationId getSolarProposal
     */
    public function show(SolarProposal $solarProposal): SolarProposalResource
    {
        $solarProposal->load([
            'contact',
            'creator',
            'variantGroup.activeBoms',
            'selectedBom',
            'convertedQuotation',
        ]);

        return new SolarProposalResource($solarProposal);
    }

    /**
     * Update the specified solar proposal.
     *
     * @operationId updateSolarProposal
     */
    public function update(UpdateSolarProposalRequest $request, SolarProposal $solarProposal): JsonResponse
    {
        try {
            $proposal = $this->service->update($solarProposal, $request->validated());

            return (new SolarProposalResource($proposal))->response();
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove the specified solar proposal.
     *
     * @operationId deleteSolarProposal
     */
    public function destroy(SolarProposal $solarProposal): JsonResponse
    {
        try {
            $this->service->delete($solarProposal);

            return response()->json(['message' => 'Proposal berhasil dihapus.']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Calculate/recalculate all proposal values.
     *
     * @operationId calculateSolarProposal
     */
    public function calculate(SolarProposal $solarProposal): JsonResponse
    {
        try {
            $proposal = $this->service->calculateProposal($solarProposal);

            return (new SolarProposalResource($proposal))->response();
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Attach a BOM variant group to the proposal.
     *
     * @operationId attachSolarVariants
     */
    public function attachVariants(AttachSolarVariantsRequest $request, SolarProposal $solarProposal): JsonResponse
    {
        try {
            $proposal = $this->service->attachVariantGroup(
                $solarProposal,
                $request->validated()['variant_group_id']
            );

            return (new SolarProposalResource($proposal))->response();
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Select a specific BOM from the variant group.
     *
     * @operationId selectSolarBom
     */
    public function selectBom(Request $request, SolarProposal $solarProposal): JsonResponse
    {
        $request->validate([
            'bom_id' => ['required', 'exists:boms,id'],
        ]);

        try {
            $proposal = $this->service->selectBom($solarProposal, $request->input('bom_id'));

            return (new SolarProposalResource($proposal))->response();
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Mark proposal as sent to customer.
     *
     * @operationId sendSolarProposal
     */
    public function send(SolarProposal $solarProposal): JsonResponse
    {
        try {
            $proposal = $this->service->send($solarProposal);

            return (new SolarProposalResource($proposal))->response();
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Mark proposal as accepted by customer.
     *
     * @operationId acceptSolarProposal
     */
    public function accept(AcceptSolarProposalRequest $request, SolarProposal $solarProposal): JsonResponse
    {
        try {
            $proposal = $this->service->accept(
                $solarProposal,
                $request->validated()['selected_bom_id'] ?? null
            );

            return (new SolarProposalResource($proposal))->response();
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Mark proposal as rejected by customer.
     *
     * @operationId rejectSolarProposal
     */
    public function reject(Request $request, SolarProposal $solarProposal): JsonResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $proposal = $this->service->reject($solarProposal, $request->input('reason'));

            return (new SolarProposalResource($proposal))->response();
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Convert accepted proposal to a quotation.
     *
     * @operationId convertSolarProposalToQuotation
     */
    public function convertToQuotation(SolarProposal $solarProposal): JsonResponse
    {
        try {
            $quotation = $this->service->convertToQuotation($solarProposal);

            return response()->json([
                'message' => 'Proposal berhasil dikonversi ke quotation.',
                'quotation' => new QuotationResource($quotation),
                'proposal' => new SolarProposalResource($solarProposal->fresh()),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get solar proposal statistics.
     *
     * @operationId getSolarProposalStatistics
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => SolarProposal::count(),
            'draft' => SolarProposal::draft()->count(),
            'sent' => SolarProposal::sent()->count(),
            'accepted' => SolarProposal::accepted()->count(),
            'rejected' => SolarProposal::where('status', SolarProposal::STATUS_REJECTED)->count(),
            'expired' => SolarProposal::where('status', SolarProposal::STATUS_EXPIRED)->count(),
            'active' => SolarProposal::active()->count(),

            // Financial metrics
            'total_system_value' => SolarProposal::accepted()
                ->whereNotNull('selected_bom_id')
                ->with('selectedBom')
                ->get()
                ->sum(fn ($p) => $p->getSystemCost() ?? 0),

            // This month
            'created_this_month' => SolarProposal::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'accepted_this_month' => SolarProposal::whereMonth('accepted_at', now()->month)
                ->whereYear('accepted_at', now()->year)
                ->count(),
        ];

        return response()->json(['data' => $stats]);
    }
}
