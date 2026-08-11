<?php

namespace App\Models\ElectricalPanel;

use App\Models\Manufacturing\BomItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomItemPanelMeta extends Model
{
    protected $table = 'electrical_panel_bom_item_meta';

    protected $fillable = [
        'bom_item_id',
        'component_standard_id',
    ];

    /**
     * @return BelongsTo<BomItem, $this>
     */
    public function bomItem(): BelongsTo
    {
        return $this->belongsTo(BomItem::class);
    }

    /**
     * @return BelongsTo<ComponentStandard, $this>
     */
    public function componentStandard(): BelongsTo
    {
        return $this->belongsTo(ComponentStandard::class);
    }

    public static function sync(BomItem $item, ?int $componentStandardId): void
    {
        if ($componentStandardId === null) {
            static::query()->where('bom_item_id', $item->id)->delete();

            return;
        }

        static::query()->updateOrCreate(
            ['bom_item_id' => $item->id],
            ['component_standard_id' => $componentStandardId]
        );
    }
}
