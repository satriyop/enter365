<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBomVariantGroupRequest;
use App\Http\Requests\Api\V1\UpdateBomVariantGroupRequest;
use App\Http\Resources\Api\V1\BomResource;
use App\Http\Resources\Api\V1\BomVariantGroupResource;
use App\Models\Accounting\Bom;
use App\Models\Accounting\BomVariantGroup;
use App\Services\Accounting\BomVariantGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class BomVariantGroupController extends Controller
{
    public function __construct(
        private BomVariantGroupService $service
    ) {}

    /**
     * Display a listing of variant groups.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = BomVariantGroup::query()->with(['product', 'boms']);

        if ($request->has('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('product', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]));
            });
        }

        $groups = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 25));

        return BomVariantGroupResource::collection($groups);
    }

    /**
     * Store a newly created variant group.
     */
    public function store(StoreBomVariantGroupRequest $request): JsonResponse
    {
        $group = $this->service->create($request->validated());

        return (new BomVariantGroupResource($group))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified variant group.
     */
    public function show(BomVariantGroup $bomVariantGroup): BomVariantGroupResource
    {
        return new BomVariantGroupResource(
            $bomVariantGroup->load(['product', 'boms.items', 'creator'])
        );
    }

    /**
     * Update the specified variant group.
     */
    public function update(
        UpdateBomVariantGroupRequest $request,
        BomVariantGroup $bomVariantGroup
    ): BomVariantGroupResource {
        $group = $this->service->update($bomVariantGroup, $request->validated());

        return new BomVariantGroupResource($group);
    }

    /**
     * Remove the specified variant group.
     */
    public function destroy(BomVariantGroup $bomVariantGroup): JsonResponse
    {
        $this->service->delete($bomVariantGroup);

        return response()->json(['message' => 'Variant group berhasil dihapus.']);
    }

    /**
     * Get side-by-side comparison data.
     */
    public function compare(BomVariantGroup $bomVariantGroup): JsonResponse
    {
        $comparison = $this->service->getComparisonData($bomVariantGroup);

        return response()->json(['data' => $comparison]);
    }

    /**
     * Get detailed item-level comparison.
     */
    public function compareDetailed(BomVariantGroup $bomVariantGroup): JsonResponse
    {
        $comparison = $this->service->getDetailedComparison($bomVariantGroup);

        return response()->json(['data' => $comparison]);
    }

    /**
     * Add a BOM to the variant group.
     */
    public function addBom(Request $request, BomVariantGroup $bomVariantGroup): JsonResponse
    {
        $request->validate([
            'bom_id' => ['required', 'integer', 'exists:boms,id'],
            'variant_name' => ['nullable', 'string', 'max:100'],
            'variant_label' => ['nullable', 'string', 'max:255'],
            'is_primary_variant' => ['nullable', 'boolean'],
        ], [
            'bom_id.required' => 'BOM harus dipilih.',
            'bom_id.exists' => 'BOM tidak ditemukan.',
        ]);

        try {
            $bom = Bom::findOrFail($request->input('bom_id'));
            $updatedBom = $this->service->addBom($bomVariantGroup, $bom, $request->only([
                'variant_name',
                'variant_label',
                'is_primary_variant',
            ]));

            return response()->json([
                'message' => 'BOM berhasil ditambahkan ke variant group.',
                'data' => new BomResource($updatedBom),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove a BOM from the variant group.
     */
    public function removeBom(BomVariantGroup $bomVariantGroup, Bom $bom): JsonResponse
    {
        try {
            $this->service->removeBom($bomVariantGroup, $bom);

            return response()->json(['message' => 'BOM berhasil dihapus dari variant group.']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Set primary variant.
     */
    public function setPrimary(BomVariantGroup $bomVariantGroup, Bom $bom): JsonResponse
    {
        try {
            $updatedBom = $this->service->setPrimaryVariant($bomVariantGroup, $bom);

            return response()->json([
                'message' => 'Primary variant berhasil diubah.',
                'data' => new BomResource($updatedBom),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Reorder variants.
     */
    public function reorder(Request $request, BomVariantGroup $bomVariantGroup): JsonResponse
    {
        $request->validate([
            'order' => ['required', 'array'],
            'order.*.bom_id' => ['required', 'integer', 'exists:boms,id'],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $orderMap = collect($request->input('order'))
            ->pluck('sort_order', 'bom_id')
            ->toArray();

        $group = $this->service->reorderVariants($bomVariantGroup, $orderMap);

        return response()->json([
            'message' => 'Urutan variant berhasil diubah.',
            'data' => new BomVariantGroupResource($group),
        ]);
    }

    /**
     * Create a new variant from existing BOM.
     */
    public function createVariant(Request $request, BomVariantGroup $bomVariantGroup): JsonResponse
    {
        $request->validate([
            'source_bom_id' => ['required', 'integer', 'exists:boms,id'],
            'variant_name' => ['required', 'string', 'max:100'],
            'variant_label' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'is_primary_variant' => ['nullable', 'boolean'],
        ], [
            'source_bom_id.required' => 'BOM sumber harus dipilih.',
            'variant_name.required' => 'Nama variant harus diisi.',
        ]);

        try {
            $sourceBom = Bom::findOrFail($request->input('source_bom_id'));
            $newBom = $this->service->createVariantFromBom(
                $bomVariantGroup,
                $sourceBom,
                $request->only(['variant_name', 'variant_label', 'name', 'is_primary_variant'])
            );

            return response()->json([
                'message' => 'Variant baru berhasil dibuat.',
                'data' => new BomResource($newBom),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
