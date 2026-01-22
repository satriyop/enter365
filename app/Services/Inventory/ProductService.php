<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Inventory\InventoryServiceInterface;
use App\Contracts\Inventory\ProductServiceInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\ValidationException;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Services\Base\BaseService;

class ProductService extends BaseService implements ProductServiceInterface
{
    public function __construct(
        private InventoryServiceInterface $inventoryService,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a new product with defaults.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product
    {
        return $this->executeInTransaction('create', function () use ($data) {
            // Generate SKU if not provided
            if (empty($data['sku'])) {
                $prefix = ($data['type'] ?? Product::TYPE_PRODUCT) === Product::TYPE_SERVICE ? 'SVC' : 'PRD';
                $data['sku'] = Product::generateSku($prefix);
            }

            // Set defaults
            $data = $this->applyDefaults($data);

            // Override defaults for services
            if ($data['type'] === Product::TYPE_SERVICE) {
                $data['track_inventory'] = false;
                $data['min_stock'] = 0;
                $data['current_stock'] = 0;
            }

            $product = Product::create($data);

            return $product->load('category');
        }, ['type' => $data['type'] ?? Product::TYPE_PRODUCT]);
    }

    /**
     * Update a product with validation.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws DocumentLockedException
     */
    public function update(Product $product, array $data): Product
    {
        // Validate type change
        if (isset($data['type']) && $data['type'] !== $product->type) {
            $this->validateTypeChange($product);
        }

        $product->update($data);

        return $product->fresh('category');
    }

    /**
     * Delete a product (or deactivate if has transactions).
     *
     * @return bool True if deleted, false if deactivated
     */
    public function delete(Product $product): bool
    {
        if ($this->hasTransactions($product)) {
            // Soft delete by deactivating
            $product->update(['is_active' => false]);

            return false; // Indicates deactivation, not deletion
        }

        return $product->forceDelete();
    }

    /**
     * Duplicate a product with new SKU.
     */
    public function duplicate(Product $product): Product
    {
        return $this->executeInTransaction('duplicate', function () use ($product) {
            $newProduct = $product->replicate([
                'sku',
                'barcode',
                'current_stock',
            ]);

            $prefix = $product->type === Product::TYPE_SERVICE ? 'SVC' : 'PRD';
            $newProduct->sku = Product::generateSku($prefix);
            $newProduct->name = $product->name.' (Copy)';
            $newProduct->barcode = null;
            $newProduct->current_stock = 0;
            $newProduct->save();

            return $newProduct->load('category');
        }, ['source_product_id' => $product->id]);
    }

    /**
     * Activate a product.
     */
    public function activate(Product $product): Product
    {
        $product->update(['is_active' => true]);

        return $product->fresh();
    }

    /**
     * Deactivate a product.
     */
    public function deactivate(Product $product): Product
    {
        $product->update(['is_active' => false]);

        return $product->fresh();
    }

    /**
     * Adjust product stock using InventoryService for audit trail.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> Adjustment result
     *
     * @throws ValidationException
     */
    public function adjustStock(Product $product, Warehouse $warehouse, array $data): array
    {
        if (! $product->track_inventory) {
            throw ValidationException::invalid(
                'product_id',
                'Produk ini tidak melacak inventori.',
                ['product_id' => $product->id]
            );
        }

        $adjustment = (int) $data['quantity'];
        $currentStock = $product->current_stock;
        $newStock = $currentStock + $adjustment;

        if ($newStock < 0) {
            throw ValidationException::invalid(
                'quantity',
                'Stok tidak bisa negatif.',
                [
                    'current_stock' => $currentStock,
                    'adjustment' => $adjustment,
                    'would_result_in' => $newStock,
                ]
            );
        }

        // Use InventoryService for proper audit trail
        $movement = $this->inventoryService->adjust(
            $product,
            $warehouse,
            $newStock,
            null, // Keep current unit cost
            $data['reason'] ?? 'Penyesuaian stok manual',
            Product::class,
            $product->id
        );

        return [
            'previous_stock' => $currentStock,
            'adjustment' => $adjustment,
            'current_stock' => $newStock,
            'movement_id' => $movement->id,
            'movement_number' => $movement->movement_number,
        ];
    }

    /**
     * Apply default values to product data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyDefaults(array $data): array
    {
        return array_merge([
            'type' => Product::TYPE_PRODUCT,
            'is_active' => true,
            'is_sellable' => true,
            'is_purchasable' => true,
            'is_taxable' => true,
            'tax_rate' => config('accounting.tax.default_rate', 11.00),
            'track_inventory' => false,
            'min_stock' => 0,
            'current_stock' => 0,
        ], $data);
    }

    /**
     * Validate that product type can be changed.
     *
     * @throws DocumentLockedException
     */
    private function validateTypeChange(Product $product): void
    {
        if ($this->hasTransactions($product)) {
            throw DocumentLockedException::cannotEdit(
                $product,
                'Tidak bisa mengubah tipe produk yang sudah memiliki transaksi.'
            );
        }
    }

    /**
     * Check if product has any transactions.
     */
    private function hasTransactions(Product $product): bool
    {
        return $product->invoiceItems()->exists()
            || $product->billItems()->exists();
    }
}
