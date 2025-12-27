<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bom extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'bom_number',
        'name',
        'description',
        'product_id',
        'output_quantity',
        'output_unit',
        'total_material_cost',
        'total_labor_cost',
        'total_overhead_cost',
        'total_cost',
        'unit_cost',
        'status',
        'version',
        'parent_bom_id',
        'variant_group_id',
        'variant_name',
        'variant_label',
        'is_primary_variant',
        'variant_sort_order',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'output_quantity' => 'decimal:4',
            'total_material_cost' => 'integer',
            'total_labor_cost' => 'integer',
            'total_overhead_cost' => 'integer',
            'total_cost' => 'integer',
            'unit_cost' => 'integer',
            'is_primary_variant' => 'boolean',
            'variant_sort_order' => 'integer',
            'approved_at' => 'datetime',
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
     * @return HasMany<BomItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<BomItem, $this>
     */
    public function materialItems(): HasMany
    {
        return $this->hasMany(BomItem::class)->where('type', BomItem::TYPE_MATERIAL)->orderBy('sort_order');
    }

    /**
     * @return HasMany<BomItem, $this>
     */
    public function laborItems(): HasMany
    {
        return $this->hasMany(BomItem::class)->where('type', BomItem::TYPE_LABOR)->orderBy('sort_order');
    }

    /**
     * @return HasMany<BomItem, $this>
     */
    public function overheadItems(): HasMany
    {
        return $this->hasMany(BomItem::class)->where('type', BomItem::TYPE_OVERHEAD)->orderBy('sort_order');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parentBom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_bom_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function childBoms(): HasMany
    {
        return $this->hasMany(self::class, 'parent_bom_id');
    }

    /**
     * @return BelongsTo<BomVariantGroup, $this>
     */
    public function variantGroup(): BelongsTo
    {
        return $this->belongsTo(BomVariantGroup::class, 'variant_group_id');
    }

    /**
     * Get sibling variants (other BOMs in the same variant group).
     *
     * @return HasMany<self, $this>
     */
    public function siblingVariants(): HasMany
    {
        return $this->hasMany(self::class, 'variant_group_id', 'variant_group_id')
            ->where('id', '!=', $this->id)
            ->orderBy('variant_sort_order');
    }

    /**
     * Check if this BOM is part of a variant comparison group.
     */
    public function hasVariants(): bool
    {
        return $this->variant_group_id !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if the BOM can be edited.
     */
    public function canBeEdited(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if the BOM can be activated.
     */
    public function canBeActivated(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->items()->exists();
    }

    /**
     * Check if the BOM can be deactivated.
     */
    public function canBeDeactivated(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Calculate and update totals from items.
     */
    public function calculateTotals(): void
    {
        $this->total_material_cost = (int) $this->items()
            ->where('type', BomItem::TYPE_MATERIAL)
            ->sum('total_cost');

        $this->total_labor_cost = (int) $this->items()
            ->where('type', BomItem::TYPE_LABOR)
            ->sum('total_cost');

        $this->total_overhead_cost = (int) $this->items()
            ->where('type', BomItem::TYPE_OVERHEAD)
            ->sum('total_cost');

        $this->total_cost = $this->total_material_cost + $this->total_labor_cost + $this->total_overhead_cost;

        $this->unit_cost = $this->output_quantity > 0
            ? (int) round($this->total_cost / (float) $this->output_quantity)
            : 0;
    }

    /**
     * Get cost breakdown.
     *
     * @return array<string, mixed>
     */
    public function getCostBreakdown(): array
    {
        $total = $this->total_cost > 0 ? $this->total_cost : 1;

        return [
            'material' => [
                'amount' => $this->total_material_cost,
                'percentage' => round(($this->total_material_cost / $total) * 100, 2),
            ],
            'labor' => [
                'amount' => $this->total_labor_cost,
                'percentage' => round(($this->total_labor_cost / $total) * 100, 2),
            ],
            'overhead' => [
                'amount' => $this->total_overhead_cost,
                'percentage' => round(($this->total_overhead_cost / $total) * 100, 2),
            ],
            'total' => $this->total_cost,
            'unit_cost' => $this->unit_cost,
        ];
    }

    /**
     * Generate the next BOM number.
     */
    public static function generateBomNumber(): string
    {
        $prefix = 'BOM-'.now()->format('Ym').'-';
        $lastBom = static::query()
            ->where('bom_number', 'like', $prefix.'%')
            ->orderBy('bom_number', 'desc')
            ->first();

        if ($lastBom) {
            $lastNumber = (int) substr($lastBom->bom_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
