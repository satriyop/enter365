<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMrpRunRequest;
use App\Http\Requests\Api\V1\UpdateMrpSuggestionRequest;
use App\Http\Resources\Api\V1\MrpDemandResource;
use App\Http\Resources\Api\V1\MrpRunResource;
use App\Http\Resources\Api\V1\MrpSuggestionResource;
use App\Models\Accounting\MrpRun;
use App\Models\Accounting\MrpSuggestion;
use App\Services\Accounting\MrpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MrpController extends Controller
{
    public function __construct(
        private MrpService $mrpService
    ) {}

    /**
     * List all MRP runs.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = MrpRun::query()
            ->with(['warehouse'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->warehouse_id, fn ($q, $w) => $q->where('warehouse_id', $w))
            ->orderByDesc('created_at');

        $runs = $request->per_page
            ? $query->paginate($request->per_page)
            : $query->get();

        return MrpRunResource::collection($runs);
    }

    /**
     * Create a new MRP run.
     */
    public function store(StoreMrpRunRequest $request): JsonResponse
    {
        $run = $this->mrpService->create($request->validated());

        return (new MrpRunResource($run))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get a single MRP run.
     */
    public function show(MrpRun $mrpRun): MrpRunResource
    {
        $mrpRun->load(['warehouse', 'demands.product', 'suggestions.product']);

        return new MrpRunResource($mrpRun);
    }

    /**
     * Update an MRP run.
     */
    public function update(StoreMrpRunRequest $request, MrpRun $mrpRun): MrpRunResource
    {
        $run = $this->mrpService->update($mrpRun, $request->validated());

        return new MrpRunResource($run);
    }

    /**
     * Delete an MRP run.
     */
    public function destroy(MrpRun $mrpRun): JsonResponse
    {
        $this->mrpService->delete($mrpRun);

        return response()->json(['message' => 'MRP run berhasil dihapus.']);
    }

    /**
     * Execute MRP run.
     */
    public function execute(MrpRun $mrpRun): MrpRunResource
    {
        $run = $this->mrpService->execute($mrpRun);

        return new MrpRunResource($run->load(['demands.product', 'suggestions.product']));
    }

    /**
     * Get demands for an MRP run.
     */
    public function demands(MrpRun $mrpRun): AnonymousResourceCollection
    {
        $demands = $mrpRun->demands()
            ->with(['product', 'warehouse'])
            ->orderBy('required_date')
            ->get();

        return MrpDemandResource::collection($demands);
    }

    /**
     * Get suggestions for an MRP run.
     */
    public function suggestions(Request $request, MrpRun $mrpRun): AnonymousResourceCollection
    {
        $suggestions = $mrpRun->suggestions()
            ->with(['product', 'suggestedSupplier', 'suggestedWarehouse'])
            ->when($request->type, fn ($q, $t) => $q->where('suggestion_type', $t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->priority, fn ($q, $p) => $q->where('priority', $p))
            ->orderBy('priority')
            ->orderBy('suggested_due_date')
            ->get();

        return MrpSuggestionResource::collection($suggestions);
    }

    /**
     * Accept a suggestion.
     */
    public function acceptSuggestion(MrpSuggestion $suggestion): MrpSuggestionResource
    {
        $suggestion = $this->mrpService->acceptSuggestion($suggestion);

        return new MrpSuggestionResource($suggestion->load('product'));
    }

    /**
     * Reject a suggestion.
     */
    public function rejectSuggestion(Request $request, MrpSuggestion $suggestion): MrpSuggestionResource
    {
        $suggestion = $this->mrpService->rejectSuggestion(
            $suggestion,
            $request->input('reason')
        );

        return new MrpSuggestionResource($suggestion->load('product'));
    }

    /**
     * Update a suggestion.
     */
    public function updateSuggestion(
        UpdateMrpSuggestionRequest $request,
        MrpSuggestion $suggestion
    ): MrpSuggestionResource {
        if ($request->has('adjusted_quantity')) {
            $suggestion = $this->mrpService->updateSuggestionQuantity(
                $suggestion,
                $request->input('adjusted_quantity')
            );
        }

        // Update other fields
        $suggestion->fill($request->only([
            'suggested_supplier_id',
            'suggested_warehouse_id',
            'priority',
            'notes',
        ]));
        $suggestion->save();

        return new MrpSuggestionResource($suggestion->fresh(['product', 'suggestedSupplier']));
    }

    /**
     * Convert suggestion to Purchase Order.
     */
    public function convertToPurchaseOrder(MrpSuggestion $suggestion): JsonResponse
    {
        $po = $this->mrpService->convertToPurchaseOrder($suggestion);

        return response()->json([
            'message' => 'Saran berhasil dikonversi ke Purchase Order.',
            'purchase_order' => [
                'id' => $po->id,
                'po_number' => $po->po_number,
            ],
        ]);
    }

    /**
     * Convert suggestion to Work Order.
     */
    public function convertToWorkOrder(MrpSuggestion $suggestion): JsonResponse
    {
        $wo = $this->mrpService->convertToWorkOrder($suggestion);

        return response()->json([
            'message' => 'Saran berhasil dikonversi ke Work Order.',
            'work_order' => [
                'id' => $wo->id,
                'wo_number' => $wo->wo_number,
            ],
        ]);
    }

    /**
     * Convert suggestion to Subcontractor Work Order.
     */
    public function convertToSubcontractorWorkOrder(
        Request $request,
        MrpSuggestion $suggestion
    ): JsonResponse {
        $request->validate([
            'subcontractor_id' => ['required', 'integer', 'exists:contacts,id'],
        ]);

        $scWo = $this->mrpService->convertToSubcontractorWorkOrder(
            $suggestion,
            $request->input('subcontractor_id')
        );

        return response()->json([
            'message' => 'Saran berhasil dikonversi ke Subcontractor Work Order.',
            'subcontractor_work_order' => [
                'id' => $scWo->id,
                'sc_wo_number' => $scWo->sc_wo_number,
            ],
        ]);
    }

    /**
     * Bulk accept suggestions.
     */
    public function bulkAccept(Request $request): JsonResponse
    {
        $request->validate([
            'suggestion_ids' => ['required', 'array'],
            'suggestion_ids.*' => ['integer', 'exists:mrp_suggestions,id'],
        ]);

        $count = $this->mrpService->bulkAccept($request->input('suggestion_ids'));

        return response()->json([
            'message' => "{$count} saran berhasil diterima.",
            'accepted_count' => $count,
        ]);
    }

    /**
     * Bulk reject suggestions.
     */
    public function bulkReject(Request $request): JsonResponse
    {
        $request->validate([
            'suggestion_ids' => ['required', 'array'],
            'suggestion_ids.*' => ['integer', 'exists:mrp_suggestions,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $count = $this->mrpService->bulkReject(
            $request->input('suggestion_ids'),
            $request->input('reason')
        );

        return response()->json([
            'message' => "{$count} saran berhasil ditolak.",
            'rejected_count' => $count,
        ]);
    }

    /**
     * Get shortage report (quick analysis without saving).
     */
    public function shortageReport(Request $request): JsonResponse
    {
        $request->validate([
            'horizon_start' => ['required', 'date'],
            'horizon_end' => ['required', 'date', 'after:horizon_start'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        $report = $this->mrpService->getShortageReport(
            new \DateTime($request->input('horizon_start')),
            new \DateTime($request->input('horizon_end')),
            $request->input('warehouse_id')
        );

        return response()->json($report);
    }

    /**
     * Get MRP statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->mrpService->getStatistics();

        return response()->json($stats);
    }
}
