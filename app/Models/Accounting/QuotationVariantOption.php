<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationVariantOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'bom_id',
        'display_name',
        'tagline',
        'is_recommended',
        'selling_price',
        'features',
        'specifications',
        'warranty_terms',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_recommended' => 'boolean',
            'selling_price' => 'integer',
            'features' => 'array',
            'specifications' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<Bom, $this>
     */
    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    /**
     * Get cost breakdown from the linked BOM.
     *
     * @return array{material: int, labor: int, overhead: int, total: int, unit_cost: int}
     */
    public function getCostBreakdown(): array
    {
        return $this->bom->getCostBreakdown();
    }

    /**
     * Calculate profit margin based on selling price vs BOM cost.
     */
    public function getProfitMargin(): float
    {
        $bomCost = $this->bom->total_cost;
        if ($bomCost <= 0) {
            return 0;
        }

        return round((($this->selling_price - $bomCost) / $bomCost) * 100, 2);
    }

    /**
     * Calculate profit amount.
     */
    public function getProfitAmount(): int
    {
        return $this->selling_price - $this->bom->total_cost;
    }

    /**
     * Get formatted features for display.
     *
     * @return array<int, string>
     */
    public function getFormattedFeatures(): array
    {
        return $this->features ?? [];
    }

    /**
     * Get the variant name from the linked BOM.
     */
    public function getVariantName(): ?string
    {
        return $this->bom->variant_name;
    }
}
