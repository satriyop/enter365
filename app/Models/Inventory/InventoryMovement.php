<?php

namespace App\Models\Inventory;

use App\Models\User;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property \Carbon\Carbon $movement_date
 */
class InventoryMovement extends Model
{
    use Filterable, HasFactory;

    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_TRANSFER_IN = 'transfer_in';

    public const TYPE_TRANSFER_OUT = 'transfer_out';

    public const TYPE_PRODUCTION = 'production';

    protected $fillable = [
        'movement_number',
        'product_id',
        'warehouse_id',
        'type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'transfer_warehouse_id',
        'movement_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'unit_cost' => 'integer',
            'total_cost' => 'integer',
            'movement_date' => 'date',
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
     * @return BelongsTo<Warehouse, $this>
     */
    public function transferWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'transfer_warehouse_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo('reference')->withTrashed();
    }

    /**
     * Get type label in Indonesian.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_IN => 'Masuk',
            self::TYPE_OUT => 'Keluar',
            self::TYPE_ADJUSTMENT => 'Penyesuaian',
            self::TYPE_TRANSFER_IN => 'Transfer Masuk',
            self::TYPE_TRANSFER_OUT => 'Transfer Keluar',
            self::TYPE_PRODUCTION => 'Produksi',
            default => $this->type,
        };
    }

    /**
     * Check if this is an incoming movement.
     */
    public function isIncoming(): bool
    {
        return in_array($this->type, [self::TYPE_IN, self::TYPE_TRANSFER_IN, self::TYPE_PRODUCTION]);
    }

    /**
     * Check if this is an outgoing movement.
     */
    public function isOutgoing(): bool
    {
        return in_array($this->type, [self::TYPE_OUT, self::TYPE_TRANSFER_OUT]);
    }

    /**
     * Generate the next movement number.
     *
     * Delegates to DocumentNumbers rather than sorting movement_number as text:
     * "…-9999" ranks above "…-10000" lexicographically, so the old scan handed
     * out a duplicate once a prefix passed 10,000 movements in a day.
     */
    public static function generateMovementNumber(string $type): string
    {
        $prefix = match ($type) {
            self::TYPE_IN => 'IN',
            self::TYPE_OUT => 'OUT',
            self::TYPE_ADJUSTMENT => 'ADJ',
            self::TYPE_TRANSFER_OUT => 'TRF',
            self::TYPE_TRANSFER_IN => 'TRI',
            default => 'MOV',
        };

        return \App\Domain\Shared\DocumentNumbers::generate(
            "{$prefix}-".now()->format('Ymd').'-',
            'inventory_movements',
            'movement_number'
        );
    }

    /**
     * Scope for movements of a specific product.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<InventoryMovement>  $query
     * @return \Illuminate\Database\Eloquent\Builder<InventoryMovement>
     */
    public function scopeForProduct($query, Product $product)
    {
        return $query->where('product_id', $product->id);
    }

    /**
     * Scope for movements in a specific warehouse.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<InventoryMovement>  $query
     * @return \Illuminate\Database\Eloquent\Builder<InventoryMovement>
     */
    public function scopeInWarehouse($query, Warehouse $warehouse)
    {
        return $query->where('warehouse_id', $warehouse->id);
    }

    /**
     * Scope for date range.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<InventoryMovement>  $query
     * @return \Illuminate\Database\Eloquent\Builder<InventoryMovement>
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('movement_date', [$startDate, $endDate]);
    }
}
