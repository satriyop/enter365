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
use App\Support\AddonExtensions;
use App\Support\Features;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rule;

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

        $this->registerCoreExtensionPoints();
    }

    /**
     * Extend core resources / validation / eager-loads without core naming this pack.
     */
    private function registerCoreExtensionPoints(): void
    {
        AddonExtensions::registerEagerLoads('bom.show', [
            'items.panelMeta',
        ]);
        AddonExtensions::registerEagerLoads('bom_template.list', [
            'defaultRuleSet',
        ]);
        AddonExtensions::registerEagerLoads('bom_template.show', [
            'items.panelMeta.componentStandard',
            'panelMeta.defaultRuleSet',
            'defaultRuleSet',
        ]);
        AddonExtensions::registerEagerLoads('bom_template.item', [
            'panelMeta.componentStandard',
        ]);
        AddonExtensions::registerEagerLoads('bom_template.duplicate', [
            'items.panelMeta.componentStandard',
            'panelMeta.defaultRuleSet',
            'defaultRuleSet',
        ]);

        AddonExtensions::registerResource('bom_template', function ($resource): array {
            if (! Features::enabled('electrical_panel')) {
                return [];
            }

            $template = $resource->resource;
            if (! $template->relationLoaded('defaultRuleSet') || ! $template->defaultRuleSet) {
                return ['default_rule_set' => null];
            }

            return [
                'default_rule_set' => [
                    'id' => $template->defaultRuleSet->id,
                    'name' => $template->defaultRuleSet->name,
                    'code' => $template->defaultRuleSet->code,
                ],
            ];
        });

        AddonExtensions::registerResource('bom_item', function ($resource): array {
            if (! Features::enabled('electrical_panel')) {
                return [];
            }

            $item = $resource->resource;

            return [
                'component_standard_id' => $item->panelMeta?->component_standard_id,
            ];
        });

        AddonExtensions::registerResource('bom_template_item', function ($resource): array {
            if (! Features::enabled('electrical_panel')) {
                return [];
            }

            $item = $resource->resource;
            $standard = null;
            if ($item->relationLoaded('panelMeta') || $item->relationLoaded('componentStandard')) {
                $standard = $item->panelMeta?->componentStandard ?? $item->componentStandard;
            }

            return [
                'component_standard_id' => $item->panelMeta?->component_standard_id,
                'component_standard' => $standard ? [
                    'id' => $standard->id,
                    'code' => $standard->code,
                    'name' => $standard->name,
                    'category' => $standard->category,
                ] : null,
                'has_component_standard' => $item->panelMeta?->component_standard_id !== null,
            ];
        });

        AddonExtensions::registerValidationRules('bom_template', function (): array {
            if (! Features::enabled('electrical_panel')) {
                return [
                    'default_rule_set_id' => ['prohibited'],
                ];
            }

            return [
                'default_rule_set_id' => ['nullable', 'integer', 'exists:spec_validation_rule_sets,id'],
            ];
        });

        AddonExtensions::registerValidationRules('bom_template_item', function (): array {
            if (! Features::enabled('electrical_panel')) {
                return [
                    'component_standard_id' => ['prohibited'],
                ];
            }

            return [
                'component_standard_id' => ['nullable', 'integer', 'exists:component_standards,id'],
            ];
        });

        AddonExtensions::registerValidationRules('create_bom_from_template', function (): array {
            if (! Features::enabled('electrical_panel')) {
                return [
                    'target_brand' => ['prohibited'],
                ];
            }

            return [
                'target_brand' => [
                    'nullable',
                    'string',
                    Rule::in(array_keys(ComponentBrandMapping::getBrands())),
                ],
            ];
        });

        AddonExtensions::registerValidationRules('preview_bom_from_template', function (): array {
            if (! Features::enabled('electrical_panel')) {
                return [
                    'target_brand' => ['prohibited'],
                ];
            }

            return [
                'target_brand' => [
                    'nullable',
                    'string',
                    Rule::in(array_keys(ComponentBrandMapping::getBrands())),
                ],
            ];
        });

        AddonExtensions::registerMeta('bom_template.items_with_extension', function (BomTemplate $template): int {
            if (! Features::enabled('electrical_panel')) {
                return 0;
            }

            return (int) $template->items()
                ->whereHas('panelMeta', fn ($q) => $q->whereNotNull('component_standard_id'))
                ->count();
        });
    }

    public static function isEnabled(): bool
    {
        return Features::enabled('electrical_panel');
    }
}
