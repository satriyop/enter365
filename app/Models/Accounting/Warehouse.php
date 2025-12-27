<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'address',
        'phone',
        'contact_person',
        'is_default',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get product stocks in this warehouse.
     *
     * @return HasMany<ProductStock, $this>
     */
    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    /**
     * Get inventory movements for this warehouse.
     *
     * @return HasMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Get the default warehouse.
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first()
            ?? static::where('is_active', true)->first();
    }

    /**
     * Set this warehouse as default.
     */
    public function setAsDefault(): void
    {
        static::where('is_default', true)->update(['is_default' => false]);
        $this->update(['is_default' => true]);
    }

    /**
     * Get stock for a specific product.
     */
    public function getStockFor(Product $product): int
    {
        return $this->productStocks()
            ->where('product_id', $product->id)
            ->value('quantity') ?? 0;
    }

    /**
     * Generate the next warehouse code.
     */
    public static function generateCode(): string
    {
        $last = static::orderByDesc('code')->first();

        if ($last && preg_match('/WH-(\d+)/', $last->code, $matches)) {
            $nextNum = (int) $matches[1] + 1;

            return 'WH-'.str_pad($nextNum, 3, '0', STR_PAD_LEFT);
        }

        return 'WH-001';
    }

    /**
     * Scope for active warehouses.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Warehouse>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Warehouse>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
