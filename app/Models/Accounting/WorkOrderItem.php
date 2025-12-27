<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrderItem extends Model
{
    use HasFactory;

    public const TYPE_MATERIAL = 'material';

    public const TYPE_LABOR = 'labor';

    public const TYPE_OVERHEAD = 'overhead';

    protected $fillable = [
        'work_order_id',
        'bom_item_id',
        'parent_item_id',
        'type',
        'product_id',
        'description',
        'quantity_required',
        'quantity_reserved',
        'quantity_consumed',
        'quantity_scrapped',
        'unit',
        'unit_cost',
        'actual_unit_cost',
        'total_estimated_cost',
        'total_actual_cost',
        'sort_order',
        'level',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_required' => 'decimal:4',
            'quantity_reserved' => 'decimal:4',
            'quantity_consumed' => 'decimal:4',
            'quantity_scrapped' => 'decimal:4',
            'unit_cost' => 'integer',
            'actual_unit_cost' => 'integer',
            'total_estimated_cost' => 'integer',
            'total_actual_cost' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<BomItem, $this>
     */
    public function bomItem(): BelongsTo
    {
        return $this->belongsTo(BomItem::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_item_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function childItems(): HasMany
    {
        return $this->hasMany(self::class, 'parent_item_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<MaterialConsumption, $this>
     */
    public function consumptions(): HasMany
    {
        return $this->hasMany(MaterialConsumption::class);
    }

    /**
     * Check if this is a material item.
     */
    public function isMaterial(): bool
    {
        return $this->type === self::TYPE_MATERIAL;
    }

    /**
     * Check if this is a labor item.
     */
    public function isLabor(): bool
    {
        return $this->type === self::TYPE_LABOR;
    }

    /**
     * Check if this is an overhead item.
     */
    public function isOverhead(): bool
    {
        return $this->type === self::TYPE_OVERHEAD;
    }

    /**
     * Calculate estimated cost.
     */
    public function calculateEstimatedCost(): void
    {
        $this->total_estimated_cost = (int) round((float) $this->quantity_required * $this->unit_cost);
    }

    /**
     * Calculate actual cost from consumptions.
     */
    public function calculateActualCost(): void
    {
        $totalCost = $this->consumptions()->sum('total_cost');
        $totalQuantity = $this->consumptions()->sum('quantity_consumed');

        $this->total_actual_cost = (int) $totalCost;
        $this->actual_unit_cost = $totalQuantity > 0
            ? (int) round($totalCost / $totalQuantity)
            : 0;
    }

    /**
     * Get remaining quantity to consume.
     */
    public function getRemainingQuantity(): float
    {
        return max(0, (float) $this->quantity_required - (float) $this->quantity_consumed);
    }

    /**
     * Get available types.
     *
     * @return array<string, string>
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_MATERIAL => 'Material',
            self::TYPE_LABOR => 'Tenaga Kerja',
            self::TYPE_OVERHEAD => 'Overhead',
        ];
    }
}
