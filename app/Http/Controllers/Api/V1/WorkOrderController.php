<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkOrderRequest;
use App\Http\Requests\Api\V1\UpdateWorkOrderRequest;
use App\Http\Resources\Api\V1\WorkOrderResource;
use App\Models\Accounting\Bom;
use App\Models\Accounting\Project;
use App\Models\Accounting\WorkOrder;
use App\Services\Accounting\WorkOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class WorkOrderController extends Controller
{
    public function __construct(
        private WorkOrderService $workOrderService
    ) {}

    /**
     * Display a listing of work orders.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = WorkOrder::query()->with(['project', 'product', 'bom', 'warehouse']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->has('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        if ($request->has('bom_id')) {
            $query->where('bom_id', $request->input('bom_id'));
        }

        if ($request->has('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->has('start_date')) {
            $query->where('planned_start_date', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $query->where('planned_end_date', '<=', $request->input('end_date'));
        }

        if ($request->boolean('parent_only')) {
            $query->whereNull('parent_work_order_id');
        }

        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(wo_number) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
            });
        }

        $workOrders = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 25));

        return WorkOrderResource::collection($workOrders);
    }

    /**
     * Store a newly created work order.
     */
    public function store(StoreWorkOrderRequest $request): JsonResponse
    {
        $workOrder = $this->workOrderService->create($request->validated());

        return (new WorkOrderResource($workOrder))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified work order.
     */
    public function show(WorkOrder $workOrder): WorkOrderResource
    {
        return new WorkOrderResource(
            $workOrder->load([
                'project',
                'product',
                'bom',
                'warehouse',
                'parentWorkOrder',
                'items.product',
                'subWorkOrders',
                'consumptions.product',
            ])
        );
    }

    /**
     * Update the specified work order.
     */
    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder): WorkOrderResource|JsonResponse
    {
        try {
            $workOrder = $this->workOrderService->update($workOrder, $request->validated());

            return new WorkOrderResource($workOrder);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove the specified work order.
     */
    public function destroy(WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->workOrderService->delete($workOrder);

            return response()->json(['message' => 'Work order berhasil dihapus.']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Create work order for a project.
     */
    public function createForProject(StoreWorkOrderRequest $request, Project $project): JsonResponse
    {
        $workOrder = $this->workOrderService->createFromProject($project, $request->validated());

        return (new WorkOrderResource($workOrder))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Create work order from BOM.
     */
    public function createFromBom(Request $request, Bom $bom): JsonResponse
    {
        $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['nullable', 'string'],
            'planned_start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'quantity.required' => 'Kuantitas harus diisi.',
            'quantity.min' => 'Kuantitas harus lebih dari 0.',
        ]);

        if ($bom->status !== Bom::STATUS_ACTIVE) {
            return response()->json([
                'message' => 'BOM harus dalam status aktif untuk membuat work order.',
            ], 422);
        }

        $workOrder = $this->workOrderService->createFromBom($bom, $request->all());

        return response()->json([
            'message' => 'Work order berhasil dibuat dari BOM.',
            'data' => new WorkOrderResource($workOrder),
        ], 201);
    }

    /**
     * List sub-work orders for a work order.
     */
    public function subWorkOrders(WorkOrder $workOrder): AnonymousResourceCollection
    {
        $subWorkOrders = $workOrder->subWorkOrders()
            ->with(['project', 'product', 'bom'])
            ->orderByDesc('created_at')
            ->get();

        return WorkOrderResource::collection($subWorkOrders);
    }

    /**
     * Create sub-work order.
     */
    public function createSubWorkOrder(StoreWorkOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $subWorkOrder = $this->workOrderService->createSubWorkOrder($workOrder, $request->validated());

        return (new WorkOrderResource($subWorkOrder))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Confirm work order and reserve materials.
     */
    public function confirm(WorkOrder $workOrder): WorkOrderResource|JsonResponse
    {
        try {
            $workOrder = $this->workOrderService->confirm($workOrder);

            return new WorkOrderResource($workOrder);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Start work order.
     */
    public function start(WorkOrder $workOrder): WorkOrderResource|JsonResponse
    {
        try {
            $workOrder = $this->workOrderService->start($workOrder);

            return new WorkOrderResource($workOrder);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Complete work order.
     */
    public function complete(WorkOrder $workOrder): WorkOrderResource|JsonResponse
    {
        try {
            $workOrder = $this->workOrderService->complete($workOrder);

            return new WorkOrderResource($workOrder);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel work order.
     */
    public function cancel(Request $request, WorkOrder $workOrder): WorkOrderResource|JsonResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $workOrder = $this->workOrderService->cancel(
                $workOrder,
                $request->input('reason')
            );

            return new WorkOrderResource($workOrder);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Record output quantity.
     */
    public function recordOutput(Request $request, WorkOrder $workOrder): WorkOrderResource|JsonResponse
    {
        $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'scrapped' => ['nullable', 'numeric', 'min:0'],
        ], [
            'quantity.required' => 'Kuantitas output harus diisi.',
            'quantity.min' => 'Kuantitas output harus lebih dari 0.',
        ]);

        try {
            $workOrder = $this->workOrderService->recordOutput(
                $workOrder,
                $request->input('quantity'),
                $request->input('scrapped', 0)
            );

            return new WorkOrderResource($workOrder);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Record material consumption.
     */
    public function recordConsumption(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $request->validate([
            'consumptions' => ['required', 'array', 'min:1'],
            'consumptions.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'consumptions.*.work_order_item_id' => ['nullable', 'integer', 'exists:work_order_items,id'],
            'consumptions.*.quantity_consumed' => ['required', 'numeric', 'min:0'],
            'consumptions.*.quantity_scrapped' => ['nullable', 'numeric', 'min:0'],
            'consumptions.*.scrap_reason' => ['nullable', 'string', 'max:255'],
            'consumptions.*.unit' => ['nullable', 'string', 'max:20'],
            'consumptions.*.unit_cost' => ['nullable', 'integer', 'min:0'],
            'consumptions.*.consumed_date' => ['nullable', 'date'],
            'consumptions.*.batch_number' => ['nullable', 'string', 'max:50'],
            'consumptions.*.notes' => ['nullable', 'string', 'max:500'],
        ], [
            'consumptions.required' => 'Data konsumsi harus diisi.',
            'consumptions.*.product_id.required' => 'Produk harus dipilih.',
            'consumptions.*.quantity_consumed.required' => 'Kuantitas yang dikonsumsi harus diisi.',
        ]);

        try {
            $this->workOrderService->recordConsumption($workOrder, $request->input('consumptions'));

            return response()->json([
                'message' => 'Konsumsi material berhasil dicatat.',
                'data' => new WorkOrderResource($workOrder->fresh(['items', 'consumptions'])),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get cost summary.
     */
    public function costSummary(WorkOrder $workOrder): JsonResponse
    {
        $summary = $this->workOrderService->getCostSummary($workOrder);

        return response()->json(['data' => $summary]);
    }

    /**
     * Get material status.
     */
    public function materialStatus(WorkOrder $workOrder): JsonResponse
    {
        $status = $this->workOrderService->getMaterialStatus($workOrder);

        return response()->json(['data' => $status]);
    }

    /**
     * Get work order statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $statistics = $this->workOrderService->getStatistics(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json(['data' => $statistics]);
    }
}
