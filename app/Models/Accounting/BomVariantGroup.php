<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BomVariantGroup extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'product_id',
        'name',
        'description',
        'comparison_notes',
        'status',
        'created_by',
    ];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<Bom, $this>
     */
    public function boms(): HasMany
    {
        return $this->hasMany(Bom::class, 'variant_group_id')->orderBy('variant_sort_order');
    }

    /**
     * @return HasMany<Bom, $this>
     */
    public function activeBoms(): HasMany
    {
        return $this->hasMany(Bom::class, 'variant_group_id')
            ->where('status', Bom::STATUS_ACTIVE)
            ->orderBy('variant_sort_order');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the primary (recommended) variant BOM.
     */
    public function primaryBom(): ?Bom
    {
        return $this->boms()->where('is_primary_variant', true)->first();
    }

    /**
     * Generate side-by-side comparison data for all variants.
     *
     * @return array<string, mixed>
     */
    public function getComparisonData(): array
    {
        $boms = $this->boms()
            ->with(['items.product', 'product'])
            ->get();

        if ($boms->isEmpty()) {
            return [
                'product' => $this->product,
                'variants' => [],
                'summary' => [],
            ];
        }

        $variants = $boms->map(function (Bom $bom) {
            return [
                'id' => $bom->id,
                'bom_number' => $bom->bom_number,
                'name' => $bom->name,
                'variant_name' => $bom->variant_name,
                'variant_label' => $bom->variant_label,
                'is_primary' => $bom->is_primary_variant,
                'status' => $bom->status,
                'cost_breakdown' => $bom->getCostBreakdown(),
                'items' => $bom->items->groupBy('type'),
            ];
        });

        return [
            'product' => $this->product,
            'variants' => $variants,
            'summary' => $this->generateComparisonSummary($boms),
        ];
    }

    /**
     * Generate summary statistics for comparison.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Bom>  $boms
     * @return array<string, mixed>
     */
    public function generateComparisonSummary($boms): array
    {
        $costs = $boms->pluck('total_cost');
        $materialCosts = $boms->pluck('total_material_cost');
        $laborCosts = $boms->pluck('total_labor_cost');

        return [
            'total_variants' => $boms->count(),
            'cost_range' => [
                'min' => $costs->min(),
                'max' => $costs->max(),
                'difference' => $costs->max() - $costs->min(),
                'difference_percentage' => $costs->min() > 0
                    ? round((($costs->max() - $costs->min()) / $costs->min()) * 100, 2)
                    : 0,
            ],
            'material_cost_range' => [
                'min' => $materialCosts->min(),
                'max' => $materialCosts->max(),
            ],
            'labor_cost_range' => [
                'min' => $laborCosts->min(),
                'max' => $laborCosts->max(),
            ],
            'cheapest_variant' => $boms->sortBy('total_cost')->first()?->variant_name,
            'most_expensive_variant' => $boms->sortByDesc('total_cost')->first()?->variant_name,
        ];
    }

    /**
     * Compare specific cost categories across variants.
     *
     * @return array<string, array<string, int>>
     */
    public function compareCostCategories(): array
    {
        $boms = $this->boms()->get();

        return [
            'material' => $boms->pluck('total_material_cost', 'variant_name')->toArray(),
            'labor' => $boms->pluck('total_labor_cost', 'variant_name')->toArray(),
            'overhead' => $boms->pluck('total_overhead_cost', 'variant_name')->toArray(),
            'total' => $boms->pluck('total_cost', 'variant_name')->toArray(),
            'unit' => $boms->pluck('unit_cost', 'variant_name')->toArray(),
        ];
    }
}
