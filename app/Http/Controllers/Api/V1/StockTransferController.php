<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\StockTransferFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStockTransferRequest;
use App\Http\Resources\Api\V1\StockTransferResource;
use App\Models\Inventory\StockTransfer;
use App\Services\Inventory\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockTransferController extends Controller
{
    public function __construct(
        private StockTransferService $stockTransfers
    ) {}

    public function index(StockTransferFilter $filter): AnonymousResourceCollection
    {
        $transfers = StockTransfer::query()
            ->with(['fromWarehouse', 'toWarehouse', 'contact'])
            ->filter($filter)
            ->latest('id')
            ->paginate($filter->getRequest()->input('per_page', 15));

        return StockTransferResource::collection($transfers);
    }

    public function store(StoreStockTransferRequest $request): JsonResponse
    {
        $transfer = $this->stockTransfers->create($request->validated());

        return response()->json([
            'message' => 'Transfer stok berhasil dibuat.',
            'data' => new StockTransferResource($transfer),
        ], 201);
    }

    public function show(StockTransfer $stockTransfer): StockTransferResource
    {
        $stockTransfer->loadMissing(['fromWarehouse', 'toWarehouse', 'contact', 'items.product', 'createdByUser']);

        return new StockTransferResource($stockTransfer);
    }

    public function confirm(StockTransfer $stockTransfer): JsonResponse
    {
        $transfer = $this->stockTransfers->confirm($stockTransfer);

        return response()->json([
            'message' => 'Transfer stok selesai.',
            'data' => new StockTransferResource($transfer),
        ]);
    }

    public function cancel(StockTransfer $stockTransfer): JsonResponse
    {
        $transfer = $this->stockTransfers->cancel($stockTransfer);

        return response()->json([
            'message' => 'Transfer stok dibatalkan.',
            'data' => new StockTransferResource($transfer),
        ]);
    }
}
