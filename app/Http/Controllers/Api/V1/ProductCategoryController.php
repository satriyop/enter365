<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\ProductCategoryFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductCategoryRequest;
use App\Http\Requests\Api\V1\UpdateProductCategoryRequest;
use App\Http\Resources\Api\V1\ProductCategoryResource;
use App\Models\Inventory\ProductCategory;
use App\Services\Inventory\ProductCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductCategoryController extends Controller
{
    public function __construct(
        private ProductCategoryService $categoryService
    ) {}

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
        $category = $this->categoryService->create($request->validated());

        return (new ProductCategoryResource($category))
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
        $category = $this->categoryService->update($productCategory, $request->validated());

        return new ProductCategoryResource($category);
    }

    public function destroy(ProductCategory $productCategory): JsonResponse
    {
        $this->categoryService->delete($productCategory);

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
