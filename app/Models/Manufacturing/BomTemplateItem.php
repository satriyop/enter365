<?php

namespace App\Models\Manufacturing;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomTemplateItem extends Model
{
    use HasFactory;

    public const TYPE_MATERIAL = 'material';

    public const TYPE_LABOR = 'labor';

    public const TYPE_OVERHEAD = 'overhead';

    protected $fillable = [
        'template_id',
        'type',
        // Extension column owned by electrical_panel add-on (nullable FK).
        'component_standard_id',
        'product_id',
        'description',
        'default_quantity',
        'unit',
        'is_required',
        'is_quantity_variable',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'default_quantity' => 'decimal:2',
            'is_required' => 'boolean',
            'is_quantity_variable' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BomTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(BomTemplate::class, 'template_id');
    }

    // componentStandard() relation registered by ElectricalPanelServiceProvider (add-on).

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Whether this line references a component standard (FK only; no add-on type import).
     */
    public function hasComponentStandard(): bool
    {
        return $this->component_standard_id !== null;
    }

    /**
     * Check if this item has a specific product.
     */
    public function hasProduct(): bool
    {
        return $this->product_id !== null;
    }

    /**
     * Get item types list.
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
