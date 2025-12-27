<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBomRequest;
use App\Http\Requests\Api\V1\UpdateBomRequest;
use App\Http\Resources\Api\V1\BomResource;
use App\Models\Accounting\Bom;
use App\Models\Accounting\Product;
use App\Services\Accounting\BomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class BomController extends Controller
{
    public function __construct(
        private BomService $bomService
    ) {}

    /**
     * Display a listing of BOMs.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Bom::query()->with(['product', 'items']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(bom_number) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('product', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]));
            });
        }

        $boms = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 25));

        return BomResource::collection($boms);
    }

    /**
     * Store a newly created BOM.
     */
    public function store(StoreBomRequest $request): JsonResponse
    {
        $bom = $this->bomService->create($request->validated());

        return (new BomResource($bom))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified BOM.
     */
    public function show(Bom $bom): BomResource
    {
        return new BomResource(
            $bom->load(['product', 'items.product', 'creator', 'parentBom'])
        );
    }

    /**
     * Update the specified BOM.
     */
    public function update(UpdateBomRequest $request, Bom $bom): BomResource|JsonResponse
    {
        try {
            $bom = $this->bomService->update($bom, $request->validated());

            return new BomResource($bom);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove the specified BOM.
     */
    public function destroy(Bom $bom): JsonResponse
    {
        try {
            $this->bomService->delete($bom);

            return response()->json(['message' => 'BOM berhasil dihapus.']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Activate a BOM.
     */
    public function activate(Bom $bom): BomResource|JsonResponse
    {
        try {
            $bom = $this->bomService->activate($bom, auth()->id());

            return new BomResource($bom);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Deactivate a BOM.
     */
    public function deactivate(Bom $bom): BomResource|JsonResponse
    {
        try {
            $bom = $this->bomService->deactivate($bom);

            return new BomResource($bom);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Duplicate a BOM.
     */
    public function duplicate(Bom $bom): JsonResponse
    {
        $newBom = $this->bomService->duplicate($bom);

        return response()->json([
            'message' => 'BOM berhasil diduplikasi.',
            'data' => new BomResource($newBom),
        ], 201);
    }

    /**
     * Get active BOM for a product.
     */
    public function forProduct(Product $product): JsonResponse
    {
        $bom = $this->bomService->getActiveForProduct($product);

        if (! $bom) {
            return response()->json([
                'message' => 'Tidak ada BOM aktif untuk produk ini.',
            ], 404);
        }

        return response()->json([
            'data' => new BomResource($bom->load(['items'])),
        ]);
    }

    /**
     * Calculate production cost for a quantity.
     */
    public function calculateCost(Request $request): JsonResponse
    {
        $request->validate([
            'bom_id' => ['required', 'integer', 'exists:boms,id'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
        ], [
            'bom_id.required' => 'BOM harus dipilih.',
            'bom_id.exists' => 'BOM tidak ditemukan.',
            'quantity.required' => 'Kuantitas harus diisi.',
            'quantity.min' => 'Kuantitas harus lebih dari 0.',
        ]);

        $bom = Bom::with(['items'])->findOrFail($request->input('bom_id'));

        if ($bom->status !== Bom::STATUS_ACTIVE) {
            return response()->json([
                'message' => 'Hanya BOM aktif yang dapat digunakan untuk kalkulasi.',
            ], 422);
        }

        $calculation = $this->bomService->calculateProductionCost($bom, $request->input('quantity'));

        return response()->json(['data' => $calculation]);
    }

    /**
     * Get BOM statistics.
     */
    public function statistics(): JsonResponse
    {
        $statistics = $this->bomService->getStatistics();

        return response()->json(['data' => $statistics]);
    }
}
