<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DocumentStatus;
use App\Filters\MaterialRequisitionFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMaterialRequisitionRequest;
use App\Http\Requests\Api\V1\UpdateMaterialRequisitionRequest;
use App\Http\Resources\Api\V1\MaterialRequisitionResource;
use App\Models\Manufacturing\MaterialRequisition;
use App\Models\Manufacturing\WorkOrder;
use App\Services\Manufacturing\MaterialRequisitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MaterialRequisitionController extends Controller
{
    public function __construct(
        private MaterialRequisitionService $materialRequisitionService
    ) {}

    /**
     * Display a listing of material requisitions.
     */
    public function index(MaterialRequisitionFilter $filter): AnonymousResourceCollection
    {
        $requisitions = MaterialRequisition::query()
            ->with(['workOrder', 'warehouse'])
            ->filter($filter)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filter->getRequest()->input('per_page', 25));

        return MaterialRequisitionResource::collection($requisitions);
    }

    /**
     * Create material requisition for a work order.
     */
    public function createForWorkOrder(StoreMaterialRequisitionRequest $request, WorkOrder $workOrder): JsonResponse
    {
        if (! in_array($workOrder->status, [DocumentStatus::Confirmed, DocumentStatus::InProgress])) {
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
    public function show(MaterialRequisition $materialRequisition, MaterialRequisitionFilter $filter): MaterialRequisitionResource
    {
        $filter->apply($materialRequisition->newQuery());

        $materialRequisition->loadMissing(['workOrder', 'warehouse', 'items.product']);

        return new MaterialRequisitionResource($materialRequisition);
    }

    /**
     * Update the specified material requisition.
     */
    public function update(UpdateMaterialRequisitionRequest $request, MaterialRequisition $materialRequisition): MaterialRequisitionResource
    {
        $requisition = $this->materialRequisitionService->update($materialRequisition, $request->validated());

        return new MaterialRequisitionResource($requisition);
    }

    /**
     * Remove the specified material requisition.
     */
    public function destroy(MaterialRequisition $materialRequisition): JsonResponse
    {
        $this->materialRequisitionService->delete($materialRequisition);

        return response()->json(['message' => 'Material requisition berhasil dihapus.']);
    }

    /**
     * Approve material requisition.
     */
    public function approve(MaterialRequisition $materialRequisition): MaterialRequisitionResource
    {
        $requisition = $this->materialRequisitionService->approve($materialRequisition);

        return new MaterialRequisitionResource($requisition);
    }

    /**
     * Issue materials from requisition.
     */
    public function issue(Request $request, MaterialRequisition $materialRequisition): MaterialRequisitionResource
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

        $requisition = $this->materialRequisitionService->issue(
            $materialRequisition,
            $request->input('items')
        );

        return new MaterialRequisitionResource($requisition);
    }

    /**
     * Cancel material requisition.
     */
    public function cancel(MaterialRequisition $materialRequisition): MaterialRequisitionResource
    {
        $requisition = $this->materialRequisitionService->cancel($materialRequisition);

        return new MaterialRequisitionResource($requisition);
    }
}
