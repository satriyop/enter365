<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Inventory\ProductServiceInterface;
use App\Filters\ProductFilter;
use App\Http\Requests\Api\V1\ProductPriceForVendorRequest;
use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\ProductForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(
        private ProductServiceInterface $productService,
        private ProductForecastService $forecastService
    ) {}

    /**
     * Display a listing of products.
     */
    public function index(ProductFilter $filter): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::query()
            ->with(['category', 'salesTaxes', 'purchaseTaxes', 'vendorPricelists.contact'])
            ->filter($filter)
            ->paginate($filter->getRequest()->input('per_page', 25));

        $this->forecastService->applyTo($products->getCollection());

        return ProductResource::collection($products);
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

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
        $this->authorize('view', $product);

        $filter->apply($product->newQuery());

        $product->loadMissing([
            'category',
            'salesTaxes',
            'purchaseTaxes',
            'vendorPricelists.contact',
            'inventoryAccount',
            'cogsAccount',
            'salesAccount',
            'purchaseAccount',
        ]);

        $this->forecastService->applyTo([$product]);

        return new ProductResource($product);
    }

    /**
     * Update the specified product.
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $this->authorize('update', $product);

        $product = $this->productService->update($product, $request->validated());

        return new ProductResource($product);
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $deleted = $this->productService->delete($product);

        return $this->success(
            message: $deleted
                ? 'Produk berhasil dihapus.'
                : 'Produk dinonaktifkan karena sudah memiliki transaksi.'
        );
    }

    /**
     * Adjust product stock with audit trail.
     *
     * @response array{message: string, current_stock: float, movement: array<mixed>}
     */
    public function adjustStock(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

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

        return $this->success($result, 'Stok berhasil disesuaikan.');
    }

    /**
     * Get low stock products.
     */
    public function lowStock(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

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
     *
     * @response array{data: array<array{id: int, sku: string, name: string, unit: string, purchase_price: int, selling_price: int, selling_price_with_tax: int, tax_rate: float, is_taxable: bool}>}
     */
    public function priceList(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

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

        return $this->success($products->map(fn ($p) => [
            'id' => $p->id,
            'sku' => $p->sku,
            'name' => $p->name,
            'unit' => $p->unit,
            'purchase_price' => $p->purchase_price,
            'selling_price' => $p->selling_price,
            'selling_price_with_tax' => $p->selling_price_with_tax,
            'tax_rate' => $p->tax_rate,
            'is_taxable' => $p->is_taxable,
        ]));
    }

    /**
     * Lookup product by SKU or barcode.
     *
     * @response array{data: ProductResource}
     */
    public function lookup(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $code = $request->input('code');

        if (! $code) {
            return $this->error('Kode produk wajib diisi.', 422);
        }

        $product = Product::query()
            ->where('sku', $code)
            ->orWhere('barcode', $code)
            ->first();

        if (! $product) {
            return $this->notFound('Produk');
        }

        return $this->success(new ProductResource($product->load('category')));
    }

    /**
     * Unit price for a vendor and quantity (pricelist, else purchase price).
     *
     * @queryParam contact_id int Vendor contact id. Example: 7
     * @queryParam quantity number Line quantity. Example: 10
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Operasi berhasil.",
     *   "data": {"price": 75000, "source": "pricelist"}
     * }
     */
    public function priceForVendor(ProductPriceForVendorRequest $request, Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $contactId = $request->filled('contact_id') ? (int) $request->integer('contact_id') : null;
        $quantity = $request->filled('quantity') ? (float) $request->input('quantity') : 1.0;

        $product->loadMissing('vendorPricelists');

        return $this->success([
            'price' => $product->priceForVendor($contactId, $quantity),
            'source' => $product->vendorPriceSource($contactId, $quantity),
        ]);
    }

    /**
     * Duplicate a product.
     */
    public function duplicate(Product $product): JsonResponse
    {
        $this->authorize('create', Product::class);

        $newProduct = $this->productService->duplicate($product);

        return (new ProductResource($newProduct))
            ->response()
            ->setStatusCode(201);
    }
}
