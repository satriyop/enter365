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

        $last = static::where('sku', 'like', $prefix . '-%')
            ->orderByDesc('sku')
            ->first();

        if ($last) {
            $lastNum = (int) substr($last->sku, strlen($prefix) + 1);

            return $prefix . '-' . str_pad($lastNum + 1, 5, '0', STR_PAD_LEFT);
        }

        return $prefix . '-00001';
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
}
