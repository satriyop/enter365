<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Inventory\ProductServiceInterface;
use App\Filters\ProductFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(
        private ProductServiceInterface $productService
    ) {}

    /**
     * Display a listing of products.
     */
    public function index(ProductFilter $filter): AnonymousResourceCollection
    {
        $products = Product::query()
            ->with(['category']) // Default eager loads
            ->filter($filter)
            ->paginate($filter->getRequest()->input('per_page', 25));

        return ProductResource::collection($products);
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create($request->validated());

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product, ProductFilter $filter): ProductResource
    {
        $filter->apply($product->newQuery());

        $product->loadMissing([
            'category',
            'inventoryAccount',
            'cogsAccount',
            'salesAccount',
            'purchaseAccount',
        ]);

        return new ProductResource($product);
    }

    /**
     * Update the specified product.
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product = $this->productService->update($product, $request->validated());

        return new ProductResource($product);
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product $product): JsonResponse
    {
        $deleted = $this->productService->delete($product);

        return response()->json([
            'message' => $deleted
                ? 'Produk berhasil dihapus.'
                : 'Produk dinonaktifkan karena sudah memiliki transaksi.',
        ]);
    }

    /**
     * Adjust product stock with audit trail.
     */
    public function adjustStock(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|integer',
            'reason' => 'nullable|string|max:255',
        ]);

        $warehouse = Warehouse::findOrFail($request->input('warehouse_id'));

        $result = $this->productService->adjustStock(
            $product,
            $warehouse,
            $request->only(['quantity', 'reason'])
        );

        return response()->json([
            'message' => 'Stok berhasil disesuaikan.',
            ...$result,
        ]);
    }

    /**
     * Get low stock products.
     */
    public function lowStock(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->with('category')
            ->lowStock()
            ->active()
            ->orderBy('current_stock')
            ->paginate($request->input('per_page', 25));

        return ProductResource::collection($products);
    }

    /**
     * Get product price list.
     */
    public function priceList(Request $request): JsonResponse
    {
        $query = Product::query()
            ->select(['id', 'sku', 'name', 'unit', 'purchase_price', 'selling_price', 'tax_rate', 'is_taxable'])
            ->active();

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        $products = $query->orderBy('name')->get();

        return response()->json([
            'data' => $products->map(fn ($p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'unit' => $p->unit,
                'purchase_price' => $p->purchase_price,
                'selling_price' => $p->selling_price,
                'selling_price_with_tax' => $p->selling_price_with_tax,
                'tax_rate' => $p->tax_rate,
                'is_taxable' => $p->is_taxable,
            ]),
        ]);
    }

    /**
     * Lookup product by SKU or barcode.
     */
    public function lookup(Request $request): JsonResponse
    {
        $code = $request->input('code');

        if (! $code) {
            return response()->json([
                'message' => 'Kode produk wajib diisi.',
            ], 422);
        }

        $product = Product::query()
            ->where('sku', $code)
            ->orWhere('barcode', $code)
            ->first();

        if (! $product) {
            return response()->json([
                'message' => 'Produk tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'data' => new ProductResource($product->load('category')),
        ]);
    }

    /**
     * Duplicate a product.
     */
    public function duplicate(Product $product): JsonResponse
    {
        $newProduct = $this->productService->duplicate($product);

        return (new ProductResource($newProduct))
            ->response()
            ->setStatusCode(201);
    }
}
