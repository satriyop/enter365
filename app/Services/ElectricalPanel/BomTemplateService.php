<?php

declare(strict_types=1);

namespace App\Services\ElectricalPanel;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Manufacturing\BomTemplateServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\ElectricalPanel\BomItemPanelMeta;
use App\Models\ElectricalPanel\BomPanelMeta;
use App\Models\ElectricalPanel\BomTemplateItemPanelMeta;
use App\Models\ElectricalPanel\BomTemplatePanelMeta;
use App\Models\ElectricalPanel\ComponentBrandMapping;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;
use App\Services\Base\BaseService;
use App\Services\Manufacturing\Concerns\ManagesBomTemplateItems;

/**
 * Brand-aware BOM template operations for electrical_panel add-on.
 * Uses meta tables — never writes extension FKs onto core manufacturing columns.
 */
class BomTemplateService extends BaseService implements BomTemplateServiceInterface
{
    use ManagesBomTemplateItems;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    public function createTemplate(array $data): BomTemplate
    {
        $ruleSetId = $data['default_rule_set_id'] ?? null;
        unset($data['default_rule_set_id']);

        return $this->executeInTransaction('create_bom_template_panel', function () use ($data, $ruleSetId) {
            $data['created_by'] = $this->getUserId();
            $template = BomTemplate::create($data);
            BomTemplatePanelMeta::sync($template, $ruleSetId !== null ? (int) $ruleSetId : null);

            return $template->fresh(['items', 'creator', 'panelMeta.defaultRuleSet']);
        }, ['code' => $data['code']]);
    }

    public function updateTemplate(BomTemplate $template, array $data): BomTemplate
    {
        $ruleSetId = array_key_exists('default_rule_set_id', $data)
            ? $data['default_rule_set_id']
            : false;
        unset($data['default_rule_set_id']);

        return $this->executeInTransaction('update_bom_template_panel', function () use ($template, $data, $ruleSetId) {
            $template->update($data);
            if ($ruleSetId !== false) {
                BomTemplatePanelMeta::sync(
                    $template,
                    $ruleSetId !== null ? (int) $ruleSetId : null
                );
            }

            return $template->fresh(['items', 'creator', 'panelMeta.defaultRuleSet']);
        }, ['template_id' => $template->id]);
    }

    public function deleteTemplate(BomTemplate $template): void
    {
        $this->executeInTransaction('delete_bom_template_panel', function () use ($template) {
            $template->delete();
        }, ['template_id' => $template->id]);
    }

