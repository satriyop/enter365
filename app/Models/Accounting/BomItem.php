<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomItem extends Model
{
    use HasFactory;

    public const TYPE_MATERIAL = 'material';

    public const TYPE_LABOR = 'labor';

    public const TYPE_OVERHEAD = 'overhead';

    protected $fillable = [
        'bom_id',
        'type',
        'product_id',
        'description',
        'quantity',
        'unit',
        'unit_cost',
        'total_cost',
        'waste_percentage',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'integer',
            'total_cost' => 'integer',
            'waste_percentage' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Bom, $this>
     */
    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate total cost including waste.
     */
    public function calculateTotalCost(): void
    {
        $baseQuantity = (float) $this->quantity;
        $wasteMultiplier = 1 + ((float) $this->waste_percentage / 100);
        $effectiveQuantity = $baseQuantity * $wasteMultiplier;

        $this->total_cost = (int) round($effectiveQuantity * $this->unit_cost);
    }

    /**
     * Get effective quantity including waste.
     */
    public function getEffectiveQuantity(): float
    {
        $wasteMultiplier = 1 + ((float) $this->waste_percentage / 100);

        return (float) $this->quantity * $wasteMultiplier;
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
