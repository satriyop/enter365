<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BomResource;
use App\Http\Resources\Api\V1\BomVariantGroupResource;
use App\Http\Resources\Api\V1\ComponentBrandMappingResource;
use App\Http\Resources\Api\V1\ComponentStandardResource;
use App\Models\Accounting\Bom;
use App\Models\Accounting\BomVariantGroup;
use App\Models\Accounting\ComponentBrandMapping;
use App\Models\Accounting\Product;
use App\Services\Accounting\ComponentCrossReferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComponentCrossReferenceController extends Controller
{
    public function __construct(
        private ComponentCrossReferenceService $service
    ) {}

    /**
     * Find equivalent products for a given product.
     */
    public function productEquivalents(Request $request, Product $product): JsonResponse
    {
        $targetBrand = $request->input('brand');
        $equivalents = $this->service->findEquivalents($product, $targetBrand);

        return response()->json([
            'data' => ComponentBrandMappingResource::collection($equivalents),
            'source_product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'brand' => $product->brand,
            ],
        ]);
    }

    /**
     * Search components by specifications.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'category' => 'required|string',
            'specs' => 'nullable|array',
            'brand' => 'nullable|string',
        ]);

        $results = $this->service->searchBySpecs(
            $request->input('category'),
            $request->input('specs', []),
            $request->input('brand')
        );

        return response()->json([
            'data' => ComponentStandardResource::collection($results),
        ]);
    }

    /**
     * Compare BOM across all available brands.
     * Returns side-by-side comparison for quick decision making.
     */
    public function compareBrands(Bom $bom): JsonResponse
    {
        $comparison = $this->service->compareBrands($bom);

        return response()->json([
            'data' => $comparison,
        ]);
    }

    /**
     * Preview swap without creating a new BOM.
     * Returns estimated costs and item-by-item breakdown.
     */
    public function previewSwapBrand(Request $request, Bom $bom): JsonResponse
    {
        $request->validate([
            'target_brand' => 'required|string',
        ]);

        $preview = $this->service->previewSwapBrand(
            $bom,
            $request->input('target_brand')
        );

        return response()->json([
            'data' => $preview,
        ]);
    }

    /**
     * Swap BOM to a different brand.
     */
    public function swapBrand(Request $request, Bom $bom): JsonResponse
    {
        $request->validate([
            'target_brand' => 'required|string',
            'create_variant' => 'nullable|boolean',
            'variant_group_id' => 'nullable|integer|exists:bom_variant_groups,id',
        ]);

        $variantGroup = null;
        if ($request->has('variant_group_id')) {
            $variantGroup = BomVariantGroup::find(
                $request->input('variant_group_id')
            );
        }

        $result = $this->service->swapBomBrand(
            $bom,
            $request->input('target_brand'),
            $request->boolean('create_variant', true),
            $variantGroup
        );

        return response()->json([
            'message' => 'Brand swap berhasil.',
            'data' => [
                'bom' => new BomResource($result['bom']),
                'swap_report' => $result['swap_report'],
            ],
        ], 201);
    }

    /**
     * Generate all brand variants for a BOM.
     */
    public function generateBrandVariants(Request $request, Bom $bom): JsonResponse
    {
        $request->validate([
            'brands' => 'required|array|min:1',
            'brands.*' => 'string',
            'group_name' => 'nullable|string|max:255',
        ]);

        $result = $this->service->generateBrandVariants(
            $bom,
            $request->input('brands'),
            $request->input('group_name')
        );

        return response()->json([
            'message' => 'Brand variants berhasil dibuat.',
            'data' => [
                'variant_group' => new BomVariantGroupResource($result['variant_group']),
                'boms' => BomResource::collection($result['boms']),
                'report' => $result['report'],
            ],
        ], 201);
    }

    /**
     * Preview mixed-brand cost optimization.
     * Finds cheapest alternative for each item across all brands.
     */
    public function previewCostOptimization(Bom $bom): JsonResponse
    {
        $preview = $this->service->previewCostOptimization($bom);

        return response()->json([
            'data' => $preview,
        ]);
    }

    /**
     * Apply cost optimization to create a new BOM with cheapest alternatives.
     */
    public function applyCostOptimization(Request $request, Bom $bom): JsonResponse
    {
        $request->validate([
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'integer|exists:bom_items,id',
        ]);

        $result = $this->service->applyCostOptimization(
            $bom,
            $request->input('item_ids', [])
        );

        return response()->json([
            'message' => 'Cost optimization berhasil diterapkan.',
            'data' => [
                'bom' => new BomResource($result['bom']),
                'optimization_report' => $result['optimization_report'],
            ],
        ], 201);
    }

    /**
     * Get available brands from mappings.
     */
    public function availableBrands(): JsonResponse
    {
        $brands = ComponentBrandMapping::query()
            ->select('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand')
            ->map(fn ($brand) => [
                'code' => $brand,
                'name' => ComponentBrandMapping::getBrands()[$brand] ?? ucfirst($brand),
            ]);

        return response()->json(['data' => $brands]);
    }
}
