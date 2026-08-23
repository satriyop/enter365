<?php

declare(strict_types=1);

namespace App\Models\Pos;

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSaleItem extends Model
{
    /** @use HasFactory<\Database\Factories\Pos\PosSaleItemFactory> */
    use HasFactory;

    protected $fillable = [
        'pos_sale_id',
        'product_id',
        'quantity',
        'unit_price_inclusive',
        'payable_amount',
        'dpp_amount',
        'ppn_amount',
        'is_taxable',
        'track_inventory',
        'inventory_movement_id',
        'cogs_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_inclusive' => 'integer',
            'payable_amount' => 'integer',
            'dpp_amount' => 'integer',
            'ppn_amount' => 'integer',
            'is_taxable' => 'boolean',
            'track_inventory' => 'boolean',
            'cogs_amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PosSale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<InventoryMovement, $this>
     */
    public function inventoryMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class);
    }
}
