<?php

namespace App\Models\ElectricalPanel;

use App\Models\Manufacturing\Bom;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomPanelMeta extends Model
{
    protected $table = 'electrical_panel_bom_meta';

    protected $fillable = [
        'bom_id',
        'spec_rule_set_id',
    ];

    /**
     * @return BelongsTo<Bom, $this>
     */
    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    /**
     * @return BelongsTo<SpecValidationRuleSet, $this>
     */
    public function specRuleSet(): BelongsTo
    {
        return $this->belongsTo(SpecValidationRuleSet::class, 'spec_rule_set_id');
    }

    public static function sync(Bom $bom, ?int $specRuleSetId): void
    {
        if ($specRuleSetId === null) {
            static::query()->where('bom_id', $bom->id)->delete();

            return;
        }

        static::query()->updateOrCreate(
            ['bom_id' => $bom->id],
            ['spec_rule_set_id' => $specRuleSetId]
        );
    }
}
