<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'reserved_quantity',
        'average_cost',
        'total_value',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'average_cost' => 'integer',
            'total_value' => 'integer',
        ];
    }

    /**
     * Get available quantity (total - reserved).
     */
    public function getAvailableQuantity(): int
    {
        return max(0, $this->quantity - ($this->reserved_quantity ?? 0));
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Recalculate total value based on quantity and average cost.
     */
    public function recalculateTotalValue(): void
    {
        $this->total_value = $this->quantity * $this->average_cost;
        $this->save();
    }

    /**
     * Add stock with weighted average cost calculation.
     */
    public function addStock(int $quantity, int $unitCost): void
    {
        if ($quantity <= 0) {
            return;
        }

        $currentValue = $this->quantity * $this->average_cost;
        $addedValue = $quantity * $unitCost;
        $newQuantity = $this->quantity + $quantity;

        if ($newQuantity > 0) {
            $this->average_cost = (int) round(($currentValue + $addedValue) / $newQuantity);
        }

        $this->quantity = $newQuantity;
        $this->total_value = $this->quantity * $this->average_cost;
        $this->save();
    }

    /**
     * Remove stock (average cost remains unchanged).
     */
    public function removeStock(int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $this->quantity = max(0, $this->quantity - $quantity);
        $this->total_value = $this->quantity * $this->average_cost;
        $this->save();
    }

    /**
     * Get or create stock record for product in warehouse.
     */
    public static function getOrCreate(Product $product, Warehouse $warehouse): self
    {
        return static::firstOrCreate(
            [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
            ],
            [
                'quantity' => 0,
                'average_cost' => $product->purchase_price,
                'total_value' => 0,
            ]
        );
    }
}
