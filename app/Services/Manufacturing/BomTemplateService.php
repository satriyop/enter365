<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Manufacturing\BomTemplateServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;
use App\Models\Manufacturing\ComponentBrandMapping;
use App\Services\Base\AbstractApplicationService;

class BomTemplateService extends AbstractApplicationService implements BomTemplateServiceInterface
{
    public function __construct(
        private BomService $bomService,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a new BOM from a template.
     *
     * @param  array<string, mixed>  $options  {
     *                                         target_brand?: string,
     *                                         product_id: int,
     *                                         name?: string,
     *                                         notes?: string,
     *                                         output_quantity?: float,
     *                                         quantity_overrides?: array<int, float>,
     *                                         }
     * @return array{bom: Bom, report: array}
     */
    public function createBomFromTemplate(BomTemplate $template, array $options): array
    {
        $template->load([
            'items.componentStandard.brandMappings.product',
            'items.product',
            'defaultRuleSet',
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

        $bomItems = [];

        foreach ($template->items as $templateItem) {
            $itemReport = $this->resolveTemplateItem(
                $templateItem,
                $targetBrand,
                $quantityOverrides[$templateItem->id] ?? null
            );

            $report['items'][] = $itemReport;

            if ($itemReport['status'] === 'resolved') {
                $report['resolved']++;
            } elseif ($itemReport['status'] === 'no_mapping') {
                $report['no_mapping']++;
            } elseif ($itemReport['status'] === 'using_product') {
                $report['using_product']++;
            }

            $bomItems[] = $itemReport['bom_item_data'];
        }

        // Create the BOM
        $bom = $this->executeInTransaction('create_from_template', function () use ($template, $options, $bomItems) {
            $bom = new Bom([
                'product_id' => $options['product_id'],
                'name' => $options['name'] ?? "BOM dari Template: {$template->name}",
                'notes' => $options['notes'] ?? "Dibuat dari template {$template->code}",
                'output_quantity' => $options['output_quantity'] ?? 1,
                'status' => DocumentStatus::Draft,
                'version' => '1.0',
                'spec_rule_set_id' => $template->default_rule_set_id,
            ]);
            $bom->bom_number = Bom::generateBomNumber();
            $bom->created_by = $this->getUserId();
            $bom->save();

            // Create items
            $sortOrder = 0;
            foreach ($bomItems as $itemData) {
                if ($itemData !== null) {
                    $itemData['sort_order'] = $sortOrder++;
                    $item = new BomItem($itemData);
                    $item->bom_id = $bom->id;
                    $item->calculateTotalCost();
                    $item->save();
                }
            }

            // Recalculate totals
            $bom->calculateTotals();
            $bom->save();

            // Increment template usage
            $template->incrementUsage();

            return $bom->fresh(['items.product', 'product']);
        }, ['template_id' => $template->id, 'product_id' => $options['product_id']]);

        return [
            'bom' => $bom,
            'report' => $report,
        ];
    }

    /**
     * Preview creating a BOM from a template without actually creating it.
     *
     * @param  array<string, mixed>  $options
     * @return array{items: array, report: array}
     */
    public function previewCreateFromTemplate(BomTemplate $template, array $options): array
    {
        $template->load([
            'items.componentStandard.brandMappings.product',
            'items.product',
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

            if ($itemReport['status'] === 'resolved') {
                $report['resolved']++;
            } elseif ($itemReport['status'] === 'no_mapping') {
                $report['no_mapping']++;
            } elseif ($itemReport['status'] === 'using_product') {
                $report['using_product']++;
            }

            $items[] = [
                'template_item_id' => $templateItem->id,
                'type' => $templateItem->type,
                'description' => $itemReport['bom_item_data']['description'] ?? $templateItem->description,
                'quantity' => $itemReport['bom_item_data']['quantity'] ?? $templateItem->default_quantity,
                'unit' => $itemReport['bom_item_data']['unit'] ?? $templateItem->unit,
                'unit_cost' => $itemReport['bom_item_data']['unit_cost'] ?? 0,
                'product' => $itemReport['product'] ?? null,
                'component_standard' => $templateItem->componentStandard ? [
                    'id' => $templateItem->componentStandard->id,
                    'code' => $templateItem->componentStandard->code,
                    'name' => $templateItem->componentStandard->name,
                ] : null,
                'status' => $itemReport['status'],
                'notes' => $itemReport['notes'] ?? null,
                'is_required' => $templateItem->is_required,
                'is_quantity_variable' => $templateItem->is_quantity_variable,
            ];
        }

        return [
            'items' => $items,
            'report' => $report,
        ];
    }

    /**
     * Resolve a template item to a BOM item.
     *
     * @return array{status: string, bom_item_data: ?array, product: ?array, notes: ?string}
     */
    private function resolveTemplateItem(
        BomTemplateItem $templateItem,
        ?string $targetBrand,
        ?float $quantityOverride
    ): array {
        $quantity = $quantityOverride ?? (float) $templateItem->default_quantity;

        // Item has a direct product - use it regardless of brand
        if ($templateItem->product_id) {
            $product = $templateItem->product;

            return [
                'status' => 'using_product',
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
                    'purchase_price' => $product->purchase_price,
                ],
                'notes' => 'Menggunakan produk spesifik dari template',
            ];
        }

        // Item has a component standard - try to resolve via brand mapping
        if ($templateItem->component_standard_id && $targetBrand) {
            $mapping = $this->findBrandMapping(
                $templateItem->component_standard_id,
                $targetBrand
            );

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

            // No mapping found for this brand
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

        // Item has component standard but no target brand specified
        if ($templateItem->component_standard_id && ! $targetBrand) {
            // Try to find preferred mapping
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

        // No product and no component standard - just use description
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

    /**
     * Find brand mapping for a component standard.
     */
    private function findBrandMapping(int $componentStandardId, string $brand): ?ComponentBrandMapping
    {
        return ComponentBrandMapping::query()
            ->where('component_standard_id', $componentStandardId)
            ->where('brand', strtolower($brand))
            ->with('product')
            ->first();
    }

    /**
     * Find preferred mapping for a component standard.
     */
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

    /**
     * Get available brands for a template based on its component standards.
     *
     * @return array<int, array{code: string, name: string, coverage: int, coverage_percent: float}>
     */
    public function getAvailableBrandsForTemplate(BomTemplate $template): array
    {
        $template->load('items.componentStandard.brandMappings');

        $itemsWithStandard = $template->items->filter(fn ($item) => $item->component_standard_id !== null);

        if ($itemsWithStandard->isEmpty()) {
            return [];
        }

        // Get all brand codes from mappings
        $brandCounts = [];
        foreach ($itemsWithStandard as $item) {
            if ($item->componentStandard) {
                foreach ($item->componentStandard->brandMappings as $mapping) {
                    $brand = strtolower($mapping->brand);
                    $brandCounts[$brand] = ($brandCounts[$brand] ?? 0) + 1;
                }
            }
        }

        // Build result with coverage info
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

        // Sort by coverage descending
        usort($brands, fn ($a, $b) => $b['coverage'] <=> $a['coverage']);

        return $brands;
    }
}
