<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreGoodsReceiptNoteRequest;
use App\Http\Requests\Api\V1\UpdateGoodsReceiptNoteRequest;
use App\Http\Resources\Api\V1\GoodsReceiptNoteItemResource;
use App\Http\Resources\Api\V1\GoodsReceiptNoteResource;
use App\Models\Accounting\GoodsReceiptNote;
use App\Models\Accounting\GoodsReceiptNoteItem;
use App\Models\Accounting\PurchaseOrder;
use App\Services\Accounting\GoodsReceiptNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GoodsReceiptNoteController extends Controller
{
    public function __construct(
        private GoodsReceiptNoteService $grnService
    ) {}

    /**
     * Display a listing of GRNs.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = GoodsReceiptNote::query()
            ->with(['warehouse', 'purchaseOrder.contact', 'receivedByUser']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by warehouse
        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Filter by PO
        if ($request->has('purchase_order_id')) {
            $query->where('purchase_order_id', $request->purchase_order_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('receipt_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('receipt_date', '<=', $request->end_date);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('grn_number', 'like', "%{$search}%")
                    ->orWhere('supplier_do_number', 'like', "%{$search}%")
                    ->orWhere('supplier_invoice_number', 'like', "%{$search}%");
            });
        }

        $grns = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return GoodsReceiptNoteResource::collection($grns);
    }

    /**
     * Store a newly created GRN.
     */
    public function store(StoreGoodsReceiptNoteRequest $request): JsonResponse
    {
        try {
            $grn = $this->grnService->create($request->validated());

            return response()->json([
                'message' => 'GRN berhasil dibuat.',
                'data' => new GoodsReceiptNoteResource($grn->load(['warehouse', 'purchaseOrder', 'items'])),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Display the specified GRN.
     */
    public function show(GoodsReceiptNote $goodsReceiptNote): GoodsReceiptNoteResource
    {
        return new GoodsReceiptNoteResource(
            $goodsReceiptNote->load(['warehouse', 'purchaseOrder.contact', 'items.product', 'receivedByUser', 'checkedByUser', 'createdByUser'])
        );
    }

    /**
     * Update the specified GRN.
     */
    public function update(UpdateGoodsReceiptNoteRequest $request, GoodsReceiptNote $goodsReceiptNote): JsonResponse
    {
        try {
            $grn = $this->grnService->update($goodsReceiptNote, $request->validated());

            return response()->json([
                'message' => 'GRN berhasil diperbarui.',
                'data' => new GoodsReceiptNoteResource($grn->load(['warehouse', 'purchaseOrder', 'items'])),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove the specified GRN.
     */
    public function destroy(GoodsReceiptNote $goodsReceiptNote): JsonResponse
    {
        try {
            $this->grnService->delete($goodsReceiptNote);

            return response()->json([
                'message' => 'GRN berhasil dihapus.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Create a GRN from a Purchase Order.
     */
    public function createFromPurchaseOrder(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'receipt_date' => ['sometimes', 'date'],
            'supplier_do_number' => ['sometimes', 'string', 'max:100'],
            'supplier_invoice_number' => ['sometimes', 'string', 'max:100'],
            'vehicle_number' => ['sometimes', 'string', 'max:50'],
            'driver_name' => ['sometimes', 'string', 'max:100'],
            'notes' => ['sometimes', 'string', 'max:1000'],
        ], [
            'warehouse_id.required' => 'Gudang wajib dipilih.',
            'warehouse_id.exists' => 'Gudang tidak ditemukan.',
        ]);

        try {
            $grn = $this->grnService->createFromPurchaseOrder($purchaseOrder, $request->all());

            return response()->json([
                'message' => 'GRN berhasil dibuat dari Purchase Order.',
                'data' => new GoodsReceiptNoteResource($grn->load(['warehouse', 'purchaseOrder', 'items.product'])),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Update a GRN item.
     */
    public function updateItem(Request $request, GoodsReceiptNote $goodsReceiptNote, GoodsReceiptNoteItem $item): JsonResponse
    {
        // Ensure item belongs to this GRN
        if ($item->goods_receipt_note_id !== $goodsReceiptNote->id) {
            return response()->json(['message' => 'Item tidak ditemukan dalam GRN ini.'], 404);
        }

        $request->validate([
            'quantity_received' => ['sometimes', 'integer', 'min:0'],
            'quantity_rejected' => ['sometimes', 'integer', 'min:0'],
            'rejection_reason' => ['sometimes', 'string', 'max:500'],
            'quality_notes' => ['sometimes', 'string', 'max:500'],
            'lot_number' => ['sometimes', 'string', 'max:100'],
            'expiry_date' => ['sometimes', 'date'],
        ]);

        try {
            $item = $this->grnService->updateItem($item, $request->all());

            return response()->json([
                'message' => 'Item berhasil diperbarui.',
                'data' => new GoodsReceiptNoteItemResource($item->load('product')),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Start receiving workflow.
     */
    public function startReceiving(GoodsReceiptNote $goodsReceiptNote): JsonResponse
    {
        try {
            $grn = $this->grnService->startReceiving($goodsReceiptNote, auth()->id());

            return response()->json([
                'message' => 'Proses penerimaan dimulai.',
                'data' => new GoodsReceiptNoteResource($grn->load(['warehouse', 'purchaseOrder', 'items.product'])),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Complete the GRN and update inventory.
     */
    public function complete(GoodsReceiptNote $goodsReceiptNote): JsonResponse
    {
        try {
            $grn = $this->grnService->complete($goodsReceiptNote, auth()->id());

            return response()->json([
                'message' => 'GRN berhasil diselesaikan dan stok telah diperbarui.',
                'data' => new GoodsReceiptNoteResource($grn->load(['warehouse', 'purchaseOrder', 'items.product'])),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel the GRN.
     */
    public function cancel(GoodsReceiptNote $goodsReceiptNote): JsonResponse
    {
        try {
            $grn = $this->grnService->cancel($goodsReceiptNote, auth()->id());

            return response()->json([
                'message' => 'GRN berhasil dibatalkan.',
                'data' => new GoodsReceiptNoteResource($grn),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get GRNs for a Purchase Order.
     */
    public function forPurchaseOrder(PurchaseOrder $purchaseOrder): AnonymousResourceCollection
    {
        $grns = $this->grnService->getForPurchaseOrder($purchaseOrder);

        return GoodsReceiptNoteResource::collection($grns);
    }
}
