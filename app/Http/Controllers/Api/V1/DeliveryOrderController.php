<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDeliveryOrderRequest;
use App\Http\Requests\Api\V1\UpdateDeliveryOrderRequest;
use App\Http\Resources\Api\V1\DeliveryOrderResource;
use App\Models\Accounting\DeliveryOrder;
use App\Models\Accounting\Invoice;
use App\Services\Accounting\DeliveryOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeliveryOrderController extends Controller
{
    public function __construct(
        private DeliveryOrderService $deliveryOrderService
    ) {}

    /**
     * Display a listing of delivery orders.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DeliveryOrder::query()
            ->with(['contact', 'invoice', 'warehouse', 'creator'])
            ->withCount('items');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by contact
        if ($request->has('contact_id')) {
            $query->where('contact_id', $request->contact_id);
        }

        // Filter by invoice
        if ($request->has('invoice_id')) {
            $query->where('invoice_id', $request->invoice_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('do_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('do_date', '<=', $request->end_date);
        }

        // Filter by warehouse
        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('do_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('driver_name', 'like', "%{$search}%")
                    ->orWhereHas('contact', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Sorting
        $sortField = $request->get('sort', 'do_date');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $deliveryOrders = $request->has('per_page')
            ? $query->paginate($request->per_page)
            : $query->get();

        return DeliveryOrderResource::collection($deliveryOrders);
    }

    /**
     * Store a newly created delivery order.
     */
    public function store(StoreDeliveryOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        $deliveryOrder = $this->deliveryOrderService->create($data);

        return response()->json([
            'message' => 'Delivery order created successfully.',
            'data' => new DeliveryOrderResource($deliveryOrder),
        ], 201);
    }

    /**
     * Create delivery order from invoice.
     */
    public function createFromInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'do_date' => ['sometimes', 'date'],
            'shipping_address' => ['nullable', 'string', 'max:500'],
            'shipping_method' => ['nullable', 'string', 'max:50'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['created_by'] = $request->user()?->id;

        $deliveryOrder = $this->deliveryOrderService->createFromInvoice($invoice, $data);

        return response()->json([
            'message' => 'Delivery order created from invoice successfully.',
            'data' => new DeliveryOrderResource($deliveryOrder),
        ], 201);
    }

    /**
     * Display the specified delivery order.
     */
    public function show(DeliveryOrder $deliveryOrder): DeliveryOrderResource
    {
        $deliveryOrder->load(['items.product', 'contact', 'invoice', 'warehouse', 'creator']);

        return new DeliveryOrderResource($deliveryOrder);
    }

    /**
     * Update the specified delivery order.
     */
    public function update(UpdateDeliveryOrderRequest $request, DeliveryOrder $deliveryOrder): JsonResponse
    {
        try {
            $deliveryOrder = $this->deliveryOrderService->update($deliveryOrder, $request->validated());

            return response()->json([
                'message' => 'Delivery order updated successfully.',
                'data' => new DeliveryOrderResource($deliveryOrder),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove the specified delivery order.
     */
    public function destroy(DeliveryOrder $deliveryOrder): JsonResponse
    {
        try {
            $this->deliveryOrderService->delete($deliveryOrder);

            return response()->json(['message' => 'Delivery order deleted successfully.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Confirm a delivery order.
     */
    public function confirm(Request $request, DeliveryOrder $deliveryOrder): JsonResponse
    {
        try {
            $deliveryOrder = $this->deliveryOrderService->confirm(
                $deliveryOrder,
                $request->user()?->id
            );

            return response()->json([
                'message' => 'Delivery order confirmed successfully.',
                'data' => new DeliveryOrderResource($deliveryOrder),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Ship a delivery order.
     */
    public function ship(Request $request, DeliveryOrder $deliveryOrder): JsonResponse
    {
        $data = $request->validate([
            'shipping_date' => ['sometimes', 'date'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'vehicle_number' => ['nullable', 'string', 'max:20'],
        ]);

        $data['shipped_by'] = $request->user()?->id;

        try {
            $deliveryOrder = $this->deliveryOrderService->ship($deliveryOrder, $data);

            return response()->json([
                'message' => 'Delivery order shipped successfully.',
                'data' => new DeliveryOrderResource($deliveryOrder),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Mark delivery order as delivered.
     */
    public function deliver(Request $request, DeliveryOrder $deliveryOrder): JsonResponse
    {
        $data = $request->validate([
            'received_date' => ['sometimes', 'date'],
            'received_by' => ['nullable', 'string', 'max:100'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['delivered_by'] = $request->user()?->id;

        try {
            $deliveryOrder = $this->deliveryOrderService->deliver($deliveryOrder, $data);

            return response()->json([
                'message' => 'Delivery order marked as delivered.',
                'data' => new DeliveryOrderResource($deliveryOrder),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel a delivery order.
     */
    public function cancel(Request $request, DeliveryOrder $deliveryOrder): JsonResponse
    {
        try {
            $reason = $request->input('reason');
            $deliveryOrder = $this->deliveryOrderService->cancel($deliveryOrder, $reason);

            return response()->json([
                'message' => 'Delivery order cancelled successfully.',
                'data' => new DeliveryOrderResource($deliveryOrder),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Update delivery progress (partial delivery).
     */
    public function updateProgress(Request $request, DeliveryOrder $deliveryOrder): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:delivery_order_items,id'],
            'items.*.quantity_delivered' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $deliveryOrder = $this->deliveryOrderService->updateDeliveryProgress(
                $deliveryOrder,
                $data['items']
            );

            return response()->json([
                'message' => 'Delivery progress updated successfully.',
                'data' => new DeliveryOrderResource($deliveryOrder),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Duplicate a delivery order.
     */
    public function duplicate(DeliveryOrder $deliveryOrder): JsonResponse
    {
        $newDo = $this->deliveryOrderService->duplicate($deliveryOrder);

        return response()->json([
            'message' => 'Delivery order duplicated successfully.',
            'data' => new DeliveryOrderResource($newDo),
        ], 201);
    }

    /**
     * Get delivery orders for an invoice.
     */
    public function forInvoice(Invoice $invoice): AnonymousResourceCollection
    {
        $deliveryOrders = $this->deliveryOrderService->getForInvoice($invoice);

        return DeliveryOrderResource::collection($deliveryOrders);
    }

    /**
     * Get delivery order statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $query = DeliveryOrder::query();

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('do_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('do_date', '<=', $request->end_date);
        }

        $stats = [
            'total_count' => (clone $query)->count(),
            'by_status' => [
                'draft' => (clone $query)->where('status', DeliveryOrder::STATUS_DRAFT)->count(),
                'confirmed' => (clone $query)->where('status', DeliveryOrder::STATUS_CONFIRMED)->count(),
                'shipped' => (clone $query)->where('status', DeliveryOrder::STATUS_SHIPPED)->count(),
                'delivered' => (clone $query)->where('status', DeliveryOrder::STATUS_DELIVERED)->count(),
                'cancelled' => (clone $query)->where('status', DeliveryOrder::STATUS_CANCELLED)->count(),
            ],
            'pending_delivery' => (clone $query)->whereIn('status', [
                DeliveryOrder::STATUS_CONFIRMED,
                DeliveryOrder::STATUS_SHIPPED,
            ])->count(),
            'delivered_this_month' => DeliveryOrder::query()
                ->where('status', DeliveryOrder::STATUS_DELIVERED)
                ->whereMonth('delivered_at', now()->month)
                ->whereYear('delivered_at', now()->year)
                ->count(),
        ];

        return response()->json($stats);
    }
}
