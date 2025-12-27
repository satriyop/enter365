<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStockOpnameRequest;
use App\Http\Requests\Api\V1\UpdateStockOpnameRequest;
use App\Http\Resources\Api\V1\StockOpnameItemResource;
use App\Http\Resources\Api\V1\StockOpnameResource;
use App\Models\Accounting\StockOpname;
use App\Models\Accounting\StockOpnameItem;
use App\Services\Accounting\StockOpnameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockOpnameController extends Controller
{
    public function __construct(
        private StockOpnameService $stockOpnameService
    ) {}

    /**
     * Display a listing of stock opnames.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = StockOpname::query()
            ->with(['warehouse', 'createdByUser']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by warehouse
        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('opname_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('opname_date', '<=', $request->end_date);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('opname_number', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%");
            });
        }

        $opnames = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return StockOpnameResource::collection($opnames);
    }

    /**
     * Store a newly created stock opname.
     */
    public function store(StoreStockOpnameRequest $request): JsonResponse
    {
        $opname = $this->stockOpnameService->create($request->validated());

        return response()->json([
            'message' => 'Stock opname berhasil dibuat.',
            'data' => new StockOpnameResource($opname->load(['warehouse', 'items'])),
        ], 201);
    }

    /**
     * Display the specified stock opname.
     */
    public function show(StockOpname $stockOpname): StockOpnameResource
    {
        return new StockOpnameResource(
            $stockOpname->load(['warehouse', 'items.product', 'countedByUser', 'reviewedByUser', 'approvedByUser', 'createdByUser'])
        );
    }

    /**
     * Update the specified stock opname.
     */
    public function update(UpdateStockOpnameRequest $request, StockOpname $stockOpname): JsonResponse
    {
        try {
            $opname = $this->stockOpnameService->update($stockOpname, $request->validated());

            return response()->json([
                'message' => 'Stock opname berhasil diperbarui.',
                'data' => new StockOpnameResource($opname->load(['warehouse', 'items'])),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove the specified stock opname.
     */
    public function destroy(StockOpname $stockOpname): JsonResponse
    {
        try {
            $this->stockOpnameService->delete($stockOpname);

            return response()->json([
                'message' => 'Stock opname berhasil dihapus.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Generate items from warehouse stock.
     */
    public function generateItems(StockOpname $stockOpname): JsonResponse
    {
        try {
            $opname = $this->stockOpnameService->generateItems($stockOpname);

            return response()->json([
                'message' => 'Item berhasil di-generate dari stok gudang.',
                'data' => new StockOpnameResource($opname->load(['warehouse', 'items.product'])),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Add an item manually.
     */
    public function addItem(Request $request, StockOpname $stockOpname): JsonResponse
    {
        $request->validate([
            'product_id' => ['required', 'exists:products,id'],
        ], [
            'product_id.required' => 'Produk wajib dipilih.',
            'product_id.exists' => 'Produk tidak ditemukan.',
        ]);

        try {
            $item = $this->stockOpnameService->addItem($stockOpname, $request->all());

            return response()->json([
                'message' => 'Item berhasil ditambahkan.',
                'data' => new StockOpnameItemResource($item->load('product')),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Update an item (record count).
     */
    public function updateItem(Request $request, StockOpname $stockOpname, StockOpnameItem $item): JsonResponse
    {
        // Ensure item belongs to this stock opname
        if ($item->stock_opname_id !== $stockOpname->id) {
            return response()->json(['message' => 'Item tidak ditemukan dalam stock opname ini.'], 404);
        }

        $request->validate([
            'counted_quantity' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['sometimes', 'string', 'max:500'],
        ], [
            'counted_quantity.integer' => 'Jumlah hitung harus berupa angka.',
            'counted_quantity.min' => 'Jumlah hitung tidak boleh negatif.',
            'notes.max' => 'Catatan maksimal 500 karakter.',
        ]);

        try {
            $item = $this->stockOpnameService->updateItem($item, $request->all());

            return response()->json([
                'message' => 'Item berhasil diperbarui.',
                'data' => new StockOpnameItemResource($item->load('product')),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove an item.
     */
    public function removeItem(StockOpname $stockOpname, StockOpnameItem $item): JsonResponse
    {
        // Ensure item belongs to this stock opname
        if ($item->stock_opname_id !== $stockOpname->id) {
            return response()->json(['message' => 'Item tidak ditemukan dalam stock opname ini.'], 404);
        }

        try {
            $this->stockOpnameService->removeItem($item);

            return response()->json([
                'message' => 'Item berhasil dihapus.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Start counting workflow.
     */
    public function startCounting(StockOpname $stockOpname): JsonResponse
    {
        try {
            $opname = $this->stockOpnameService->startCounting($stockOpname, auth()->id());

            return response()->json([
                'message' => 'Penghitungan stock opname dimulai.',
                'data' => new StockOpnameResource($opname->load(['warehouse', 'items.product'])),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Submit for review.
     */
    public function submitForReview(StockOpname $stockOpname): JsonResponse
    {
        try {
            $opname = $this->stockOpnameService->submitForReview($stockOpname, auth()->id());

            return response()->json([
                'message' => 'Stock opname berhasil disubmit untuk review.',
                'data' => new StockOpnameResource($opname->load(['warehouse', 'items.product'])),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Approve and apply adjustments.
     */
    public function approve(StockOpname $stockOpname): JsonResponse
    {
        try {
            $opname = $this->stockOpnameService->approve($stockOpname, auth()->id());

            return response()->json([
                'message' => 'Stock opname berhasil diapprove dan penyesuaian stok telah diterapkan.',
                'data' => new StockOpnameResource($opname->load(['warehouse', 'items.product'])),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Reject and return to counting.
     */
    public function reject(Request $request, StockOpname $stockOpname): JsonResponse
    {
        $request->validate([
            'reason' => ['sometimes', 'string', 'max:500'],
        ]);

        try {
            $opname = $this->stockOpnameService->reject(
                $stockOpname,
                auth()->id(),
                $request->input('reason')
            );

            return response()->json([
                'message' => 'Stock opname ditolak dan dikembalikan ke status counting.',
                'data' => new StockOpnameResource($opname->load(['warehouse', 'items.product'])),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel stock opname.
     */
    public function cancel(StockOpname $stockOpname): JsonResponse
    {
        try {
            $opname = $this->stockOpnameService->cancel($stockOpname, auth()->id());

            return response()->json([
                'message' => 'Stock opname berhasil dibatalkan.',
                'data' => new StockOpnameResource($opname),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get variance report.
     */
    public function varianceReport(StockOpname $stockOpname): JsonResponse
    {
        $report = $this->stockOpnameService->getVarianceReport($stockOpname);

        return response()->json([
            'data' => $report,
        ]);
    }
}
