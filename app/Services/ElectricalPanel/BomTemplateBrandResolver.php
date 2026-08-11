<?php

declare(strict_types=1);

namespace App\Services\ElectricalPanel;

use App\Contracts\ElectricalPanel\BomTemplateBrandResolverInterface;
use App\Models\ElectricalPanel\ComponentBrandMapping;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;

/**
 * Real electrical_panel implementation of template brand / standard resolution.
 */
class BomTemplateBrandResolver implements BomTemplateBrandResolverInterface
{
    public function isEnabled(): bool
    {
        return true;
    }

    public function resolveStandardBasedItem(
        BomTemplateItem $templateItem,
        ?string $targetBrand,
        float $quantity
    ): ?array {
        if (! $templateItem->component_standard_id) {
            return null;
        }

        if ($targetBrand) {
            $mapping = $this->findBrandMapping($templateItem->component_standard_id, $targetBrand);

            if ($mapping && $mapping->product) {
                $product = $mapping->product;

                return [
                    'status' => 'resolved',
                    'bom_item_data' => [
                        'type' => $templateItem->type,
                        'product_id' => $product->id,
                        'component_standard_id' => $templateItem->component_standard_id,
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

            return [
                'status' => 'no_mapping',
                'bom_item_data' => [
                    'type' => $templateItem->type,
                    'product_id' => null,
                    'component_standard_id' => $templateItem->component_standard_id,
                    'description' => $templateItem->description,
                    'quantity' => $quantity,
                    'unit' => $templateItem->unit ?? 'pcs',
                    'unit_cost' => 0,
                    'notes' => $templateItem->notes,
                ],
                'product' => null,
                'notes' => "Tidak ada mapping untuk brand '{$targetBrand}'",
            ];
        }

        $mapping = $this->findPreferredMapping($templateItem->component_standard_id);

        if ($mapping && $mapping->product) {
            $product = $mapping->product;

            return [
                'status' => 'resolved',
                'bom_item_data' => [
                    'type' => $templateItem->type,
                    'product_id' => $product->id,
                    'component_standard_id' => $templateItem->component_standard_id,
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
                'notes' => "Menggunakan brand preferensi: {$mapping->brand}",
            ];
        }

        return [
            'status' => 'no_mapping',
            'bom_item_data' => [
                'type' => $templateItem->type,
                'product_id' => null,
                'component_standard_id' => $templateItem->component_standard_id,
                'description' => $templateItem->description,
                'quantity' => $quantity,
                'unit' => $templateItem->unit ?? 'pcs',
                'unit_cost' => 0,
                'notes' => $templateItem->notes,
            ],
            'product' => null,
            'notes' => 'Tidak ada brand mapping untuk komponen standar ini',
        ];
    }

    public function shouldPersistStandardId(): bool
    {
        return true;
    }

    public function templateEagerLoads(): array
    {
        return [
            'items.componentStandard.brandMappings.product',
            'items.product',
            'defaultRuleSet',
        ];
    }

    public function standardPreview(BomTemplateItem $templateItem): ?array
    {
        $standard = $templateItem->componentStandard;
        if (! $standard) {
            return null;
        }

        return [
            'id' => $standard->id,
            'code' => $standard->code,
            'name' => $standard->name,
        ];
    }

    public function availableBrandsForTemplate(BomTemplate $template): array
    {
        $template->load('items.componentStandard.brandMappings');

        $itemsWithStandard = $template->items->filter(fn ($item) => $item->component_standard_id !== null);

        if ($itemsWithStandard->isEmpty()) {
            return [];
        }

        $brandCounts = [];
        foreach ($itemsWithStandard as $item) {
            if ($item->componentStandard) {
                foreach ($item->componentStandard->brandMappings as $mapping) {
                    $brand = strtolower((string) $mapping->brand);
                    $brandCounts[$brand] = ($brandCounts[$brand] ?? 0) + 1;
                }
            }
        }

        $totalStandardItems = $itemsWithStandard->count();
        $brands = [];
        $brandNames = ComponentBrandMapping::getBrands();

        foreach ($brandCounts as $brandCode => $count) {
            $brands[] = [
                'code' => $brandCode,
                'name' => $brandNames[$brandCode] ?? ucfirst($brandCode),
                'coverage' => $count,
                'coverage_percent' => round(($count / $totalStandardItems) * 100, 1),
            ];
        }

        usort($brands, fn ($a, $b) => $b['coverage'] <=> $a['coverage']);

        return $brands;
    }

    public function templateSpecRuleSetId(BomTemplate $template): ?int
    {
        return $template->default_rule_set_id;
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
