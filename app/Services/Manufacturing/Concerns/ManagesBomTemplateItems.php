<?php

declare(strict_types=1);

namespace App\Services\Manufacturing\Concerns;

use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;

/**
 * Shared BOM template item mutations for core and industry add-on services.
 *
 * Requires host class to provide executeInTransaction() (BaseService) and
 * applyItemAddonAttributes() (BomTemplateServiceInterface).
 */
trait ManagesBomTemplateItems
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $addonAttributes
     */
    public function addItem(BomTemplate $template, array $data, array $addonAttributes = []): BomTemplateItem
    {
        return $this->executeInTransaction('add_bom_template_item', function () use ($template, $data, $addonAttributes) {
            $data = $this->coreItemAttributes($data);

            if (! array_key_exists('sort_order', $data) || $data['sort_order'] === null) {
                $data['sort_order'] = (int) $template->items()->max('sort_order') + 1;
            }

            $item = $template->items()->create($data);
            $this->applyItemAddonAttributes($item, $addonAttributes);

            return $item->fresh() ?? $item;
        }, ['template_id' => $template->id]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $addonAttributes
     */
    public function updateItem(
        BomTemplate $template,
        BomTemplateItem $item,
        array $data,
        array $addonAttributes = []
    ): BomTemplateItem {
        $this->ensureItemBelongsToTemplate($template, $item);

        return $this->executeInTransaction('update_bom_template_item', function () use ($item, $data, $addonAttributes) {
            $item->update($this->coreItemAttributes($data));
            $this->applyItemAddonAttributes($item, $addonAttributes);

            return $item->fresh() ?? $item;
        }, ['template_id' => $template->id, 'item_id' => $item->id]);
    }

    public function deleteItem(BomTemplate $template, BomTemplateItem $item): void
    {
        $this->ensureItemBelongsToTemplate($template, $item);

        $this->executeInTransaction('delete_bom_template_item', function () use ($item) {
            $item->delete();
        }, ['template_id' => $template->id, 'item_id' => $item->id]);
    }

    /**
     * @param  list<int>  $itemIds
     */
    public function reorderItems(BomTemplate $template, array $itemIds): BomTemplate
    {
        return $this->executeInTransaction('reorder_bom_template_items', function () use ($template, $itemIds) {
            foreach ($itemIds as $index => $itemId) {
                BomTemplateItem::query()
                    ->where('id', $itemId)
                    ->where('template_id', $template->id)
                    ->update(['sort_order' => $index]);
            }

            return $template->fresh(['items']) ?? $template;
        }, ['template_id' => $template->id, 'items_count' => count($itemIds)]);
    }

    public function toggleActive(BomTemplate $template): BomTemplate
    {
        return $this->executeInTransaction('toggle_bom_template_active', function () use ($template) {
            $template->update(['is_active' => ! $template->is_active]);

            return $template->fresh() ?? $template;
        }, ['template_id' => $template->id]);
    }

    protected function ensureItemBelongsToTemplate(BomTemplate $template, BomTemplateItem $item): void
    {
        if ($item->template_id !== $template->id) {
            abort(404, 'Item tidak ditemukan dalam template ini.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function coreItemAttributes(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'type',
            'product_id',
            'description',
            'default_quantity',
            'unit',
            'is_required',
            'is_quantity_variable',
            'sort_order',
            'notes',
        ]));
    }
}
