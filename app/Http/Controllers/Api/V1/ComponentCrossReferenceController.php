<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BomResource;
use App\Http\Resources\Api\V1\BomVariantGroupResource;
use App\Http\Resources\Api\V1\ComponentBrandMappingResource;
use App\Http\Resources\Api\V1\ComponentStandardResource;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\BomVariantGroup;
use App\Models\Manufacturing\ComponentBrandMapping;
use App\Models\Manufacturing\ComponentStandard;
use App\Services\Manufacturing\BrandSwapService;
use App\Services\Manufacturing\ComponentMappingService;
use App\Services\Manufacturing\CostOptimizationService;
use App\Services\Manufacturing\ProductEquivalenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComponentCrossReferenceController extends Controller
{
    public function __construct(
        private ProductEquivalenceService $equivalenceService,
        private BrandSwapService $brandSwapService,
        private CostOptimizationService $costOptimizationService,
        private ComponentMappingService $mappingService
    ) {}

    /**
     * Find equivalent products for a given product.
     */
    public function productEquivalents(Request $request, Product $product): JsonResponse
    {
        $targetBrand = $request->input('brand');
        $equivalents = $this->equivalenceService->findEquivalents($product, $targetBrand);

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

        $results = $this->equivalenceService->searchBySpecs(
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
        $comparison = $this->brandSwapService->compareBrands($bom);

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

        $preview = $this->brandSwapService->previewSwapBrand(
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

        $result = $this->brandSwapService->swapBomBrand(
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

        $result = $this->brandSwapService->generateBrandVariants(
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
        $preview = $this->costOptimizationService->previewOptimization($bom);

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

        $result = $this->costOptimizationService->applyOptimization(
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

    // =========================================================================
    // Quick Swap - Inline Item-Level Brand Swap
    // =========================================================================

    /**
     * Get alternatives for a BOM item.
     */
    public function getItemAlternatives(Bom $bom, BomItem $item): JsonResponse
    {
        // Verify item belongs to BOM
        if ($item->bom_id !== $bom->id) {
            return response()->json(['message' => 'Item does not belong to this BOM'], 404);
        }

        $data = $this->brandSwapService->getItemAlternatives($item);

        return response()->json(['data' => $data]);
    }

    /**
     * Quick swap a BOM item to a different product.
     */
    public function quickSwapItem(Request $request, Bom $bom, BomItem $item): JsonResponse
    {
        // Verify item belongs to BOM
        if ($item->bom_id !== $bom->id) {
            return response()->json(['message' => 'Item does not belong to this BOM'], 404);
        }

        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $newProduct = Product::findOrFail($request->product_id);

        // Verify the new product is a valid alternative (has same component standard)
        if ($item->component_standard_id) {
            $validMapping = ComponentBrandMapping::query()
                ->where('product_id', $newProduct->id)
                ->where('component_standard_id', $item->component_standard_id)
                ->exists();

            if (! $validMapping) {
                return response()->json([
                    'message' => 'Product tidak valid untuk item ini. Pastikan product memiliki component standard yang sama.',
                ], 422);
            }
        }

        $result = $this->brandSwapService->quickSwapItem(
            $item,
            $newProduct,
            $request->reason
        );

        return response()->json([
            'message' => 'Item berhasil diganti.',
            'data' => $result,
        ]);
    }

    // =========================================================================
    // Auto-Mapping - Suggest Component Standards from Product Names
    // =========================================================================

    /**
     * Get unmapped products for a given brand.
     */
    public function getUnmappedProducts(Request $request): JsonResponse
    {
        $brand = $request->input('brand');
        $limit = min($request->input('limit', 50), 200);

        $products = $this->mappingService->getUnmappedProducts($brand, $limit);

        return response()->json([
            'data' => $products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'brand' => $p->brand,
                'purchase_price' => $p->purchase_price,
            ]),
            'meta' => [
                'total' => $products->count(),
                'brand' => $brand,
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * Get mapping suggestions for a single product.
     */
    public function suggestMapping(Product $product): JsonResponse
    {
        $suggestion = $this->mappingService->suggestMappingForProduct($product);

        return response()->json([
            'data' => $suggestion,
        ]);
    }

    /**
     * Get mapping suggestions for multiple products.
     */
    public function suggestMappingsBatch(Request $request): JsonResponse
    {
        $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:50'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $products = Product::whereIn('id', $request->product_ids)->get();
        $suggestions = $this->mappingService->suggestMappingsForProducts($products);

        return response()->json([
            'data' => $suggestions,
        ]);
    }

    /**
     * Accept a single mapping suggestion.
     */
    public function acceptSuggestion(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'component_standard_id' => ['required', 'integer', 'exists:component_standards,id'],
            'brand_sku' => ['nullable', 'string', 'max:100'],
            'is_preferred' => ['nullable', 'boolean'],
        ]);

        // Check if mapping already exists
        $exists = ComponentBrandMapping::query()
            ->where('product_id', $product->id)
            ->where('component_standard_id', $request->component_standard_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Mapping untuk product ini sudah ada.',
            ], 422);
        }

        $standard = ComponentStandard::findOrFail($request->component_standard_id);

        $mapping = $this->mappingService->acceptMappingSuggestion(
            $product,
            $standard,
            $request->brand_sku,
            $request->boolean('is_preferred', false)
        );

        return response()->json([
            'message' => 'Mapping berhasil dibuat.',
            'data' => new ComponentBrandMappingResource($mapping->load('product', 'componentStandard')),
        ], 201);
    }

    /**
     * Bulk accept mapping suggestions.
     */
    public function bulkAcceptSuggestions(Request $request): JsonResponse
    {
        $request->validate([
            'mappings' => ['required', 'array', 'min:1', 'max:100'],
            'mappings.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'mappings.*.component_standard_id' => ['required', 'integer', 'exists:component_standards,id'],
            'mappings.*.brand_sku' => ['nullable', 'string', 'max:100'],
            'mappings.*.is_preferred' => ['nullable', 'boolean'],
        ]);

        $result = $this->mappingService->bulkAcceptMappingSuggestions($request->mappings);

        $status = $result['created'] > 0 ? 201 : 200;

        return response()->json([
            'message' => 'Bulk mapping selesai.',
            'data' => $result,
        ], $status);
    }

    /**
     * Parse a product name to extract specs (debug/preview endpoint).
     */
    public function parseProductName(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $parsed = $this->mappingService->parseProductName($request->name);

        return response()->json([
            'data' => $parsed,
        ]);
    }
}
