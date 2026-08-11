<?php

namespace App\Models\ElectricalPanel;

use App\Models\Manufacturing\BomTemplateItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomTemplateItemPanelMeta extends Model
{
    protected $table = 'electrical_panel_bom_template_item_meta';

    protected $fillable = [
        'bom_template_item_id',
        'component_standard_id',
    ];

    /**
     * @return BelongsTo<BomTemplateItem, $this>
     */
    public function templateItem(): BelongsTo
    {
        return $this->belongsTo(BomTemplateItem::class, 'bom_template_item_id');
    }

    /**
     * @return BelongsTo<ComponentStandard, $this>
     */
    public function componentStandard(): BelongsTo
    {
        return $this->belongsTo(ComponentStandard::class);
    }

    public static function sync(BomTemplateItem $item, ?int $componentStandardId): void
    {
        if ($componentStandardId === null) {
            static::query()->where('bom_template_item_id', $item->id)->delete();

            return;
        }

        static::query()->updateOrCreate(
            ['bom_template_item_id' => $item->id],
            ['component_standard_id' => $componentStandardId]
        );
    }
}
