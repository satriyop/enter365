<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MrpDemand extends Model
{
    use HasFactory;

    protected $fillable = [
        'mrp_run_id',
        'product_id',
        'demand_source_type',
        'demand_source_id',
        'demand_source_number',
        'required_date',
        'week_bucket',
        'quantity_required',
        'quantity_on_hand',
        'quantity_on_order',
        'quantity_reserved',
        'quantity_available',
        'quantity_short',
        'warehouse_id',
        'bom_level',
    ];

    protected function casts(): array
    {
        return [
            'required_date' => 'date',
            'quantity_required' => 'decimal:4',
            'quantity_on_hand' => 'decimal:4',
            'quantity_on_order' => 'decimal:4',
            'quantity_reserved' => 'decimal:4',
            'quantity_available' => 'decimal:4',
            'quantity_short' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<MrpRun, $this>
     */
    public function mrpRun(): BelongsTo
    {
        return $this->belongsTo(MrpRun::class);
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
     * Polymorphic relation to demand source (WorkOrder, Project).
     *
     * @return MorphTo<Model, $this>
     */
    public function demandSource(): MorphTo
    {
        return $this->morphTo('demand_source');
    }

    /**
     * Check if demand has shortage.
     */
    public function hasShortage(): bool
    {
        return (float) $this->quantity_short > 0;
    }

    /**
     * Check if this is an exploded demand (from BOM).
     */
    public function isExploded(): bool
    {
        return $this->bom_level > 0;
    }

    /**
     * Check if this is a direct demand.
     */
    public function isDirectDemand(): bool
    {
        return $this->bom_level === 0;
    }

    /**
     * Calculate available quantity.
     */
    public function calculateAvailableQuantity(): float
    {
        $available = (float) $this->quantity_on_hand
            + (float) $this->quantity_on_order
            - (float) $this->quantity_reserved;

        return max(0, $available);
    }

    /**
     * Calculate shortage quantity.
     */
    public function calculateShortage(): float
    {
        $shortage = (float) $this->quantity_required - $this->calculateAvailableQuantity();

        return max(0, $shortage);
    }

    /**
     * Recalculate quantities.
     */
    public function recalculate(): void
    {
        $this->quantity_available = $this->calculateAvailableQuantity();
        $this->quantity_short = $this->calculateShortage();
    }

    /**
     * Get week bucket from required date.
     */
    public static function calculateWeekBucket(\DateTimeInterface $date): int
    {
        return (int) $date->format('W');
    }

    /**
     * Get display name for demand source type.
     */
    public function getDemandSourceTypeName(): string
    {
        return match ($this->demand_source_type) {
            WorkOrder::class => 'Work Order',
            Project::class => 'Project',
            default => class_basename($this->demand_source_type),
        };
    }

    /**
     * Scope for demands with shortage.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<MrpDemand>  $query
     * @return \Illuminate\Database\Eloquent\Builder<MrpDemand>
     */
    public function scopeWithShortage($query)
    {
        return $query->where('quantity_short', '>', 0);
    }

    /**
     * Scope for direct demands (not exploded from BOM).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<MrpDemand>  $query
     * @return \Illuminate\Database\Eloquent\Builder<MrpDemand>
     */
    public function scopeDirectDemands($query)
    {
        return $query->where('bom_level', 0);
    }

    /**
     * Scope for exploded demands (from BOM).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<MrpDemand>  $query
     * @return \Illuminate\Database\Eloquent\Builder<MrpDemand>
     */
    public function scopeExplodedDemands($query)
    {
        return $query->where('bom_level', '>', 0);
    }

    /**
     * Scope by week bucket.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<MrpDemand>  $query
     * @return \Illuminate\Database\Eloquent\Builder<MrpDemand>
     */
    public function scopeForWeek($query, int $week)
    {
        return $query->where('week_bucket', $week);
    }
}
