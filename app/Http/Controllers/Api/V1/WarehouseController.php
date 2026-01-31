<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\WarehouseFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWarehouseRequest;
use App\Http\Requests\Api\V1\UpdateWarehouseRequest;
use App\Http\Resources\Api\V1\WarehouseResource;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\WarehouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseController extends Controller
{
    public function __construct(
        private WarehouseService $warehouseService
    ) {}

    public function index(WarehouseFilter $filter): AnonymousResourceCollection
    {
        $warehouses = Warehouse::query()
            ->filter($filter)
            ->withCount('productStocks')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate($filter->getRequest()->input('per_page', 25));

        return WarehouseResource::collection($warehouses);
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = $this->warehouseService->create($request->validated());

        return (new WarehouseResource($warehouse))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Warehouse $warehouse, WarehouseFilter $filter): WarehouseResource
    {
        $filter->apply($warehouse->newQuery());

        $warehouse->loadMissing(['productStocks']);
        $warehouse->loadCount('productStocks');

        return new WarehouseResource($warehouse);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): WarehouseResource
    {
        $warehouse = $this->warehouseService->update($warehouse, $request->validated());

        return new WarehouseResource($warehouse);
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->warehouseService->delete($warehouse);

        return response()->json([
            'message' => 'Gudang berhasil dihapus.',
        ]);
    }

    /**
     * Set warehouse as default.
     */
    public function setDefault(Warehouse $warehouse): JsonResponse
    {
        $warehouse = $this->warehouseService->setAsDefault($warehouse);

        return response()->json([
            'message' => 'Gudang berhasil ditetapkan sebagai default.',
            'data' => new WarehouseResource($warehouse),
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
