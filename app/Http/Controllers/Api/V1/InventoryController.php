<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StockAdjustmentRequest;
use App\Http\Requests\Api\V1\StockInRequest;
use App\Http\Requests\Api\V1\StockOutRequest;
use App\Http\Requests\Api\V1\StockTransferRequest;
use App\Http\Resources\Api\V1\InventoryMovementResource;
use App\Http\Resources\Api\V1\ProductStockResource;
use App\Models\Accounting\InventoryMovement;
use App\Models\Accounting\Product;
use App\Models\Accounting\ProductStock;
use App\Models\Accounting\Warehouse;
use App\Services\Accounting\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * List inventory movements.
     */
    public function movements(Request $request): AnonymousResourceCollection
    {
        $query = InventoryMovement::query()
            ->with(['product', 'warehouse', 'createdByUser']);

        // Filter by product
        if ($request->has('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        // Filter by warehouse
        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('movement_date', '>=', $request->input('start_date'));
        }
        if ($request->has('end_date')) {
            $query->where('movement_date', '<=', $request->input('end_date'));
        }

        $movements = $query->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 25));

        return InventoryMovementResource::collection($movements);
    }

    /**
     * Record stock in.
     */
    public function stockIn(StockInRequest $request): JsonResponse
    {
        $data = $request->validated();

        $product = Product::findOrFail($data['product_id']);

        if (! $product->track_inventory) {
            return response()->json([
                'message' => 'Produk ini tidak melacak inventori.',
            ], 422);
        }

        $warehouse = isset($data['warehouse_id'])
            ? Warehouse::findOrFail($data['warehouse_id'])
            : Warehouse::getDefault();

        if (! $warehouse) {
            return response()->json([
                'message' => 'Tidak ada gudang default. Silakan buat gudang terlebih dahulu.',
            ], 422);
        }

        $movement = $this->inventoryService->stockIn(
            $product,
            $warehouse,
            $data['quantity'],
            $data['unit_cost'],
            $data['notes'] ?? null
        );

        return response()->json([
            'message' => 'Stok masuk berhasil dicatat.',
            'data' => new InventoryMovementResource($movement->load(['product', 'warehouse'])),
        ], 201);
    }

    /**
     * Record stock out.
     */
    public function stockOut(StockOutRequest $request): JsonResponse
    {
        $data = $request->validated();

        $product = Product::findOrFail($data['product_id']);

        if (! $product->track_inventory) {
            return response()->json([
                'message' => 'Produk ini tidak melacak inventori.',
            ], 422);
        }

        $warehouse = isset($data['warehouse_id'])
            ? Warehouse::findOrFail($data['warehouse_id'])
            : Warehouse::getDefault();

        if (! $warehouse) {
            return response()->json([
                'message' => 'Tidak ada gudang default. Silakan buat gudang terlebih dahulu.',
            ], 422);
        }

        // Check available stock
        $availableStock = $product->getStockInWarehouse($warehouse);
        if ($availableStock < $data['quantity']) {
            return response()->json([
                'message' => "Stok tidak mencukupi. Tersedia: {$availableStock}, diminta: {$data['quantity']}",
            ], 422);
        }

        $movement = $this->inventoryService->stockOut(
            $product,
            $warehouse,
            $data['quantity'],
            $data['notes'] ?? null
        );

        return response()->json([
            'message' => 'Stok keluar berhasil dicatat.',
            'data' => new InventoryMovementResource($movement->load(['product', 'warehouse'])),
        ], 201);
    }

    /**
     * Adjust stock.
     */
    public function adjust(StockAdjustmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $product = Product::findOrFail($data['product_id']);

        if (! $product->track_inventory) {
            return response()->json([
                'message' => 'Produk ini tidak melacak inventori.',
            ], 422);
        }

        $warehouse = isset($data['warehouse_id'])
            ? Warehouse::findOrFail($data['warehouse_id'])
            : Warehouse::getDefault();

        if (! $warehouse) {
            return response()->json([
                'message' => 'Tidak ada gudang default. Silakan buat gudang terlebih dahulu.',
            ], 422);
        }

        $movement = $this->inventoryService->adjust(
            $product,
            $warehouse,
            $data['new_quantity'],
            $data['new_unit_cost'] ?? null,
            $data['notes'] ?? null
        );

        return response()->json([
            'message' => 'Penyesuaian stok berhasil.',
            'data' => new InventoryMovementResource($movement->load(['product', 'warehouse'])),
        ], 201);
    }

    /**
     * Transfer stock between warehouses.
     */
    public function transfer(StockTransferRequest $request): JsonResponse
    {
        $data = $request->validated();

        $product = Product::findOrFail($data['product_id']);

        if (! $product->track_inventory) {
            return response()->json([
                'message' => 'Produk ini tidak melacak inventori.',
            ], 422);
        }

        $fromWarehouse = Warehouse::findOrFail($data['from_warehouse_id']);
        $toWarehouse = Warehouse::findOrFail($data['to_warehouse_id']);

        // Check available stock
        $availableStock = $product->getStockInWarehouse($fromWarehouse);
        if ($availableStock < $data['quantity']) {
            return response()->json([
                'message' => "Stok tidak mencukupi di {$fromWarehouse->name}. Tersedia: {$availableStock}, diminta: {$data['quantity']}",
            ], 422);
        }

        try {
            $movements = $this->inventoryService->transfer(
                $product,
                $fromWarehouse,
                $toWarehouse,
                $data['quantity'],
                $data['notes'] ?? null
            );

            return response()->json([
                'message' => 'Transfer stok berhasil.',
                'data' => [
                    'out' => new InventoryMovementResource($movements['out']->load(['product', 'warehouse', 'transferWarehouse'])),
                    'in' => new InventoryMovementResource($movements['in']->load(['product', 'warehouse', 'transferWarehouse'])),
                ],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get stock card for a product.
     */
    public function stockCard(Request $request, Product $product): JsonResponse
    {
        if (! $product->track_inventory) {
            return response()->json([
                'message' => 'Produk ini tidak melacak inventori.',
            ], 422);
        }

        $warehouse = $request->has('warehouse_id')
            ? Warehouse::findOrFail($request->input('warehouse_id'))
            : null;

        $movements = $this->inventoryService->getStockCard(
            $product,
            $warehouse,
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json([
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'unit' => $product->unit,
                'current_stock' => $product->current_stock,
            ],
            'warehouse' => $warehouse ? [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
            ] : null,
            'movements' => InventoryMovementResource::collection($movements),
        ]);
    }

    /**
     * Get stock valuation report.
     */
    public function valuation(Request $request): JsonResponse
    {
        $warehouse = $request->has('warehouse_id')
            ? Warehouse::findOrFail($request->input('warehouse_id'))
            : null;

        $valuation = $this->inventoryService->getStockValuation($warehouse);

        $totalValue = $valuation->sum('total_value');

        return response()->json([
            'warehouse' => $warehouse ? [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
            ] : null,
            'summary' => [
                'total_items' => $valuation->count(),
                'total_value' => $totalValue,
            ],
            'items' => $valuation,
        ]);
    }

    /**
     * Get inventory summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $warehouse = $request->has('warehouse_id')
            ? Warehouse::findOrFail($request->input('warehouse_id'))
            : null;

        $summary = $this->inventoryService->getInventorySummary($warehouse);

        return response()->json([
            'warehouse' => $warehouse ? [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
            ] : null,
            'summary' => $summary,
        ]);
    }

    /**
     * Get movement summary for a period.
     */
    public function movementSummary(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $warehouse = $request->has('warehouse_id')
            ? Warehouse::findOrFail($request->input('warehouse_id'))
            : null;

        $summary = $this->inventoryService->getMovementSummary(
            $request->input('start_date'),
            $request->input('end_date'),
            $warehouse
        );

        return response()->json([
            'warehouse' => $warehouse ? [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
            ] : null,
            'summary' => $summary,
        ]);
    }

    /**
     * Get stock levels.
     */
    public function stockLevels(Request $request): AnonymousResourceCollection
    {
        $query = ProductStock::query()
            ->with(['product', 'warehouse'])
            ->where('quantity', '>', 0);

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->has('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        $stocks = $query->orderBy('product_id')
            ->paginate($request->input('per_page', 25));

        return ProductStockResource::collection($stocks);
    }
}
