<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_PRODUCT = 'product';

    public const TYPE_SERVICE = 'service';

    // Procurement types for MRP
    public const PROCUREMENT_BUY = 'buy';

    public const PROCUREMENT_MAKE = 'make';

    public const PROCUREMENT_SUBCONTRACT = 'subcontract';

    // ABC Classification
    public const ABC_CLASS_A = 'A';

    public const ABC_CLASS_B = 'B';

    public const ABC_CLASS_C = 'C';

    protected $fillable = [
        'sku',
        'name',
        'description',
        'type',
        'category_id',
        'unit',
        'purchase_price',
        'selling_price',
        'tax_rate',
        'is_taxable',
        'track_inventory',
        'min_stock',
        'current_stock',
        'inventory_account_id',
        'cogs_account_id',
        'sales_account_id',
        'purchase_account_id',
        'is_active',
        'is_purchasable',
        'is_sellable',
        'barcode',
        'brand',
        'custom_fields',
        // MRP fields
        'reorder_point',
        'safety_stock',
        'lead_time_days',
        'min_order_qty',
        'order_multiple',
        'max_stock',
        'default_supplier_id',
        'abc_class',
        'procurement_type',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'integer',
            'selling_price' => 'integer',
            'tax_rate' => 'decimal:2',
            'is_taxable' => 'boolean',
            'track_inventory' => 'boolean',
            'min_stock' => 'integer',
            'current_stock' => 'integer',
            'is_active' => 'boolean',
            'is_purchasable' => 'boolean',
            'is_sellable' => 'boolean',
            'custom_fields' => 'array',
            // MRP fields
            'reorder_point' => 'integer',
            'safety_stock' => 'integer',
            'lead_time_days' => 'integer',
            'min_order_qty' => 'decimal:4',
            'order_multiple' => 'decimal:4',
            'max_stock' => 'integer',
        ];
    }

    /**
     * Get the category.
     *
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * Get the inventory account.
     *
     * @return BelongsTo<Account, $this>
     */
    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'inventory_account_id');
    }

    /**
     * Get the COGS account.
     *
     * @return BelongsTo<Account, $this>
     */
    public function cogsAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cogs_account_id');
    }

    /**
     * Get the sales account.
     *
     * @return BelongsTo<Account, $this>
     */
    public function salesAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'sales_account_id');
    }

    /**
     * Get the purchase account.
     *
     * @return BelongsTo<Account, $this>
     */
    public function purchaseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'purchase_account_id');
    }

    /**
     * Get the invoice items for this product.
     *
     * @return HasMany<InvoiceItem, $this>
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Get the bill items for this product.
     *
     * @return HasMany<BillItem, $this>
     */
    public function billItems(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    /**
     * Get stock records per warehouse.
     *
     * @return HasMany<ProductStock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    /**
     * Get inventory movements.
     *
     * @return HasMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Get default supplier.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function defaultSupplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'default_supplier_id');
    }

    /**
     * Get BOMs where this product is the output.
     *
     * @return HasMany<Bom, $this>
     */
    public function boms(): HasMany
    {
        return $this->hasMany(Bom::class);
    }

    /**
     * Get BOM items where this product is a component.
     *
     * @return HasMany<BomItem, $this>
     */
    public function bomItems(): HasMany
    {
        return $this->hasMany(BomItem::class);
    }

    /**
     * Get component brand mappings for this product.
     *
     * @return HasMany<ComponentBrandMapping, $this>
     */
    public function componentBrandMappings(): HasMany
    {
        return $this->hasMany(ComponentBrandMapping::class);
    }

    /**
     * Get work orders for this product.
     *
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Get purchase order items for this product.
     *
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Get stock in a specific warehouse.
     */
    public function getStockInWarehouse(Warehouse $warehouse): int
    {
        return $this->stocks()
            ->where('warehouse_id', $warehouse->id)
            ->value('quantity') ?? 0;
    }

    /**
     * Get total stock across all warehouses.
     */
    public function getTotalStockAttribute(): int
    {
        return $this->stocks()->sum('quantity');
    }

    /**
     * Get total stock value across all warehouses.
     */
    public function getTotalStockValueAttribute(): int
    {
        return $this->stocks()->sum('total_value');
    }

    /**
     * Sync current_stock from product_stocks table.
     */
    public function syncCurrentStock(): void
    {
        $this->update(['current_stock' => $this->total_stock]);
    }

    /**
     * Check if this is a physical product.
     */
    public function isProduct(): bool
    {
        return $this->type === self::TYPE_PRODUCT;
    }

    /**
     * Check if this is a service.
     */
    public function isService(): bool
    {
        return $this->type === self::TYPE_SERVICE;
    }

    /**
     * Check if stock is low.
     */
    public function isLowStock(): bool
    {
        if (! $this->track_inventory) {
            return false;
        }

        return $this->current_stock <= $this->min_stock;
    }

    /**
     * Check if product is out of stock.
     */
    public function isOutOfStock(): bool
    {
        if (! $this->track_inventory) {
            return false;
        }

        return $this->current_stock <= 0;
    }

    /**
     * Calculate selling price with tax.
     */
    public function getSellingPriceWithTaxAttribute(): int
    {
        if (! $this->is_taxable) {
            return $this->selling_price;
        }

        return (int) round($this->selling_price * (1 + $this->tax_rate / 100));
    }

    /**
     * Calculate tax amount for selling price.
     */
    public function getSellingTaxAmountAttribute(): int
    {
        if (! $this->is_taxable) {
            return 0;
        }

        return (int) round($this->selling_price * $this->tax_rate / 100);
    }

    /**
     * Calculate profit margin percentage.
     */
    public function getProfitMarginAttribute(): float
    {
        if ($this->selling_price <= 0) {
            return 0;
        }

        $profit = $this->selling_price - $this->purchase_price;

        return round(($profit / $this->selling_price) * 100, 2);
    }

    /**
     * Calculate markup percentage.
     */
    public function getMarkupAttribute(): float
    {
        if ($this->purchase_price <= 0) {
            return 0;
        }

        $profit = $this->selling_price - $this->purchase_price;

        return round(($profit / $this->purchase_price) * 100, 2);
    }

    /**
     * Generate the next SKU.
     */
    public static function generateSku(?string $prefix = null): string
    {
        $prefix = $prefix ?? 'PRD';

        $last = static::where('sku', 'like', $prefix.'-%')
            ->orderByDesc('sku')
            ->first();

        if ($last) {
            $lastNum = (int) substr($last->sku, strlen($prefix) + 1);

            return $prefix.'-'.str_pad($lastNum + 1, 5, '0', STR_PAD_LEFT);
        }

        return $prefix.'-00001';
    }

    /**
     * Scope for active products.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for sellable products.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    public function scopeSellable($query)
    {
        return $query->where('is_sellable', true)->where('is_active', true);
    }

    /**
     * Scope for purchasable products.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    public function scopePurchasable($query)
    {
        return $query->where('is_purchasable', true)->where('is_active', true);
    }

    /**
     * Scope for products (not services).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    public function scopeProducts($query)
    {
        return $query->where('type', self::TYPE_PRODUCT);
    }

    /**
     * Scope for services.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    public function scopeServices($query)
    {
        return $query->where('type', self::TYPE_SERVICE);
    }

    /**
     * Scope for low stock products.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    public function scopeLowStock($query)
    {
        return $query->where('track_inventory', true)
            ->whereColumn('current_stock', '<=', 'min_stock');
    }

    /**
     * Scope for products with inventory tracking.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    public function scopeWithInventory($query)
    {
        return $query->where('track_inventory', true);
    }

    /**
     * Scope for buy items.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    public function scopeBuyItems($query)
    {
        return $query->where('procurement_type', self::PROCUREMENT_BUY);
    }

    /**
     * Scope for make items.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    public function scopeMakeItems($query)
    {
        return $query->where('procurement_type', self::PROCUREMENT_MAKE);
    }

    /**
     * Scope for subcontract items.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    public function scopeSubcontractItems($query)
    {
        return $query->where('procurement_type', self::PROCUREMENT_SUBCONTRACT);
    }

    /**
     * Scope for products at or below reorder point.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    public function scopeNeedsReorder($query)
    {
        return $query->where('track_inventory', true)
            ->where('reorder_point', '>', 0)
            ->whereColumn('current_stock', '<=', 'reorder_point');
    }

    /**
     * Check if product is a buy item.
     */
    public function isBuyItem(): bool
    {
        return $this->procurement_type === self::PROCUREMENT_BUY;
    }

    /**
     * Check if product is a make item.
     */
    public function isMakeItem(): bool
    {
        return $this->procurement_type === self::PROCUREMENT_MAKE;
    }

    /**
     * Check if product is a subcontract item.
     */
    public function isSubcontractItem(): bool
    {
        return $this->procurement_type === self::PROCUREMENT_SUBCONTRACT;
    }

    /**
     * Check if stock is at or below reorder point.
     */
    public function needsReorder(): bool
    {
        if (! $this->track_inventory || $this->reorder_point <= 0) {
            return false;
        }

        return $this->current_stock <= $this->reorder_point;
    }

    /**
     * Get available stock (on hand - reserved).
     */
    public function getAvailableStock(?Warehouse $warehouse = null): int
    {
        $query = $this->stocks();

        if ($warehouse) {
            $query->where('warehouse_id', $warehouse->id);
        }

        $onHand = (int) $query->sum('quantity');
        $reserved = (int) $query->sum('reserved_quantity');

        return max(0, $onHand - $reserved);
    }

    /**
     * Get pending supply from open POs.
     */
    public function getPendingSupply(): float
    {
        return (float) $this->purchaseOrderItems()
            ->whereHas('purchaseOrder', function ($q) {
                $q->whereIn('status', [
                    PurchaseOrder::STATUS_APPROVED,
                    PurchaseOrder::STATUS_PARTIAL,
                ]);
            })
            ->selectRaw('SUM(quantity - quantity_received) as pending')
            ->value('pending') ?? 0;
    }

    /**
     * Get active BOM for manufacturing.
     */
    public function getActiveBom(): ?Bom
    {
        return $this->boms()
            ->where('status', Bom::STATUS_ACTIVE)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Round quantity to order multiple.
     */
    public function roundToOrderMultiple(float $quantity): float
    {
        $multiple = (float) ($this->order_multiple ?? 1);
        if ($multiple <= 0) {
            $multiple = 1;
        }

        return ceil($quantity / $multiple) * $multiple;
    }

    /**
     * Ensure minimum order quantity.
     */
    public function ensureMinOrderQty(float $quantity): float
    {
        $moq = (float) ($this->min_order_qty ?? 1);

        return max($quantity, $moq);
    }

    /**
     * Get procurement types.
     *
     * @return array<string, string>
     */
    public static function getProcurementTypes(): array
    {
        return [
            self::PROCUREMENT_BUY => 'Beli',
            self::PROCUREMENT_MAKE => 'Produksi',
            self::PROCUREMENT_SUBCONTRACT => 'Subkontrak',
        ];
    }

    /**
     * Get ABC classes.
     *
     * @return array<string, string>
     */
    public static function getAbcClasses(): array
    {
        return [
            self::ABC_CLASS_A => 'A - Prioritas Tinggi',
            self::ABC_CLASS_B => 'B - Prioritas Sedang',
            self::ABC_CLASS_C => 'C - Prioritas Rendah',
        ];
    }
}
