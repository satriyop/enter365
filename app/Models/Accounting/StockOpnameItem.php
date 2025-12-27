<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_opname_id',
        'product_id',
        'system_quantity',
        'system_cost',
        'system_value',
        'counted_quantity',
        'variance_quantity',
        'variance_value',
        'notes',
        'counted_at',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'integer',
            'system_cost' => 'integer',
            'system_value' => 'integer',
            'counted_quantity' => 'integer',
            'variance_quantity' => 'integer',
            'variance_value' => 'integer',
            'counted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StockOpname, $this>
     */
    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Check if item has been counted.
     */
    public function isCounted(): bool
    {
        return $this->counted_quantity !== null;
    }

    /**
     * Check if item has variance.
     */
    public function hasVariance(): bool
    {
        return $this->variance_quantity !== 0;
    }

    /**
     * Check if variance is positive (more stock than expected).
     */
    public function hasPositiveVariance(): bool
    {
        return $this->variance_quantity > 0;
    }

    /**
     * Check if variance is negative (less stock than expected).
     */
    public function hasNegativeVariance(): bool
    {
        return $this->variance_quantity < 0;
    }

    /**
     * Record count and calculate variance.
     */
    public function recordCount(int $countedQuantity, ?string $notes = null): void
    {
        $this->counted_quantity = $countedQuantity;
        $this->variance_quantity = $countedQuantity - $this->system_quantity;
        $this->variance_value = $this->variance_quantity * $this->system_cost;
        $this->notes = $notes;
        $this->counted_at = now();
        $this->save();

        // Update parent totals
        $this->stockOpname->updateTotals();
    }

    /**
     * Capture system quantities from ProductStock.
     */
    public function captureSystemQuantities(ProductStock $stock): void
    {
        $this->system_quantity = $stock->quantity;
        $this->system_cost = $stock->average_cost;
        $this->system_value = $stock->quantity * $stock->average_cost;
        $this->save();
    }

    /**
     * Get variance percentage.
     */
    public function getVariancePercentage(): float
    {
        if ($this->system_quantity === 0) {
            return $this->counted_quantity > 0 ? 100 : 0;
        }

        return round(($this->variance_quantity / $this->system_quantity) * 100, 2);
    }
}
