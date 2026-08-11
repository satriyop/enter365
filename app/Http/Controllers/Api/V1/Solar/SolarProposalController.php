<?php

namespace App\Http\Controllers\Api\V1\Solar;

use App\Enums\DocumentStatus;
use App\Exports\Solar\SolarProposalExport;
use App\Filters\SolarProposalFilter;
use App\Http\Controllers\Api\V1\Controller;
use App\Http\Requests\Api\V1\AcceptSolarProposalRequest;
use App\Http\Requests\Api\V1\AttachSolarVariantsRequest;
use App\Http\Requests\Api\V1\StoreSolarProposalRequest;
use App\Http\Requests\Api\V1\UpdateSolarProposalRequest;
use App\Http\Resources\Api\V1\QuotationResource;
use App\Http\Resources\Api\V1\SolarProposalListResource;
use App\Http\Resources\Api\V1\SolarProposalResource;
use App\Models\Solar\SolarProposal;
use App\Services\Solar\SolarProposalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

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
    public function index(SolarProposalFilter $filter): AnonymousResourceCollection
    {
        $proposals = SolarProposal::query()
            ->with(['contact', 'creator'])
            ->filter($filter)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filter->getRequest()->input('per_page', 25));

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
    public function show(SolarProposal $solarProposal, SolarProposalFilter $filter): SolarProposalResource
    {
        $filter->apply($solarProposal->newQuery());

        $solarProposal->loadMissing([
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
        $proposal = $this->service->update($solarProposal, $request->validated());

        return (new SolarProposalResource($proposal))->response();
    }

    /**
     * Remove the specified solar proposal.
     *
     * @operationId deleteSolarProposal
     */
    public function destroy(SolarProposal $solarProposal): JsonResponse
    {
        $this->service->delete($solarProposal);

        return response()->json(['message' => 'Proposal berhasil dihapus.']);
    }

    /**
     * Calculate/recalculate all proposal values.
     *
     * @operationId calculateSolarProposal
     */
    public function calculate(SolarProposal $solarProposal): JsonResponse
    {
        $proposal = $this->service->calculateProposal($solarProposal);

        return (new SolarProposalResource($proposal))->response();
    }

    /**
     * Attach a BOM variant group to the proposal.
     *
     * @operationId attachSolarVariants
     */
    public function attachVariants(AttachSolarVariantsRequest $request, SolarProposal $solarProposal): JsonResponse
    {
        $proposal = $this->service->attachVariantGroup(
            $solarProposal,
            $request->validated()['variant_group_id']
        );

        return (new SolarProposalResource($proposal))->response();
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

        $proposal = $this->service->selectBom($solarProposal, $request->input('bom_id'));

        return (new SolarProposalResource($proposal))->response();
    }

    /**
     * Mark proposal as sent to customer.
     *
     * @operationId sendSolarProposal
     */
    public function send(SolarProposal $solarProposal): JsonResponse
    {
        $proposal = $this->service->send($solarProposal);

        return (new SolarProposalResource($proposal))->response();
    }

    /**
     * Mark proposal as accepted by customer.
     *
     * @operationId acceptSolarProposal
     */
    public function accept(AcceptSolarProposalRequest $request, SolarProposal $solarProposal): JsonResponse
    {
        $proposal = $this->service->accept(
            $solarProposal,
            $request->validated()['selected_bom_id'] ?? null
        );

        return (new SolarProposalResource($proposal))->response();
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

        $proposal = $this->service->reject($solarProposal, $request->input('reason'));

        return (new SolarProposalResource($proposal))->response();
    }

    /**
     * Convert accepted proposal to a quotation.
     *
     * @operationId convertSolarProposalToQuotation
     */
    public function convertToQuotation(SolarProposal $solarProposal): JsonResponse
    {
        $quotation = $this->service->convertToQuotation($solarProposal);

        return response()->json([
            'message' => 'Proposal berhasil dikonversi ke quotation.',
            'quotation' => new QuotationResource($quotation),
            'proposal' => new SolarProposalResource($solarProposal->fresh()),
        ]);
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
            'rejected' => SolarProposal::where('status', DocumentStatus::Rejected)->count(),
            'expired' => SolarProposal::where('status', DocumentStatus::Expired)->count(),
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

    /**
     * Download PDF for a solar proposal.
     *
     * @operationId downloadSolarProposalPdf
     */
    public function pdf(SolarProposal $solarProposal): Response
    {
        // Load relationships needed for the PDF
        $solarProposal->load([
            'contact',
            'selectedBom',
            'variantGroup.activeBoms',
        ]);

        $pdf = Pdf::loadView('pdf.solar-proposal', [
            'proposal' => $solarProposal,
        ]);

        // Set paper size to A4
        $pdf->setPaper('a4', 'portrait');

        // Generate filename
        $filename = $solarProposal->proposal_number.'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Download Excel for a solar proposal.
     *
     * @operationId downloadSolarProposalExcel
     */
    public function excel(SolarProposal $solarProposal): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        // Load relationships needed for the export
        $solarProposal->load([
            'contact',
            'selectedBom',
        ]);

        $filename = $solarProposal->proposal_number.'.xlsx';

        return (new SolarProposalExport($solarProposal))->download($filename);
    }
}
