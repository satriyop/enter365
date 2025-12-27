<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePurchaseOrderRequest;
use App\Http\Requests\Api\V1\UpdatePurchaseOrderRequest;
use App\Http\Resources\Api\V1\BillResource;
use App\Http\Resources\Api\V1\PurchaseOrderResource;
use App\Models\Accounting\PurchaseOrder;
use App\Services\Accounting\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private PurchaseOrderService $purchaseOrderService
    ) {}

    /**
     * Display a listing of purchase orders.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PurchaseOrder::query()->with(['contact', 'items']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('contact_id')) {
            $query->where('contact_id', $request->input('contact_id'));
        }

        if ($request->has('start_date')) {
            $query->where('po_date', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $query->where('po_date', '<=', $request->input('end_date'));
        }

        if ($request->boolean('outstanding_only')) {
            $query->outstanding();
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(po_number) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(subject) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(reference) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('contact', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]));
            });
        }

        $purchaseOrders = $query->orderByDesc('po_date')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 25));

        return PurchaseOrderResource::collection($purchaseOrders);
    }

    /**
     * Store a newly created purchase order.
     */
    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $purchaseOrder = $this->purchaseOrderService->create($request->validated());

        return (new PurchaseOrderResource($purchaseOrder))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified purchase order.
     */
    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource(
            $purchaseOrder->load(['contact', 'items.product', 'revisions', 'convertedBill'])
        );
    }

    /**
     * Update the specified purchase order.
     */
    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource|JsonResponse
    {
        try {
            $purchaseOrder = $this->purchaseOrderService->update($purchaseOrder, $request->validated());

            return new PurchaseOrderResource($purchaseOrder);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove the specified purchase order.
     */
    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (! $purchaseOrder->isEditable()) {
            return response()->json([
                'message' => 'Hanya PO draft yang dapat dihapus.',
            ], 422);
        }

        $purchaseOrder->delete();

        return response()->json(['message' => 'PO berhasil dihapus.']);
    }

    /**
     * Submit PO for approval.
     */
    public function submit(PurchaseOrder $purchaseOrder): PurchaseOrderResource|JsonResponse
    {
        try {
            $purchaseOrder = $this->purchaseOrderService->submit($purchaseOrder);

            return new PurchaseOrderResource($purchaseOrder);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Approve a PO.
     */
    public function approve(PurchaseOrder $purchaseOrder): PurchaseOrderResource|JsonResponse
    {
        try {
            $purchaseOrder = $this->purchaseOrderService->approve($purchaseOrder);

            return new PurchaseOrderResource($purchaseOrder);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Reject a PO.
     */
    public function reject(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource|JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Alasan penolakan harus diisi.',
        ]);

        try {
            $purchaseOrder = $this->purchaseOrderService->reject($purchaseOrder, $request->input('reason'));

            return new PurchaseOrderResource($purchaseOrder);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel a PO.
     */
    public function cancel(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource|JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Alasan pembatalan harus diisi.',
        ]);

        try {
            $purchaseOrder = $this->purchaseOrderService->cancel($purchaseOrder, $request->input('reason'));

            return new PurchaseOrderResource($purchaseOrder);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Receive items for a PO.
     */
    public function receive(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource|JsonResponse
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ], [
            'items.required' => 'Item yang diterima harus diisi.',
            'items.*.item_id.required' => 'ID item harus diisi.',
            'items.*.item_id.exists' => 'Item tidak ditemukan.',
            'items.*.quantity.required' => 'Jumlah terima harus diisi.',
            'items.*.quantity.min' => 'Jumlah terima harus lebih dari 0.',
        ]);

        try {
            $purchaseOrder = $this->purchaseOrderService->receive($purchaseOrder, $request->input('items'));

            return new PurchaseOrderResource($purchaseOrder);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Convert PO to bill.
     */
    public function convertToBill(PurchaseOrder $purchaseOrder): JsonResponse
    {
        try {
            $bill = $this->purchaseOrderService->convertToBill($purchaseOrder);

            return response()->json([
                'message' => 'PO berhasil dikonversi menjadi tagihan.',
                'bill' => new BillResource($bill),
                'purchase_order' => new PurchaseOrderResource($purchaseOrder->fresh(['contact', 'items'])),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Duplicate a PO.
     */
    public function duplicate(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $newPo = $this->purchaseOrderService->duplicate($purchaseOrder);

        return response()->json([
            'message' => 'PO berhasil diduplikasi.',
            'data' => new PurchaseOrderResource($newPo),
        ], 201);
    }

    /**
     * Get outstanding POs.
     */
    public function outstanding(Request $request): AnonymousResourceCollection
    {
        $purchaseOrders = $this->purchaseOrderService->getOutstanding(
            $request->input('contact_id')
        );

        return PurchaseOrderResource::collection($purchaseOrders);
    }

    /**
     * Get PO statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $statistics = $this->purchaseOrderService->getStatistics(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json(['data' => $statistics]);
    }
}