    /**
     * @param  array{code: string, name?: string|null, thumbnail_path?: string|null}  $options
     */
    public function duplicateTemplate(BomTemplate $template, array $options): BomTemplate
    {
        return $this->executeInTransaction('duplicate_bom_template_panel', function () use ($template, $options) {
            $template->load(['items.panelMeta', 'panelMeta']);

            $newTemplate = $template->replicate(['usage_count']);
            $newTemplate->code = $options['code'];
            $newTemplate->name = $options['name'] ?? $template->name.' (Copy)';
            $newTemplate->usage_count = 0;
            $newTemplate->created_by = $this->getUserId();
            if (array_key_exists('thumbnail_path', $options)) {
                $newTemplate->thumbnail_path = $options['thumbnail_path'];
            }
            $newTemplate->save();

            $ruleSetId = $template->panelMeta?->default_rule_set_id;
            BomTemplatePanelMeta::sync(
                $newTemplate,
                $ruleSetId !== null ? (int) $ruleSetId : null
            );

            foreach ($template->items as $item) {
                $newItem = $item->replicate();
                $newItem->template_id = $newTemplate->id;
                $newItem->save();

                $standardId = $item->panelMeta?->component_standard_id;
                if ($standardId) {
                    BomTemplateItemPanelMeta::sync($newItem, (int) $standardId);
                }
            }

            return $newTemplate->fresh(['items.panelMeta', 'creator', 'panelMeta.defaultRuleSet']);
        }, ['template_id' => $template->id, 'code' => $options['code']]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{bom: Bom, report: array<string, mixed>}
     */
    public function createBomFromTemplate(BomTemplate $template, array $options): array
    {
        $template->load([
            'items.product',
            'items.panelMeta.componentStandard.brandMappings.product',
            'panelMeta',
        ]);

        $targetBrand = $options['target_brand'] ?? null;
        $quantityOverrides = $options['quantity_overrides'] ?? [];

        $report = [
            'template_id' => $template->id,
            'template_code' => $template->code,
            'target_brand' => $targetBrand,
            'total_items' => $template->items->count(),
            'resolved' => 0,
            'no_mapping' => 0,
            'using_product' => 0,
            'items' => [],
        ];

        $resolvedLines = [];

        foreach ($template->items as $templateItem) {
            $itemReport = $this->resolveTemplateItem(
                $templateItem,
                $targetBrand,
                $quantityOverrides[$templateItem->id] ?? null
            );
            $report['items'][] = $itemReport;

            match ($itemReport['status']) {
                'resolved' => $report['resolved']++,
                'no_mapping' => $report['no_mapping']++,
                default => $report['using_product']++,
            };

            $resolvedLines[] = $itemReport;
        }

        $specRuleSetId = $template->panelMeta?->default_rule_set_id;

        $bom = $this->executeInTransaction('create_from_template_panel', function () use ($template, $options, $resolvedLines, $specRuleSetId) {
            $bom = new Bom([
                'product_id' => $options['product_id'],
                'name' => $options['name'] ?? "BOM dari Template: {$template->name}",
                'notes' => $options['notes'] ?? "Dibuat dari template {$template->code}",
                'output_quantity' => $options['output_quantity'] ?? 1,
                'status' => DocumentStatus::Draft,
                'version' => '1.0',
            ]);
            $bom->created_by = $this->getUserId();
            $bom->save();

            BomPanelMeta::sync($bom, $specRuleSetId !== null ? (int) $specRuleSetId : null);

            $sortOrder = 0;
            foreach ($resolvedLines as $line) {
                $itemData = $line['bom_item_data'];
                $standardId = $itemData['component_standard_id'] ?? null;
                unset($itemData['component_standard_id']);
                $itemData['sort_order'] = $sortOrder++;

                $item = new BomItem($itemData);
                $item->bom_id = $bom->id;
                $item->calculateTotalCost();
                $item->save();

                if ($standardId) {
                    BomItemPanelMeta::sync($item, (int) $standardId);
                }
            }

            $bom->calculateTotals();
            $bom->save();
            $template->incrementUsage();

            return $bom->fresh(['items.product', 'items.panelMeta', 'product', 'panelMeta']);
        }, ['template_id' => $template->id, 'product_id' => $options['product_id']]);

        return ['bom' => $bom, 'report' => $report];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{items: array<int, array<string, mixed>>, report: array<string, mixed>}
     */
    public function previewCreateFromTemplate(BomTemplate $template, array $options): array
    {
        $template->load([
            'items.product',
            'items.panelMeta.componentStandard.brandMappings.product',
        ]);

        $targetBrand = $options['target_brand'] ?? null;
        $quantityOverrides = $options['quantity_overrides'] ?? [];

        $items = [];
        $report = [
            'template_id' => $template->id,
            'template_code' => $template->code,
            'target_brand' => $targetBrand,
            'total_items' => $template->items->count(),
            'resolved' => 0,
            'no_mapping' => 0,
            'using_product' => 0,
        ];

        foreach ($template->items as $templateItem) {
            $itemReport = $this->resolveTemplateItem(
                $templateItem,
                $targetBrand,
                $quantityOverrides[$templateItem->id] ?? null
            );

            match ($itemReport['status']) {
                'resolved' => $report['resolved']++,
                'no_mapping' => $report['no_mapping']++,
                default => $report['using_product']++,
            };

            $standard = $templateItem->panelMeta?->componentStandard;

            $items[] = [
                'template_item_id' => $templateItem->id,
                'type' => $templateItem->type,
                'description' => $itemReport['bom_item_data']['description'] ?? $templateItem->description,
                'quantity' => $itemReport['bom_item_data']['quantity'] ?? $templateItem->default_quantity,
                'unit' => $itemReport['bom_item_data']['unit'] ?? $templateItem->unit,
                'unit_cost' => $itemReport['bom_item_data']['unit_cost'] ?? 0,
                'product' => $itemReport['product'] ?? null,
                'component_standard' => $standard ? [
                    'id' => $standard->id,
                    'code' => $standard->code,
                    'name' => $standard->name,
                ] : null,
                'status' => $itemReport['status'],
                'notes' => $itemReport['notes'] ?? null,
                'is_required' => $templateItem->is_required,
                'is_quantity_variable' => $templateItem->is_quantity_variable,
            ];
        }

        return ['items' => $items, 'report' => $report];
    }

    /**
     * @return array<int, array{code: string, name: string, coverage: int, coverage_percent: float}>
     */
    public function getAvailableBrandsForTemplate(BomTemplate $template): array
    {
        $template->load('items.panelMeta.componentStandard.brandMappings');

        $itemsWithStandard = $template->items->filter(
            fn ($item) => $item->panelMeta?->component_standard_id !== null
        );

        if ($itemsWithStandard->isEmpty()) {
            return [];
        }

        $brandCounts = [];
        foreach ($itemsWithStandard as $item) {
            $standard = $item->panelMeta?->componentStandard;
            if (! $standard) {
                continue;
            }
            foreach ($standard->brandMappings as $mapping) {
                $brand = strtolower((string) $mapping->brand);
                $brandCounts[$brand] = ($brandCounts[$brand] ?? 0) + 1;
            }
        }

        $total = $itemsWithStandard->count();
        $brands = [];
        $brandNames = ComponentBrandMapping::getBrands();

        foreach ($brandCounts as $code => $count) {
            $brands[] = [
                'code' => $code,
                'name' => $brandNames[$code] ?? ucfirst($code),
                'coverage' => $count,
                'coverage_percent' => round(($count / $total) * 100, 1),
            ];
        }

        usort($brands, fn ($a, $b) => $b['coverage'] <=> $a['coverage']);

        return $brands;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function applyItemAddonAttributes(BomTemplateItem $item, array $attributes): void
    {
        if (! array_key_exists('component_standard_id', $attributes)) {
            return;
        }

        $standardId = $attributes['component_standard_id'];
        BomTemplateItemPanelMeta::sync(
            $item,
            $standardId !== null ? (int) $standardId : null
        );
    }

    /**
     * @return array{status: string, bom_item_data: array<string, mixed>, product: ?array<string, mixed>, notes: ?string}
     */
    private function resolveTemplateItem(
        BomTemplateItem $templateItem,
        ?string $targetBrand,
        ?float $quantityOverride
    ): array {
        $quantity = $quantityOverride ?? (float) $templateItem->default_quantity;
        $standardId = $templateItem->panelMeta?->component_standard_id;

        if ($templateItem->product_id) {
            $product = $templateItem->product;

            return [
                'status' => 'using_product',
                'bom_item_data' => [
                    'type' => $templateItem->type,
                    'product_id' => $product->id,
                    'component_standard_id' => $standardId,
                    'description' => $product->name,
                    'quantity' => $quantity,
                    'unit' => $templateItem->unit ?? $product->unit ?? 'pcs',
                    'unit_cost' => $product->purchase_price ?? 0,
                    'notes' => $templateItem->notes,
                ],
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'brand' => $product->brand,
                    'purchase_price' => $product->purchase_price,
                ],
                'notes' => 'Menggunakan produk spesifik dari template',
            ];
        }

        if (! $standardId) {
            return [
                'status' => 'using_product',
                'bom_item_data' => [
                    'type' => $templateItem->type,
                    'product_id' => null,
                    'component_standard_id' => null,
                    'description' => $templateItem->description,
                    'quantity' => $quantity,
                    'unit' => $templateItem->unit ?? 'pcs',
                    'unit_cost' => 0,
                    'notes' => $templateItem->notes,
                ],
                'product' => null,
                'notes' => 'Item manual tanpa produk terkait',
            ];
        }

        if ($targetBrand) {
            $mapping = $this->findBrandMapping((int) $standardId, $targetBrand);
            if ($mapping?->product) {
                return $this->resolvedFromMapping($templateItem, $mapping, $quantity, (int) $standardId);
            }

            return $this->noMappingLine($templateItem, $quantity, (int) $standardId, "Tidak ada mapping untuk brand '{$targetBrand}'");
        }

        $mapping = $this->findPreferredMapping((int) $standardId);
        if ($mapping?->product) {
            $result = $this->resolvedFromMapping($templateItem, $mapping, $quantity, (int) $standardId);
            $result['notes'] = "Menggunakan brand preferensi: {$mapping->brand}";

            return $result;
        }

        return $this->noMappingLine($templateItem, $quantity, (int) $standardId, 'Tidak ada brand mapping untuk komponen standar ini');
    }

    /**
     * @return array{status: string, bom_item_data: array<string, mixed>, product: array<string, mixed>, notes: ?string}
     */
    private function resolvedFromMapping(
        BomTemplateItem $templateItem,
        ComponentBrandMapping $mapping,
        float $quantity,
        int $standardId
    ): array {
        $product = $mapping->product;

        return [
            'status' => 'resolved',
            'bom_item_data' => [
                'type' => $templateItem->type,
                'product_id' => $product->id,
                'component_standard_id' => $standardId,
                'description' => $product->name,
                'quantity' => $quantity,
                'unit' => $templateItem->unit ?? $product->unit ?? 'pcs',
                'unit_cost' => $product->purchase_price ?? 0,
                'notes' => $templateItem->notes,
            ],
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'brand' => $product->brand,
                'brand_sku' => $mapping->brand_sku,
                'purchase_price' => $product->purchase_price,
            ],
            'notes' => null,
        ];
    }

    /**
     * @return array{status: string, bom_item_data: array<string, mixed>, product: null, notes: string}
     */
    private function noMappingLine(
        BomTemplateItem $templateItem,
        float $quantity,
        int $standardId,
        string $notes
    ): array {
        return [
            'status' => 'no_mapping',
            'bom_item_data' => [
                'type' => $templateItem->type,
                'product_id' => null,
                'component_standard_id' => $standardId,
                'description' => $templateItem->description,
                'quantity' => $quantity,
                'unit' => $templateItem->unit ?? 'pcs',
                'unit_cost' => 0,
                'notes' => $templateItem->notes,
            ],
            'product' => null,
            'notes' => $notes,
        ];
    }

    private function findBrandMapping(int $componentStandardId, string $brand): ?ComponentBrandMapping
    {
        return ComponentBrandMapping::query()
            ->where('component_standard_id', $componentStandardId)
            ->where('brand', strtolower($brand))
            ->with('product')
            ->first();
    }

    private function findPreferredMapping(int $componentStandardId): ?ComponentBrandMapping
    {
        return ComponentBrandMapping::query()
            ->where('component_standard_id', $componentStandardId)
            ->where('is_preferred', true)
            ->with('product')
            ->first()
            ?? ComponentBrandMapping::query()
                ->where('component_standard_id', $componentStandardId)
                ->with('product')
                ->first();
    }
}
