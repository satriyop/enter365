<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWarehouseRequest;
use App\Http\Requests\Api\V1\UpdateWarehouseRequest;
use App\Http\Resources\Api\V1\WarehouseResource;
use App\Models\Accounting\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Warehouse::query();

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search
        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(code) LIKE ?', ["%{$search}%"]);
            });
        }

        $warehouses = $query->withCount('productStocks')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate($request->input('per_page', 25));

        return WarehouseResource::collection($warehouses);
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Generate code if not provided
        if (empty($data['code'])) {
            $data['code'] = Warehouse::generateCode();
        }

        // Set defaults
        $data['is_active'] = $data['is_active'] ?? true;
        $data['is_default'] = $data['is_default'] ?? false;

        // If this is the first warehouse, make it default
        if (! Warehouse::exists()) {
            $data['is_default'] = true;
        }

        // If marked as default, unset other defaults
        if ($data['is_default']) {
            Warehouse::where('is_default', true)->update(['is_default' => false]);
        }

        $warehouse = Warehouse::create($data);

        return (new WarehouseResource($warehouse))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Warehouse $warehouse): WarehouseResource
    {
        return new WarehouseResource(
            $warehouse->loadCount('productStocks')
        );
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): WarehouseResource
    {
        $data = $request->validated();

        // If marked as default, unset other defaults
        if (isset($data['is_default']) && $data['is_default']) {
            Warehouse::where('is_default', true)
                ->where('id', '!=', $warehouse->id)
                ->update(['is_default' => false]);
        }

        $warehouse->update($data);

        return new WarehouseResource($warehouse->fresh());
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        // Check for stock
        if ($warehouse->productStocks()->where('quantity', '>', 0)->exists()) {
            return response()->json([
                'message' => 'Gudang tidak bisa dihapus karena masih memiliki stok.',
            ], 422);
        }

        // Check if default
        if ($warehouse->is_default) {
            return response()->json([
                'message' => 'Gudang default tidak bisa dihapus. Tetapkan gudang lain sebagai default terlebih dahulu.',
            ], 422);
        }

        // Delete related stock records (with zero quantity)
        $warehouse->productStocks()->delete();
        $warehouse->delete();

        return response()->json([
            'message' => 'Gudang berhasil dihapus.',
        ]);
    }

    /**
     * Set warehouse as default.
     */
    public function setDefault(Warehouse $warehouse): JsonResponse
    {
        if (! $warehouse->is_active) {
            return response()->json([
                'message' => 'Gudang tidak aktif tidak bisa dijadikan default.',
            ], 422);
        }

        $warehouse->setAsDefault();

        return response()->json([
            'message' => 'Gudang berhasil ditetapkan sebagai default.',
            'data' => new WarehouseResource($warehouse->fresh()),
        ]);
    }

    /**
     * Get stock summary for a warehouse.
     */
    public function stockSummary(Warehouse $warehouse): JsonResponse
    {
        $stocks = $warehouse->productStocks()
            ->with('product:id,sku,name,unit')
            ->where('quantity', '>', 0)
            ->get();

        return response()->json([
            'warehouse' => [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
            ],
            'summary' => [
                'total_items' => $stocks->count(),
                'total_quantity' => $stocks->sum('quantity'),
                'total_value' => $stocks->sum('total_value'),
            ],
            'stocks' => $stocks->map(fn ($stock) => [
                'product_id' => $stock->product_id,
                'product_sku' => $stock->product->sku,
                'product_name' => $stock->product->name,
                'unit' => $stock->product->unit,
                'quantity' => $stock->quantity,
                'average_cost' => $stock->average_cost,
                'total_value' => $stock->total_value,
            ]),
        ]);
    }
}
