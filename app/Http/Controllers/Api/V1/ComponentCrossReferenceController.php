<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BomResource;
use App\Http\Resources\Api\V1\BomVariantGroupResource;
use App\Http\Resources\Api\V1\ComponentBrandMappingResource;
use App\Http\Resources\Api\V1\ComponentStandardResource;
use App\Models\Accounting\Bom;
use App\Models\Accounting\BomItem;
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

    // =========================================================================
    // Quick Swap - Inline Item-Level Brand Swap
    // =========================================================================

    /**
     * Get alternatives for a BOM item.
     *
     * Returns all equivalent products from other brands that can replace the item.
     *
     * @queryParam bom int required The BOM ID. Example: 1
     * @queryParam item int required The BOM item ID. Example: 5
     *
     * @response 200 {
     *   "data": {
     *     "current": {
     *       "product_id": 1,
     *       "product_name": "MCB Schneider 16A",
     *       "product_sku": "SCH-MCB-16A",
     *       "unit_cost": 185000,
     *       "brand": "schneider",
     *       "brand_label": "Schneider Electric"
     *     },
     *     "alternatives": [
     *       {
     *         "product_id": 5,
     *         "product_name": "MCB ABB 16A",
     *         "product_sku": "ABB-S201-C16",
     *         "brand": "abb",
     *         "brand_label": "ABB",
     *         "unit_cost": 165000,
     *         "price_diff": -20000,
     *         "price_diff_percent": -10.8,
     *         "is_preferred": false,
     *         "stock": 50
     *       }
     *     ],
     *     "has_standard": true
     *   }
     * }
     */
    public function getItemAlternatives(Bom $bom, BomItem $item): JsonResponse
    {
        // Verify item belongs to BOM
        if ($item->bom_id !== $bom->id) {
            return response()->json(['message' => 'Item does not belong to this BOM'], 404);
        }

        $data = $this->service->getItemAlternatives($item);

        return response()->json(['data' => $data]);
    }

    /**
     * Quick swap a BOM item to a different product.
     *
     * Replaces the product in-place without creating a new BOM.
     * Use this for quick edits; use swap-brand for creating variants.
     *
     * @bodyParam product_id int required The new product ID. Example: 5
     * @bodyParam reason string Optional reason for the swap. Example: cost_optimization
     *
     * @response 200 {
     *   "message": "Item berhasil diganti.",
     *   "data": {
     *     "item": { "id": 5, "description": "MCB ABB 16A", "unit_cost": 165000 },
     *     "previous": { "product_id": 1, "product_name": "MCB Schneider 16A", "unit_cost": 185000 },
     *     "new": { "product_id": 5, "product_name": "MCB ABB 16A", "unit_cost": 165000 },
     *     "savings": 20000
     *   }
     * }
     * @response 422 {"message": "Product tidak valid untuk item ini."}
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

        $result = $this->service->quickSwapItem(
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
     *
     * Returns products that don't have any component mappings yet.
     *
     * @queryParam brand string Filter by brand code. Example: schneider
     * @queryParam limit int Max products to return. Default: 50. Example: 100
     *
     * @response 200 {
     *   "data": [
     *     {"id": 1, "name": "MCB Schneider 16A 1P", "sku": "A9F74116", "brand": "schneider"},
     *     {"id": 2, "name": "MCB Schneider 20A 1P", "sku": "A9F74120", "brand": "schneider"}
     *   ],
     *   "meta": {"total": 2, "brand": "schneider"}
     * }
     */
    public function getUnmappedProducts(Request $request): JsonResponse
    {
        $brand = $request->input('brand');
        $limit = min($request->input('limit', 50), 200);

        $products = $this->service->getUnmappedProducts($brand, $limit);

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
     *
     * Parses product name to extract specs and suggests matching component standards.
     *
     * @response 200 {
     *   "data": {
     *     "product_id": 1,
     *     "product_name": "MCB Schneider Easy9 16A 1P C-Curve",
     *     "product_sku": "A9F74116",
     *     "product_brand": "schneider",
     *     "parsed_specs": {
     *       "category": "circuit_breaker",
     *       "subcategory": "mcb",
     *       "specs": {"rating_amps": 16, "poles": 1, "curve": "C"},
     *       "brand": "schneider"
     *     },
     *     "suggestions": [
     *       {
     *         "component_standard_id": 5,
     *         "code": "MCB-1P-16A-C",
     *         "name": "MCB 1 Pole 16A C-Curve",
     *         "match_score": 95,
     *         "existing_brands": ["abb", "siemens"]
     *       }
     *     ],
     *     "has_suggestions": true
     *   }
     * }
     */
    public function suggestMapping(Product $product): JsonResponse
    {
        $suggestion = $this->service->suggestMappingForProduct($product);

        return response()->json([
            'data' => $suggestion,
        ]);
    }

    /**
     * Get mapping suggestions for multiple products.
     *
     * Batch version of suggestMapping for efficiency.
     *
     * @bodyParam product_ids array required List of product IDs. Example: [1, 2, 3]
     *
     * @response 200 {
     *   "data": [
     *     {"product_id": 1, "product_name": "MCB Schneider 16A", "suggestions": [...], "has_suggestions": true},
     *     {"product_id": 2, "product_name": "Cable NYY 3x2.5", "suggestions": [...], "has_suggestions": true}
     *   ]
     * }
     */
    public function suggestMappingsBatch(Request $request): JsonResponse
    {
        $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:50'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $products = Product::whereIn('id', $request->product_ids)->get();
        $suggestions = $this->service->suggestMappingsForProducts($products);

        return response()->json([
            'data' => $suggestions,
        ]);
    }

    /**
     * Accept a single mapping suggestion.
     *
     * Creates a brand mapping for the product to the selected component standard.
     *
     * @bodyParam component_standard_id int required The component standard ID. Example: 5
     * @bodyParam brand_sku string Optional vendor SKU. Example: A9F74116
     * @bodyParam is_preferred bool Set as preferred mapping. Default: false. Example: true
     *
     * @response 201 {
     *   "message": "Mapping berhasil dibuat.",
     *   "data": {
     *     "id": 10,
     *     "component_standard_id": 5,
     *     "brand": "schneider",
     *     "product_id": 1,
     *     "brand_sku": "A9F74116",
     *     "is_preferred": false,
     *     "is_verified": false
     *   }
     * }
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

        $standard = \App\Models\Accounting\ComponentStandard::findOrFail($request->component_standard_id);

        $mapping = $this->service->acceptMappingSuggestion(
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
     *
     * Creates multiple mappings at once for efficiency.
     *
     * @bodyParam mappings array required Array of mappings to create.
     * @bodyParam mappings[].product_id int required Product ID. Example: 1
     * @bodyParam mappings[].component_standard_id int required Component standard ID. Example: 5
     * @bodyParam mappings[].brand_sku string Optional vendor SKU. Example: A9F74116
     * @bodyParam mappings[].is_preferred bool Set as preferred. Default: false. Example: false
     *
     * @response 201 {
     *   "message": "Bulk mapping selesai.",
     *   "data": {
     *     "created": 8,
     *     "skipped": 2,
     *     "errors": []
     *   }
     * }
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

        $result = $this->service->bulkAcceptMappingSuggestions($request->mappings);

        $status = $result['created'] > 0 ? 201 : 200;

        return response()->json([
            'message' => 'Bulk mapping selesai.',
            'data' => $result,
        ], $status);
    }

    /**
     * Parse a product name to extract specs (debug/preview endpoint).
     *
     * Useful for testing the parsing logic before accepting suggestions.
     *
     * @queryParam name string required Product name to parse. Example: MCB Schneider Easy9 16A 1P C-Curve
     *
     * @response 200 {
     *   "data": {
     *     "category": "circuit_breaker",
     *     "subcategory": "mcb",
     *     "specs": {"rating_amps": 16, "poles": 1, "curve": "C"},
     *     "brand": "schneider"
     *   }
     * }
     */
    public function parseProductName(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $parsed = $this->service->parseProductName($request->name);

        return response()->json([
            'data' => $parsed,
        ]);
    }
}
