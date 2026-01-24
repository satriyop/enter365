<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\ProductCategoryFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductCategoryRequest;
use App\Http\Requests\Api\V1\UpdateProductCategoryRequest;
use App\Http\Resources\Api\V1\ProductCategoryResource;
use App\Models\Inventory\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductCategoryController extends Controller
{
    public function index(ProductCategoryFilter $filter): AnonymousResourceCollection
    {
        $categories = ProductCategory::query()
            ->with(['parent'])
            ->filter($filter)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($filter->getRequest()->input('per_page', 25));

        return ProductCategoryResource::collection($categories);
    }

    public function store(StoreProductCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['code'])) {
            $data['code'] = ProductCategory::generateCode($data['parent_id'] ?? null);
        }

        $data['is_active'] = $data['is_active'] ?? true;
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $category = ProductCategory::create($data);

        return (new ProductCategoryResource($category->load('parent')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ProductCategory $productCategory, ProductCategoryFilter $filter): ProductCategoryResource
    {
        $filter->apply($productCategory->newQuery());

        $productCategory->loadMissing(['parent', 'children', 'products']);

        return new ProductCategoryResource($productCategory);
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory): ProductCategoryResource
    {
        $productCategory->update($request->validated());

        return new ProductCategoryResource($productCategory->fresh('parent'));
    }

    public function destroy(ProductCategory $productCategory): JsonResponse
    {
        if ($productCategory->hasChildren()) {
            return response()->json([
                'message' => 'Kategori tidak bisa dihapus karena memiliki sub-kategori.',
            ], 422);
        }

        if ($productCategory->products()->exists()) {
            return response()->json([
                'message' => 'Kategori tidak bisa dihapus karena memiliki produk.',
            ], 422);
        }

        $productCategory->delete();

        return response()->json([
            'message' => 'Kategori berhasil dihapus.',
        ]);
    }

    /**
     * Get category tree structure.
     */
    public function tree(): JsonResponse
    {
        $categories = ProductCategory::query()
            ->active()
            ->root()
            ->with('descendants')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => ProductCategoryResource::collection($categories),
        ]);
    }
}
