<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSubcontractorInvoiceRequest;
use App\Http\Requests\Api\V1\StoreSubcontractorWorkOrderRequest;
use App\Http\Requests\Api\V1\UpdateSubcontractorWorkOrderRequest;
use App\Http\Resources\Api\V1\SubcontractorInvoiceResource;
use App\Http\Resources\Api\V1\SubcontractorWorkOrderResource;
use App\Models\Accounting\SubcontractorWorkOrder;
use App\Services\Accounting\SubcontractorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubcontractorWorkOrderController extends Controller
{
    public function __construct(
        private SubcontractorService $subcontractorService
    ) {}

    /**
     * List all subcontractor work orders.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SubcontractorWorkOrder::query()
            ->with(['subcontractor', 'project', 'workOrder'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->subcontractor_id, fn ($q, $s) => $q->where('subcontractor_id', $s))
            ->when($request->project_id, fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->active, fn ($q) => $q->active())
            ->orderByDesc('created_at');

        $items = $request->per_page
            ? $query->paginate($request->per_page)
            : $query->get();

        return SubcontractorWorkOrderResource::collection($items);
    }

    /**
     * Create a new subcontractor work order.
     */
    public function store(StoreSubcontractorWorkOrderRequest $request): JsonResponse
    {
        $scWo = $this->subcontractorService->create($request->validated());

        return (new SubcontractorWorkOrderResource($scWo))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get a single subcontractor work order.
     */
    public function show(SubcontractorWorkOrder $subcontractorWorkOrder): SubcontractorWorkOrderResource
    {
        $subcontractorWorkOrder->load([
            'subcontractor',
            'project',
            'workOrder',
            'invoices.subcontractor',
        ]);

        return new SubcontractorWorkOrderResource($subcontractorWorkOrder);
    }

    /**
     * Update a subcontractor work order.
     */
    public function update(
        UpdateSubcontractorWorkOrderRequest $request,
        SubcontractorWorkOrder $subcontractorWorkOrder
    ): SubcontractorWorkOrderResource {
        $scWo = $this->subcontractorService->update($subcontractorWorkOrder, $request->validated());

        return new SubcontractorWorkOrderResource($scWo);
    }

    /**
     * Delete a subcontractor work order.
     */
    public function destroy(SubcontractorWorkOrder $subcontractorWorkOrder): JsonResponse
    {
        $this->subcontractorService->delete($subcontractorWorkOrder);

        return response()->json(['message' => 'Subcontractor work order berhasil dihapus.']);
    }

    /**
     * Assign work order to subcontractor.
     */
    public function assign(SubcontractorWorkOrder $subcontractorWorkOrder): SubcontractorWorkOrderResource
    {
        $scWo = $this->subcontractorService->assign($subcontractorWorkOrder);

        return new SubcontractorWorkOrderResource($scWo);
    }

    /**
     * Start work order.
     */
    public function start(SubcontractorWorkOrder $subcontractorWorkOrder): SubcontractorWorkOrderResource
    {
        $scWo = $this->subcontractorService->start($subcontractorWorkOrder);

        return new SubcontractorWorkOrderResource($scWo);
    }

    /**
     * Update progress.
     */
    public function updateProgress(
        Request $request,
        SubcontractorWorkOrder $subcontractorWorkOrder
    ): SubcontractorWorkOrderResource {
        $request->validate([
            'completion_percentage' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $scWo = $this->subcontractorService->updateProgress(
            $subcontractorWorkOrder,
            $request->input('completion_percentage')
        );

        return new SubcontractorWorkOrderResource($scWo);
    }

    /**
     * Complete work order.
     */
    public function complete(
        Request $request,
        SubcontractorWorkOrder $subcontractorWorkOrder
    ): SubcontractorWorkOrderResource {
        $request->validate([
            'actual_amount' => ['nullable', 'integer', 'min:0'],
        ]);

        $scWo = $this->subcontractorService->complete(
            $subcontractorWorkOrder,
            $request->input('actual_amount')
        );

        return new SubcontractorWorkOrderResource($scWo);
    }

    /**
     * Cancel work order.
     */
    public function cancel(
        Request $request,
        SubcontractorWorkOrder $subcontractorWorkOrder
    ): SubcontractorWorkOrderResource {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $scWo = $this->subcontractorService->cancel(
            $subcontractorWorkOrder,
            $request->input('reason')
        );

        return new SubcontractorWorkOrderResource($scWo);
    }

    /**
     * Create invoice for work order.
     */
    public function createInvoice(
        StoreSubcontractorInvoiceRequest $request,
        SubcontractorWorkOrder $subcontractorWorkOrder
    ): JsonResponse {
        $invoice = $this->subcontractorService->createInvoice(
            $subcontractorWorkOrder,
            $request->validated()
        );

        return (new SubcontractorInvoiceResource($invoice))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get invoices for work order.
     */
    public function invoices(SubcontractorWorkOrder $subcontractorWorkOrder): AnonymousResourceCollection
    {
        $invoices = $subcontractorWorkOrder->invoices()
            ->with(['subcontractor', 'bill'])
            ->orderByDesc('invoice_date')
            ->get();

        return SubcontractorInvoiceResource::collection($invoices);
    }

    /**
     * Get subcontractors list.
     */
    public function subcontractors(): JsonResponse
    {
        $subcontractors = $this->subcontractorService->getSubcontractors();

        return response()->json([
            'data' => $subcontractors->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'phone' => $s->phone,
                'email' => $s->email,
                'subcontractor_services' => $s->subcontractor_services,
                'hourly_rate' => $s->hourly_rate,
                'daily_rate' => $s->daily_rate,
                'active_work_orders_count' => $s->active_work_orders_count,
                'completed_work_orders_count' => $s->completed_work_orders_count,
            ]),
        ]);
    }

    /**
     * Get statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $stats = $this->subcontractorService->getStatistics(
            $request->input('subcontractor_id')
        );

        return response()->json($stats);
    }
}
