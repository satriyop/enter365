<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\InventoryMovementFilter;
use App\Filters\ProductStockFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StockAdjustmentRequest;
use App\Http\Requests\Api\V1\StockInRequest;
use App\Http\Requests\Api\V1\StockOutRequest;
use App\Http\Requests\Api\V1\StockTransferRequest;
use App\Http\Resources\Api\V1\InventoryMovementResource;
use App\Http\Resources\Api\V1\ProductStockResource;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\InventoryService;
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
    public function movements(InventoryMovementFilter $filter): AnonymousResourceCollection
    {
        $movements = InventoryMovement::query()
            ->with(['product', 'warehouse']) // Default eager loads
            ->filter($filter)
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->paginate($filter->getRequest()->input('per_page', 25));

        return InventoryMovementResource::collection($movements);
    }

    /**
     * Record stock in.
     * 
     * @response array{message: string, data: InventoryMovementResource}
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
     * 
     * @response array{message: string, data: InventoryMovementResource}
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
     * 
     * @response array{message: string, data: InventoryMovementResource}
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
     * 
     * @response array{message: string, data: array{out: InventoryMovementResource, in: InventoryMovementResource}}
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
     * 
     * @response array{product: array{id: int, sku: string, name: string, unit: string, current_stock: float}, warehouse: array{id: int, code: string, name: string}|null, movements: \Illuminate\Http\Resources\Json\AnonymousResourceCollection}
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
     * 
     * @response array{warehouse: array{id: int, code: string, name: string}|null, summary: array{total_items: int, total_value: float}, items: array<mixed>}
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
     * 
     * @response array{warehouse: array{id: int, code: string, name: string}|null, summary: array<mixed>}
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
     * 
     * @response array{warehouse: array{id: int, code: string, name: string}|null, summary: array<mixed>}
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
    public function stockLevels(ProductStockFilter $filter): AnonymousResourceCollection
    {
        $stocks = ProductStock::query()
            ->with(['product', 'warehouse'])
            ->where('quantity', '>', 0)
            ->filter($filter)
            ->orderBy('product_id')
            ->paginate($filter->getRequest()->input('per_page', 25));

        return ProductStockResource::collection($stocks);
    }
}
