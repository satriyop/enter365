<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\PurchaseOrderFilter;
use App\Http\Requests\Api\V1\StorePurchaseOrderRequest;
use App\Http\Requests\Api\V1\UpdatePurchaseOrderRequest;
use App\Http\Resources\Api\V1\BillResource;
use App\Http\Resources\Api\V1\PurchaseOrderResource;
use App\Models\Purchasing\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private PurchaseOrderService $purchaseOrderService
    ) {}

    /**
     * Display a listing of purchase orders.
     */
    public function index(PurchaseOrderFilter $filter): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $purchaseOrders = PurchaseOrder::query()
            ->with(['contact'])
            ->withCount(['items'])
            ->withSum('items as total_quantity', 'quantity')
            ->withSum('items as total_received', 'quantity_received')
            ->filter($filter)
            ->paginate($filter->getRequest()->input('per_page', 25));

        return PurchaseOrderResource::collection($purchaseOrders);
    }

    /**
     * Store a newly created purchase order.
     */
    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $this->authorize('create', PurchaseOrder::class);

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
        $this->authorize('view', $purchaseOrder);

        return new PurchaseOrderResource(
            $purchaseOrder->load(['contact', 'items.product', 'revisions', 'convertedBill'])
        );
    }

    /**
     * Update the specified purchase order.
     */
    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('update', $purchaseOrder);

        $purchaseOrder = $this->purchaseOrderService->update($purchaseOrder, $request->validated());

        return new PurchaseOrderResource($purchaseOrder);
    }

    /**
     * Remove the specified purchase order.
     */
    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('delete', $purchaseOrder);

        if (! $purchaseOrder->isEditable()) {
            return $this->error('Hanya PO draft yang dapat dihapus.', 422);
        }

        $purchaseOrder->delete();

        return $this->deleted('PO berhasil dihapus.');
    }

    /**
     * Submit PO for approval.
     */
    public function submit(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('manage', $purchaseOrder);

        $purchaseOrder = $this->purchaseOrderService->submit($purchaseOrder);

        return new PurchaseOrderResource($purchaseOrder);
    }

    /**
     * Approve a PO.
     */
    public function approve(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('approve', $purchaseOrder);

        $purchaseOrder = $this->purchaseOrderService->approve($purchaseOrder);

        return new PurchaseOrderResource($purchaseOrder);
    }

    /**
     * Reject a PO.
     */
    public function reject(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource|JsonResponse
    {
        $this->authorize('manage', $purchaseOrder);

        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Alasan penolakan harus diisi.',
        ]);

        $purchaseOrder = $this->purchaseOrderService->reject($purchaseOrder, $request->input('reason'));

        return new PurchaseOrderResource($purchaseOrder);
    }

    /**
     * Cancel a PO.
     */
    public function cancel(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource|JsonResponse
    {
        $this->authorize('manage', $purchaseOrder);

        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Alasan pembatalan harus diisi.',
        ]);

        $purchaseOrder = $this->purchaseOrderService->cancel($purchaseOrder, $request->input('reason'));

        return new PurchaseOrderResource($purchaseOrder);
    }

    /**
     * Receive items for a PO.
     */
    public function receive(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource|JsonResponse
    {
        $this->authorize('manage', $purchaseOrder);

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

        $purchaseOrder = $this->purchaseOrderService->receive($purchaseOrder, $request->input('items'));

        return new PurchaseOrderResource($purchaseOrder);
    }

    /**
     * Convert PO to bill.
     */
    public function convertToBill(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('manage', $purchaseOrder);

        $bill = $this->purchaseOrderService->convertToBill($purchaseOrder);

        return $this->success([
            'bill' => new BillResource($bill),
            'purchase_order' => new PurchaseOrderResource($purchaseOrder->fresh(['contact', 'items'])),
        ], 'PO berhasil dikonversi menjadi tagihan.', 201);
    }

    /**
     * Duplicate a PO.
     */
    public function duplicate(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('manage', $purchaseOrder);

        $newPo = $this->purchaseOrderService->duplicate($purchaseOrder);

        return $this->created(
            new PurchaseOrderResource($newPo),
            'PO berhasil diduplikasi.'
        );
    }

    /**
     * Get outstanding POs.
     */
    public function outstanding(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseOrder::class);

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
        $this->authorize('viewAny', PurchaseOrder::class);

        $statistics = $this->purchaseOrderService->getStatistics(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return $this->success($statistics);
    }
}
