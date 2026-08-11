<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Contracts\ElectricalPanel\BomTemplateBrandResolverInterface;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;

/**
 * Core default: electrical_panel add-on disabled — no brand/standard resolution.
 */
class NullBomTemplateBrandResolver implements BomTemplateBrandResolverInterface
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function resolveStandardBasedItem(
        BomTemplateItem $templateItem,
        ?string $targetBrand,
        float $quantity
    ): ?array {
        // Strip standard-only lines to manual description (no panel dependency).
        if ($templateItem->component_standard_id) {
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
                'notes' => 'Komponen standar diabaikan (electrical_panel off)',
            ];
        }

        return null;
    }

    public function shouldPersistStandardId(): bool
    {
        return false;
    }

    public function templateEagerLoads(): array
    {
        return ['items.product'];
    }

    public function standardPreview(BomTemplateItem $templateItem): ?array
    {
        return null;
    }

    public function availableBrandsForTemplate(BomTemplate $template): array
    {
        return [];
    }

    public function templateSpecRuleSetId(BomTemplate $template): ?int
    {
        return null;
    }
}
