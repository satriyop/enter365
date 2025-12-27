<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Accounting\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()->with('category');

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by sellable
        if ($request->has('is_sellable')) {
            $query->where('is_sellable', $request->boolean('is_sellable'));
        }

        // Filter by purchasable
        if ($request->has('is_purchasable')) {
            $query->where('is_purchasable', $request->boolean('is_purchasable'));
        }

        // Filter by inventory tracking
        if ($request->has('track_inventory')) {
            $query->where('track_inventory', $request->boolean('track_inventory'));
        }

        // Filter low stock
        if ($request->boolean('low_stock')) {
            $query->lowStock();
        }

        // Search
        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(barcode) LIKE ?', ["%{$search}%"]);
            });
        }

        $products = $query->orderBy('name')
            ->paginate($request->input('per_page', 25));

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['sku'])) {
            $prefix = $data['type'] === Product::TYPE_SERVICE ? 'SVC' : 'PRD';
            $data['sku'] = Product::generateSku($prefix);
        }

        // Set defaults
        $data['is_active'] = $data['is_active'] ?? true;
        $data['is_sellable'] = $data['is_sellable'] ?? true;
        $data['is_purchasable'] = $data['is_purchasable'] ?? true;
        $data['is_taxable'] = $data['is_taxable'] ?? true;
        $data['tax_rate'] = $data['tax_rate'] ?? 11.00;
        $data['track_inventory'] = $data['track_inventory'] ?? false;
        $data['min_stock'] = $data['min_stock'] ?? 0;
        $data['current_stock'] = $data['current_stock'] ?? 0;

        // Set defaults for services
        if ($data['type'] === Product::TYPE_SERVICE) {
            $data['track_inventory'] = false;
            $data['min_stock'] = 0;
            $data['current_stock'] = 0;
        }

        $product = Product::create($data);

        return (new ProductResource($product->load('category')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource(
            $product->load([
                'category',
                'inventoryAccount',
                'cogsAccount',
                'salesAccount',
                'purchaseAccount',
            ])
        );
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $data = $request->validated();

        // Prevent changing type if product has transactions
        if (isset($data['type']) && $data['type'] !== $product->type) {
            $hasTransactions = $product->invoiceItems()->exists() || $product->billItems()->exists();
            if ($hasTransactions) {
                abort(422, 'Tidak bisa mengubah tipe produk yang sudah memiliki transaksi.');
            }
        }

        $product->update($data);

        return new ProductResource($product->fresh('category'));
    }

    public function destroy(Product $product): JsonResponse
    {
        // Check for transactions
        $hasTransactions = $product->invoiceItems()->exists() || $product->billItems()->exists();

        if ($hasTransactions) {
            // Soft delete by deactivating
            $product->update(['is_active' => false]);

            return response()->json([
                'message' => 'Produk dinonaktifkan karena sudah memiliki transaksi.',
            ]);
        }

        $product->forceDelete();

        return response()->json([
            'message' => 'Produk berhasil dihapus.',
        ]);
    }

    /**
     * Adjust product stock.
     */
    public function adjustStock(Request $request, Product $product): JsonResponse
    {
        if (! $product->track_inventory) {
            return response()->json([
                'message' => 'Produk ini tidak melacak inventori.',
            ], 422);
        }

        $request->validate([
            'quantity' => 'required|integer',
            'reason' => 'nullable|string|max:255',
        ]);

        $adjustment = $request->input('quantity');
        $newStock = $product->current_stock + $adjustment;

        if ($newStock < 0) {
            return response()->json([
                'message' => 'Stok tidak bisa negatif.',
            ], 422);
        }

        $product->update(['current_stock' => $newStock]);

        return response()->json([
            'message' => 'Stok berhasil disesuaikan.',
            'previous_stock' => $product->current_stock - $adjustment,
            'adjustment' => $adjustment,
            'current_stock' => $newStock,
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
        $newProduct = $product->replicate();
        $newProduct->sku = Product::generateSku(
            $product->type === Product::TYPE_SERVICE ? 'SVC' : 'PRD'
        );
        $newProduct->name = $product->name.' (Copy)';
        $newProduct->barcode = null; // Clear barcode
        $newProduct->current_stock = 0;
        $newProduct->save();

        return (new ProductResource($newProduct->load('category')))
            ->response()
            ->setStatusCode(201);
    }
}
