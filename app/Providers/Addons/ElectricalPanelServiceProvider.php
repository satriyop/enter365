<?php

namespace App\Providers\Addons;

use App\Contracts\Manufacturing\BomTemplateServiceInterface;
use App\Models\ElectricalPanel\BomItemPanelMeta;
use App\Models\ElectricalPanel\BomPanelMeta;
use App\Models\ElectricalPanel\BomTemplateItemPanelMeta;
use App\Models\ElectricalPanel\BomTemplatePanelMeta;
use App\Models\ElectricalPanel\ComponentBrandMapping;
use App\Models\ElectricalPanel\ComponentStandard;
use App\Models\ElectricalPanel\SpecValidationRuleSet;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;
use App\Services\ElectricalPanel\BomTemplateService as PanelBomTemplateService;
use App\Support\Features;
use Illuminate\Support\ServiceProvider;

/**
 * Industry add-on: electrical panel tools (Vahana).
 *
 * Owns meta tables for BOM extension data and brand-aware template services.
 * Core manufacturing tables no longer hold panel FKs.
 */
class ElectricalPanelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Dynamic so withFeatures() works after boot; panel service when flag ON.
        $this->app->bind(BomTemplateServiceInterface::class, function ($app) {
            if (Features::enabled('electrical_panel')) {
                return $app->make(PanelBomTemplateService::class);
            }

            return $app->make(\App\Services\Manufacturing\BomTemplateService::class);
        });
    }

    public function boot(): void
    {
        BomItem::resolveRelationUsing('panelMeta', function (BomItem $item) {
            return $item->hasOne(BomItemPanelMeta::class, 'bom_item_id');
        });

        BomItem::resolveRelationUsing('componentStandard', function (BomItem $item) {
            return $item->hasOneThrough(
                ComponentStandard::class,
                BomItemPanelMeta::class,
                'bom_item_id',
                'id',
                'id',
                'component_standard_id'
            );
        });

        BomTemplateItem::resolveRelationUsing('panelMeta', function (BomTemplateItem $item) {
            return $item->hasOne(BomTemplateItemPanelMeta::class, 'bom_template_item_id');
        });

        BomTemplateItem::resolveRelationUsing('componentStandard', function (BomTemplateItem $item) {
            return $item->hasOneThrough(
                ComponentStandard::class,
                BomTemplateItemPanelMeta::class,
                'bom_template_item_id',
                'id',
                'id',
                'component_standard_id'
            );
        });

        Bom::resolveRelationUsing('panelMeta', function (Bom $bom) {
            return $bom->hasOne(BomPanelMeta::class, 'bom_id');
        });

        Bom::resolveRelationUsing('specRuleSet', function (Bom $bom) {
            return $bom->hasOneThrough(
                SpecValidationRuleSet::class,
                BomPanelMeta::class,
                'bom_id',
                'id',
                'id',
                'spec_rule_set_id'
            );
        });

        BomTemplate::resolveRelationUsing('panelMeta', function (BomTemplate $template) {
            return $template->hasOne(BomTemplatePanelMeta::class, 'bom_template_id');
        });

        BomTemplate::resolveRelationUsing('defaultRuleSet', function (BomTemplate $template) {
            return $template->hasOneThrough(
                SpecValidationRuleSet::class,
                BomTemplatePanelMeta::class,
                'bom_template_id',
                'id',
                'id',
                'default_rule_set_id'
            );
        });

        Product::resolveRelationUsing('componentBrandMappings', function (Product $product) {
            return $product->hasMany(ComponentBrandMapping::class);
        });
    }

    public static function isEnabled(): bool
    {
        return Features::enabled('electrical_panel');
    }
}
