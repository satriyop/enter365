<?php

namespace App\Providers\Addons;

use App\Contracts\ElectricalPanel\BomTemplateBrandResolverInterface;
use App\Models\ElectricalPanel\ComponentStandard;
use App\Models\ElectricalPanel\SpecValidationRuleSet;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;
use App\Services\ElectricalPanel\BomTemplateBrandResolver;
use App\Services\Manufacturing\NullBomTemplateBrandResolver;
use App\Support\Features;
use Illuminate\Support\ServiceProvider;

/**
 * Industry add-on: electrical panel tools (Vahana).
 *
 * - Binds real BomTemplateBrandResolver when flag ON, else NullBomTemplateBrandResolver
 * - Registers Eloquent relations on core BOM models without core importing panel types
 */
class ElectricalPanelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Resolve per call so withFeatures() / runtime flag flips work in tests & CLI.
        $this->app->bind(BomTemplateBrandResolverInterface::class, function ($app) {
            if (Features::enabled('electrical_panel')) {
                return $app->make(BomTemplateBrandResolver::class);
            }

            return $app->make(NullBomTemplateBrandResolver::class);
        });
    }

    public function boot(): void
    {
        // Always register relations so eager-loads work when add-on data/FK exists.
        // Core models intentionally do not type-hint ElectricalPanel classes.
        BomItem::resolveRelationUsing('componentStandard', function (BomItem $item) {
            return $item->belongsTo(ComponentStandard::class, 'component_standard_id');
        });

        BomTemplateItem::resolveRelationUsing('componentStandard', function (BomTemplateItem $item) {
            return $item->belongsTo(ComponentStandard::class, 'component_standard_id');
        });

        Bom::resolveRelationUsing('specRuleSet', function (Bom $bom) {
            return $bom->belongsTo(SpecValidationRuleSet::class, 'spec_rule_set_id');
        });

        BomTemplate::resolveRelationUsing('defaultRuleSet', function (BomTemplate $template) {
            return $template->belongsTo(SpecValidationRuleSet::class, 'default_rule_set_id');
        });
    }

    public static function isEnabled(): bool
    {
        return Features::enabled('electrical_panel');
    }
}
