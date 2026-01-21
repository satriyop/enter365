<?php

declare(strict_types=1);

namespace App\Services\Manufacturing\BrandSwap;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Enums\DocumentStatus;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\BomVariantGroup;
use App\Models\Manufacturing\ComponentBrandMapping;
use App\Services\Base\AbstractApplicationService;
use App\Services\Manufacturing\BomVariantGroupService;
use App\Services\Manufacturing\ProductEquivalenceService;
use Illuminate\Support\Collection;

/**
 * Service for executing brand swap operations.
 *
 * Handles write operations: executing swaps, generating variants,
 * and quick-swapping individual items.
 */
class BrandSwapExecutionService extends AbstractApplicationService
{
    public function __construct(
        private ProductEquivalenceService $equivalenceService,
        private BomVariantGroupService $variantGroupService,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Swap all components in a BOM to a different brand.
     *
     * @return array{bom: Bom, swap_report: array<string, mixed>}
     */
    public function swapBomBrand(
        Bom $bom,
        string $targetBrand,
        bool $createVariant = true,
        ?BomVariantGroup $variantGroup = null
    ): array {
        return $this->executeInTransaction('swap_bom_brand', function () use ($bom, $targetBrand, $createVariant, $variantGroup) {
            $swapReport = [
                'total_items' => 0,
                'swapped' => 0,
                'no_mapping' => 0,
                'partial_match' => 0,
                'kept_original' => 0,
                'items' => [],
            ];

            // Duplicate the BOM if creating variant
            if ($createVariant) {
                $newBom = $bom->replicate(['bom_number', 'status', 'approved_by', 'approved_at']);
                $newBom->bom_number = Bom::generateBomNumber();
                $newBom->status = DocumentStatus::Draft;
                $newBom->name = $bom->name.' ('.ucfirst($targetBrand).')';
                $newBom->variant_name = ucfirst($targetBrand);
                $newBom->variant_label = 'Brand: '.ucfirst($targetBrand);
                $newBom->save();
            } else {
                $newBom = $bom;
            }

            // Process each material item
            foreach ($bom->materialItems as $item) {
                $swapReport['total_items']++;
                $itemReport = $this->swapItem($item, $newBom, $targetBrand, $createVariant);
                $swapReport['items'][] = $itemReport;

                match ($itemReport['status']) {
                    'swapped' => $swapReport['swapped']++,
                    'no_mapping' => $swapReport['no_mapping']++,
                    'partial_match' => $swapReport['partial_match']++,
                    default => $swapReport['kept_original']++,
                };
            }

            // Copy labor and overhead items unchanged
            if ($createVariant) {
                foreach ($bom->laborItems as $item) {
                    $newItem = $item->replicate();
                    $newItem->bom_id = $newBom->id;
                    $newItem->save();
                }

                foreach ($bom->overheadItems as $item) {
                    $newItem = $item->replicate();
                    $newItem->bom_id = $newBom->id;
                    $newItem->save();
                }
            }

            // Recalculate costs
            $newBom->calculateTotals();
            $newBom->save();

            // Add to variant group if specified
            if ($variantGroup) {
                $this->variantGroupService->addBom($variantGroup, $newBom, [
                    'variant_name' => ucfirst($targetBrand),
                    'variant_label' => 'Brand: '.ucfirst($targetBrand),
                ]);
            }

            return [
                'bom' => $newBom->fresh(['items.product', 'product']),
                'swap_report' => $swapReport,
            ];
        }, ['bom_id' => $bom->id, 'target_brand' => $targetBrand]);
    }

    /**
     * Generate all brand variants for a BOM at once.
     *
     * @param  array<string>  $brands
     * @return array{variant_group: BomVariantGroup, boms: Collection<int, Bom>, report: array<string, mixed>}
     */
    public function generateBrandVariants(
        Bom $bom,
        array $brands,
        ?string $groupName = null
    ): array {
        return $this->executeInTransaction('generate_brand_variants', function () use ($bom, $brands, $groupName) {
            // Create variant group
            $groupName ??= "{$bom->product->name} - Brand Variants";
            $group = BomVariantGroup::create([
                'product_id' => $bom->product_id,
                'name' => $groupName,
                'description' => 'Auto-generated brand variants',
                'status' => DocumentStatus::Draft,
                'created_by' => $this->getUserId(),
            ]);

            // Add original BOM to group
            $this->variantGroupService->addBom($group, $bom, [
                'variant_name' => 'Original',
                'is_primary_variant' => true,
            ]);

            $generatedBoms = collect();
            $reports = [];

            foreach ($brands as $brand) {
                $result = $this->swapBomBrand($bom, $brand, true, $group);
                $generatedBoms->push($result['bom']);
                $reports[$brand] = $result['swap_report'];
            }

            return [
                'variant_group' => $group->fresh(['boms', 'product']),
                'boms' => $generatedBoms,
                'report' => $reports,
            ];
        }, ['bom_id' => $bom->id, 'brands_count' => count($brands)]);
    }

    /**
     * Quick swap a BOM item to a different product (in-place edit).
     *
     * @return array{success: bool, item: BomItem, previous: array, savings: float}
     */
    public function quickSwapItem(BomItem $item, Product $newProduct, ?string $reason = null): array
    {
        return $this->executeInTransaction('quick_swap_item', function () use ($item, $newProduct, $reason) {
            // Store previous state for audit
            $previous = [
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name ?? $item->description,
                'product_sku' => $item->product?->sku,
                'unit_cost' => $item->unit_cost,
            ];

            $oldCost = $item->unit_cost;
            $newCost = $newProduct->purchase_price;

            // Update the item
            $item->product_id = $newProduct->id;
            $item->description = $newProduct->name;
            $item->unit_cost = $newCost;

            // Find component standard from the new product's mapping
            $newMapping = ComponentBrandMapping::query()
                ->where('product_id', $newProduct->id)
                ->first();

            if ($newMapping) {
                $item->component_standard_id = $newMapping->component_standard_id;
            }

            $item->calculateTotalCost();
            $item->save();

            // Recalculate BOM totals
            $item->bom->calculateTotals();
            $item->bom->save();

            $savings = ($oldCost - $newCost) * $item->quantity;

            return [
                'success' => true,
                'item' => $item->fresh(['product']),
                'previous' => $previous,
                'new' => [
                    'product_id' => $newProduct->id,
                    'product_name' => $newProduct->name,
                    'product_sku' => $newProduct->sku,
                    'unit_cost' => $newCost,
                ],
                'savings' => $savings,
                'reason' => $reason,
            ];
        }, ['bom_item_id' => $item->id, 'new_product_id' => $newProduct->id]);
    }

    /**
     * Swap a single BOM item to target brand.
     *
     * @return array{status: string, original: array<string, mixed>, new: array<string, mixed>|null, notes: string|null}
     */
    public function swapItem(
        BomItem $item,
        Bom $newBom,
        string $targetBrand,
        bool $isNew
    ): array {
        $result = [
            'status' => 'no_change',
            'original' => [
                'product_id' => $item->product_id,
                'description' => $item->description,
                'unit_cost' => $item->unit_cost,
            ],
            'new' => null,
            'notes' => null,
        ];

        if (! $item->product_id) {
            // Non-product items (manual entries) - just copy
            if ($isNew) {
                $newItem = $item->replicate();
                $newItem->bom_id = $newBom->id;
                $newItem->save();
            }
            $result['status'] = 'no_product';

            return $result;
        }

        // Find component standard for this product
        $mapping = ComponentBrandMapping::query()
            ->where('product_id', $item->product_id)
            ->first();

        if (! $mapping) {
            // No cross-reference mapping exists
            if ($isNew) {
                $newItem = $item->replicate();
                $newItem->bom_id = $newBom->id;
                $newItem->save();
            }
            $result['status'] = 'no_mapping';
            $result['notes'] = 'No component standard mapping found';

            return $result;
        }

        // Find equivalent in target brand
        $targetMapping = ComponentBrandMapping::query()
            ->where('component_standard_id', $mapping->component_standard_id)
            ->where('brand', $targetBrand)
            ->where('is_preferred', true)
            ->first()
            ?? ComponentBrandMapping::query()
                ->where('component_standard_id', $mapping->component_standard_id)
                ->where('brand', $targetBrand)
                ->first();

        if (! $targetMapping) {
            // Try partial match
            $partialMatches = $this->equivalenceService->findPartialMatches(
                $mapping->componentStandard,
                $targetBrand,
                70
            );

            if ($partialMatches->isNotEmpty()) {
                $targetMapping = $partialMatches->first()['mapping'];
                $result['status'] = 'partial_match';
                $result['notes'] = 'Using partial match (score: '.
                    $partialMatches->first()['match_score'].'%)';
            } else {
                // No equivalent found - keep original
                if ($isNew) {
                    $newItem = $item->replicate();
                    $newItem->bom_id = $newBom->id;
                    $newItem->save();
                }
                $result['status'] = 'no_mapping';
                $result['notes'] = "No equivalent found for brand: {$targetBrand}";

                return $result;
            }
        } else {
            $result['status'] = 'swapped';
        }

        // Create new item with target brand product
        $newProduct = $targetMapping->product;

        if ($isNew) {
            $newItem = $item->replicate();
            $newItem->bom_id = $newBom->id;
        } else {
            $newItem = $item;
        }

        $newItem->product_id = $newProduct->id;
        $newItem->component_standard_id = $mapping->component_standard_id;
        $newItem->description = $newProduct->name;
        $newItem->unit_cost = $newProduct->purchase_price;
        $newItem->calculateTotalCost();
        $newItem->save();

        $result['new'] = [
            'product_id' => $newProduct->id,
            'description' => $newProduct->name,
            'unit_cost' => $newProduct->purchase_price,
            'brand' => $targetBrand,
            'brand_sku' => $targetMapping->brand_sku,
        ];

        return $result;
    }
}
