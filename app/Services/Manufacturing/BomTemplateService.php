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
use App\Services\Base\BaseService;

/**
 * Core BOM templates — product / manual lines only.
 * Brand / component-standard resolution lives in ElectricalPanel add-on.
 */
class BomTemplateService extends BaseService implements BomTemplateServiceInterface
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * @param  array{code: string, name: string, description?: string, category?: string, thumbnail_path?: string, is_active?: bool}  $data
     */
    public function createTemplate(array $data): BomTemplate
    {
        return $this->executeInTransaction('create_bom_template', function () use ($data) {
            $data = $this->coreTemplateAttributes($data);
            $data['created_by'] = $this->getUserId();

            return BomTemplate::create($data);
        }, ['code' => $data['code']]);
    }

    /**
     * @param  array{code?: string, name?: string, description?: string, category?: string, thumbnail_path?: string, is_active?: bool}  $data
     */
    public function updateTemplate(BomTemplate $template, array $data): BomTemplate
    {
        return $this->executeInTransaction('update_bom_template', function () use ($template, $data) {
            $template->update($this->coreTemplateAttributes($data));

            return $template->fresh(['items', 'creator']);
        }, ['template_id' => $template->id]);
    }

    public function deleteTemplate(BomTemplate $template): void
    {
        $this->executeInTransaction('delete_bom_template', function () use ($template) {
            $template->delete();
        }, ['template_id' => $template->id]);
    }

    /**
     * Core has no panel meta tables — ignore standard attachment.
     */
    public function syncTemplateItemStandard(BomTemplateItem $item, ?int $componentStandardId): void
    {
        // no-op
    }

    /**
     * @param  array{code: string, name?: string|null, thumbnail_path?: string|null}  $options
     */
    public function duplicateTemplate(BomTemplate $template, array $options): BomTemplate
    {
        return $this->executeInTransaction('duplicate_bom_template', function () use ($template, $options) {
            $newTemplate = $template->replicate(['usage_count']);
            $newTemplate->code = $options['code'];
            $newTemplate->name = $options['name'] ?? $template->name.' (Copy)';
            $newTemplate->usage_count = 0;
            $newTemplate->created_by = $this->getUserId();
            if (array_key_exists('thumbnail_path', $options)) {
                $newTemplate->thumbnail_path = $options['thumbnail_path'];
            }
            $newTemplate->save();

            foreach ($template->items as $item) {
                $newItem = $item->replicate();
                $newItem->template_id = $newTemplate->id;
                $newItem->save();
            }

            return $newTemplate->fresh(['items', 'creator']);
        }, ['template_id' => $template->id, 'code' => $options['code']]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{bom: Bom, report: array<string, mixed>}
     */
    public function createBomFromTemplate(BomTemplate $template, array $options): array
    {
        $template->load(['items.product']);

        $quantityOverrides = $options['quantity_overrides'] ?? [];

        $report = [
            'template_id' => $template->id,
            'template_code' => $template->code,
            'target_brand' => null,
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
                $quantityOverrides[$templateItem->id] ?? null
            );
            $report['items'][] = $itemReport;
            $report['using_product']++;
            $bomItems[] = $itemReport['bom_item_data'];
        }

        $bom = $this->executeInTransaction('create_from_template', function () use ($template, $options, $bomItems) {
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

            $bom->calculateTotals();
            $bom->save();
            $template->incrementUsage();

            return $bom->fresh(['items.product', 'product']);
        }, ['template_id' => $template->id, 'product_id' => $options['product_id']]);

        return [
            'bom' => $bom,
            'report' => $report,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{items: array<int, array<string, mixed>>, report: array<string, mixed>}
     */
    public function previewCreateFromTemplate(BomTemplate $template, array $options): array
    {
        $template->load(['items.product']);
        $quantityOverrides = $options['quantity_overrides'] ?? [];

        $items = [];
        $report = [
            'template_id' => $template->id,
            'template_code' => $template->code,
            'target_brand' => null,
            'total_items' => $template->items->count(),
            'resolved' => 0,
            'no_mapping' => 0,
            'using_product' => 0,
        ];

        foreach ($template->items as $templateItem) {
            $itemReport = $this->resolveTemplateItem(
                $templateItem,
                $quantityOverrides[$templateItem->id] ?? null
            );
            $report['using_product']++;

            $items[] = [
                'template_item_id' => $templateItem->id,
                'type' => $templateItem->type,
                'description' => $itemReport['bom_item_data']['description'] ?? $templateItem->description,
                'quantity' => $itemReport['bom_item_data']['quantity'] ?? $templateItem->default_quantity,
                'unit' => $itemReport['bom_item_data']['unit'] ?? $templateItem->unit,
                'unit_cost' => $itemReport['bom_item_data']['unit_cost'] ?? 0,
                'product' => $itemReport['product'] ?? null,
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
     * Core never resolves brands — empty list.
     *
     * @return array<int, array{code: string, name: string, coverage: int, coverage_percent: float}>
     */
    public function getAvailableBrandsForTemplate(BomTemplate $template): array
    {
        return [];
    }

    /**
     * Only core BOM template attributes (never panel add-on fields).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function coreTemplateAttributes(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'code', 'name', 'description', 'category', 'thumbnail_path', 'is_active',
        ]));
    }

    /**
     * @return array{status: string, bom_item_data: array<string, mixed>, product: ?array<string, mixed>, notes: ?string}
     */
    private function resolveTemplateItem(BomTemplateItem $templateItem, ?float $quantityOverride): array
    {
        $quantity = $quantityOverride ?? (float) $templateItem->default_quantity;

        if ($templateItem->product_id) {
            $product = $templateItem->product;

            return [
                'status' => 'using_product',
                'bom_item_data' => [
                    'type' => $templateItem->type,
                    'product_id' => $product->id,
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

        return [
            'status' => 'using_product',
            'bom_item_data' => [
                'type' => $templateItem->type,
                'product_id' => null,
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
}
