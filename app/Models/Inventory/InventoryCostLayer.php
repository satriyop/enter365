<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks individual cost layers for FIFO inventory valuation.
 *
 * Each layer represents a batch of stock received at a specific cost.
 * When stock is issued, oldest layers are consumed first (FIFO).
 *
 * @property int $id
 * @property int $product_id
 * @property int $warehouse_id
 * @property int $quantity
 * @property int $original_quantity
 * @property int $unit_cost
 * @property \Carbon\Carbon $received_date
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class InventoryCostLayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'original_quantity',
        'unit_cost',
        'received_date',
        'reference_type',
        'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'original_quantity' => 'integer',
            'unit_cost' => 'integer',
            'received_date' => 'date',
        ];
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
     * Check if this layer has remaining quantity.
     */
    public function hasStock(): bool
    {
        return $this->quantity > 0;
    }

    /**
     * Consume quantity from this layer. Returns the quantity actually consumed.
     */
    public function consume(int $quantity): int
    {
        $consumed = min($quantity, $this->quantity);
        $this->quantity -= $consumed;
        $this->save();

        return $consumed;
    }
}
