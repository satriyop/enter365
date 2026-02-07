<?php

declare(strict_types=1);

namespace App\Models\Purchasing;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $landed_cost_id
 * @property int $goods_receipt_note_item_id
 * @property int $product_id
 * @property int $allocated_amount
 * @property int $previous_unit_cost
 * @property int $new_unit_cost
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class LandedCostAllocation extends Model
{
    protected $fillable = [
        'landed_cost_id',
        'goods_receipt_note_item_id',
        'product_id',
        'allocated_amount',
        'previous_unit_cost',
        'new_unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'integer',
            'previous_unit_cost' => 'integer',
            'new_unit_cost' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<LandedCost, $this>
     */
    public function landedCost(): BelongsTo
    {
        return $this->belongsTo(LandedCost::class);
    }

    /**
     * @return BelongsTo<GoodsReceiptNoteItem, $this>
     */
    public function goodsReceiptNoteItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNoteItem::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
