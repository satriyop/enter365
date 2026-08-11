<?php

namespace App\Models\ElectricalPanel;

use App\Models\Manufacturing\BomTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomTemplatePanelMeta extends Model
{
    protected $table = 'electrical_panel_bom_template_meta';

    protected $fillable = [
        'bom_template_id',
        'default_rule_set_id',
    ];

    /**
     * @return BelongsTo<BomTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(BomTemplate::class, 'bom_template_id');
    }

    /**
     * @return BelongsTo<SpecValidationRuleSet, $this>
     */
    public function defaultRuleSet(): BelongsTo
    {
        return $this->belongsTo(SpecValidationRuleSet::class, 'default_rule_set_id');
    }

    public static function sync(BomTemplate $template, ?int $defaultRuleSetId): void
    {
        if ($defaultRuleSetId === null) {
            static::query()->where('bom_template_id', $template->id)->delete();

            return;
        }

        static::query()->updateOrCreate(
            ['bom_template_id' => $template->id],
            ['default_rule_set_id' => $defaultRuleSetId]
        );
    }
}
