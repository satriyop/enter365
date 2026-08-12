<?php

declare(strict_types=1);

namespace Tests\Support\Addons;

use App\Models\ElectricalPanel\BomItemPanelMeta;
use App\Models\ElectricalPanel\BomTemplateItemPanelMeta;
use App\Models\ElectricalPanel\BomTemplatePanelMeta;
use App\Models\ElectricalPanel\ComponentStandard;
use App\Models\ElectricalPanel\SpecValidationRuleSet;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;

/**
 * Test helpers for electrical_panel meta (keep out of core Pest.php / factories).
 */
final class ElectricalPanelHelpers
{
    public static function attachBomItemStandard(BomItem $item, ComponentStandard $standard): void
    {
        BomItemPanelMeta::sync($item, $standard->id);
    }

    public static function attachTemplateItemStandard(BomTemplateItem $item, ComponentStandard $standard): void
    {
        BomTemplateItemPanelMeta::sync($item, $standard->id);
    }

    public static function attachTemplateRuleSet(BomTemplate $template, ?SpecValidationRuleSet $ruleSet = null): SpecValidationRuleSet
    {
        $ruleSet ??= SpecValidationRuleSet::factory()->create();
        BomTemplatePanelMeta::sync($template, $ruleSet->id);

        return $ruleSet;
    }
}
