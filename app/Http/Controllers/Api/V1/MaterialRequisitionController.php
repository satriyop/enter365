<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMaterialRequisitionRequest;
use App\Http\Requests\Api\V1\UpdateMaterialRequisitionRequest;
use App\Http\Resources\Api\V1\MaterialRequisitionResource;
use App\Models\Accounting\MaterialRequisition;
use App\Models\Accounting\WorkOrder;
use App\Services\Accounting\MaterialRequisitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class MaterialRequisitionController extends Controller
{
    public function __construct(
        private MaterialRequisitionService $materialRequisitionService
    ) {}

    /**
     * Display a listing of material requisitions.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = MaterialRequisition::query()->with(['workOrder', 'warehouse']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('work_order_id')) {
            $query->where('work_order_id', $request->input('work_order_id'));
        }

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->has('start_date')) {
            $query->where('requested_date', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $query->where('requested_date', '<=', $request->input('end_date'));
        }

        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(requisition_number) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('workOrder', fn ($q) => $q->whereRaw('LOWER(wo_number) LIKE ?', ["%{$search}%"]));
            });
        }

        $requisitions = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 25));

        return MaterialRequisitionResource::collection($requisitions);
    }

    /**
     * Create material requisition for a work order.
     */
    public function createForWorkOrder(StoreMaterialRequisitionRequest $request, WorkOrder $workOrder): JsonResponse
    {
        if (! in_array($workOrder->status, [WorkOrder::STATUS_CONFIRMED, WorkOrder::STATUS_IN_PROGRESS])) {
            return response()->json([
                'message' => 'Material requisition hanya dapat dibuat untuk work order yang sudah dikonfirmasi atau sedang berjalan.',
            ], 422);
        }

        $requisition = $this->materialRequisitionService->create($workOrder, $request->validated());

        return (new MaterialRequisitionResource($requisition))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified material requisition.
     */
    public function show(MaterialRequisition $materialRequisition): MaterialRequisitionResource
    {
        return new MaterialRequisitionResource(
            $materialRequisition->load(['workOrder', 'warehouse', 'items.product'])
        );
    }

    /**
     * Update the specified material requisition.
     */
    public function update(UpdateMaterialRequisitionRequest $request, MaterialRequisition $materialRequisition): MaterialRequisitionResource|JsonResponse
    {
        try {
            $requisition = $this->materialRequisitionService->update($materialRequisition, $request->validated());

            return new MaterialRequisitionResource($requisition);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove the specified material requisition.
     */
    public function destroy(MaterialRequisition $materialRequisition): JsonResponse
    {
        try {
            $this->materialRequisitionService->delete($materialRequisition);

            return response()->json(['message' => 'Material requisition berhasil dihapus.']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Approve material requisition.
     */
    public function approve(MaterialRequisition $materialRequisition): MaterialRequisitionResource|JsonResponse
    {
        try {
            $requisition = $this->materialRequisitionService->approve($materialRequisition);

            return new MaterialRequisitionResource($requisition);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Issue materials from requisition.
     */
    public function issue(Request $request, MaterialRequisition $materialRequisition): MaterialRequisitionResource|JsonResponse
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:material_requisition_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ], [
            'items.required' => 'Data item harus diisi.',
            'items.*.item_id.required' => 'ID item harus diisi.',
            'items.*.quantity.required' => 'Kuantitas yang dikeluarkan harus diisi.',
            'items.*.quantity.min' => 'Kuantitas harus lebih dari 0.',
        ]);

        try {
            $requisition = $this->materialRequisitionService->issue(
                $materialRequisition,
                $request->input('items')
            );

            return new MaterialRequisitionResource($requisition);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel material requisition.
     */
    public function cancel(MaterialRequisition $materialRequisition): MaterialRequisitionResource|JsonResponse
    {
        try {
            $requisition = $this->materialRequisitionService->cancel($materialRequisition);

            return new MaterialRequisitionResource($requisition);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
