<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Manufacturing\BomTemplateBrandResolverInterface;
use App\Contracts\Manufacturing\BomTemplateServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;
use App\Services\Base\BaseService;

class BomTemplateService extends BaseService implements BomTemplateServiceInterface
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        private BomTemplateBrandResolverInterface $brandResolver,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a new BOM template.
     *
     * @param  array{code: string, name: string, description?: string, category?: string, thumbnail_path?: string, default_rule_set_id?: int, is_active?: bool}  $data
     */
    public function createTemplate(array $data): BomTemplate
    {
        return $this->executeInTransaction('create_bom_template', function () use ($data) {
            $data['created_by'] = $this->getUserId();

            return BomTemplate::create($data);
        }, ['code' => $data['code']]);
    }

    /**
     * Update a BOM template.
     *
     * @param  array{code?: string, name?: string, description?: string, category?: string, thumbnail_path?: string, default_rule_set_id?: int, is_active?: bool}  $data
     */
    public function updateTemplate(BomTemplate $template, array $data): BomTemplate
    {
        return $this->executeInTransaction('update_bom_template', function () use ($template, $data) {
            $template->update($data);

            return $template->fresh(['items', 'defaultRuleSet', 'creator']);
        }, ['template_id' => $template->id]);
    }

    /**
     * Delete a BOM template.
     */
    public function deleteTemplate(BomTemplate $template): void
    {
        $this->executeInTransaction('delete_bom_template', function () use ($template) {
            $template->delete();
        }, ['template_id' => $template->id]);
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
        $template->load($this->brandResolver->templateEagerLoads());

        $targetBrand = $this->brandResolver->isEnabled()
            ? ($options['target_brand'] ?? null)
            : null;
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

        $specRuleSetId = $this->brandResolver->templateSpecRuleSetId($template);

        // Create the BOM
        $bom = $this->executeInTransaction('create_from_template', function () use ($template, $options, $bomItems, $specRuleSetId) {
            $bom = new Bom([
                'product_id' => $options['product_id'],
                'name' => $options['name'] ?? "BOM dari Template: {$template->name}",
                'notes' => $options['notes'] ?? "Dibuat dari template {$template->code}",
                'output_quantity' => $options['output_quantity'] ?? 1,
                'status' => DocumentStatus::Draft,
                'version' => '1.0',
                'spec_rule_set_id' => $specRuleSetId,
            ]);
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
        $template->load($this->brandResolver->templateEagerLoads());

        $targetBrand = $this->brandResolver->isEnabled()
            ? ($options['target_brand'] ?? null)
            : null;
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
                'component_standard' => $this->brandResolver->standardPreview($templateItem),
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
     * Resolve a template item to a BOM item (core product/manual paths + optional add-on).
     *
     * @return array{status: string, bom_item_data: array<string, mixed>, product: ?array<string, mixed>, notes: ?string}
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
                    'component_standard_id' => $this->brandResolver->shouldPersistStandardId()
                        ? $templateItem->component_standard_id
                        : null,
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

        $addonResult = $this->brandResolver->resolveStandardBasedItem(
            $templateItem,
            $targetBrand,
            $quantity
        );

        if ($addonResult !== null) {
            return $addonResult;
        }

        // No product and no add-on standard resolution - plain description
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
     * @return array<int, array{code: string, name: string, coverage: int, coverage_percent: float}>
     */
    public function getAvailableBrandsForTemplate(BomTemplate $template): array
    {
        return $this->brandResolver->availableBrandsForTemplate($template);
    }
}
