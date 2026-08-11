<?php

declare(strict_types=1);

namespace App\Services\ElectricalPanel;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\ElectricalPanel\ComponentBrandMapping;
use App\Services\Base\BaseService;

/**
 * Service for cost optimization operations on BOMs.
 *
 * Finds cheapest alternatives across brands and applies optimizations.
 */
class CostOptimizationService extends BaseService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Preview cost optimization by finding cheapest option per item across all brands.
     *
     * @return array{current_total: float, optimized_total: float, savings: float, savings_percentage: float, items: array}
     */
    public function previewOptimization(Bom $bom): array
    {
        $currentTotal = 0;
        $optimizedTotal = 0;
        $items = [];

        foreach ($bom->materialItems as $item) {
            $currentCost = $item->unit_cost * $item->quantity;
            $currentTotal += $currentCost;

            $optimization = $this->findCheapestAlternative($item);

            $optimizedCost = $optimization['cheapest_unit_cost'] * $item->quantity;
            $optimizedTotal += $optimizedCost;

            $items[] = [
                'bom_item_id' => $item->id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'current_brand' => $optimization['current_brand'],
                'current_unit_cost' => $item->unit_cost,
                'current_total' => $currentCost,
                'cheapest_brand' => $optimization['cheapest_brand'],
                'cheapest_brand_label' => $optimization['cheapest_brand_label'],
                'cheapest_unit_cost' => $optimization['cheapest_unit_cost'],
                'cheapest_total' => $optimizedCost,
                'cheapest_product_id' => $optimization['cheapest_product_id'],
                'cheapest_product_name' => $optimization['cheapest_product_name'],
                'cheapest_sku' => $optimization['cheapest_sku'],
                'savings' => $currentCost - $optimizedCost,
                'savings_percentage' => $currentCost > 0 ? round((($currentCost - $optimizedCost) / $currentCost) * 100, 1) : 0,
                'can_optimize' => $optimization['can_optimize'],
                'is_already_cheapest' => $optimization['is_already_cheapest'],
            ];
        }

        $savings = $currentTotal - $optimizedTotal;
        $savingsPercentage = $currentTotal > 0 ? ($savings / $currentTotal) * 100 : 0;

        // Count stats
        $canOptimize = collect($items)->where('can_optimize', true)->where('is_already_cheapest', false)->count();
        $alreadyCheapest = collect($items)->where('is_already_cheapest', true)->count();
        $noAlternative = collect($items)->where('can_optimize', false)->count();

        return [
            'current_total' => $currentTotal,
            'optimized_total' => $optimizedTotal,
            'savings' => $savings,
            'savings_percentage' => round($savingsPercentage, 1),
            'summary' => [
                'total_items' => count($items),
                'can_optimize' => $canOptimize,
                'already_cheapest' => $alreadyCheapest,
                'no_alternative' => $noAlternative,
            ],
            'items' => $items,
        ];
    }

    /**
     * Find the cheapest alternative for a BOM item across all brands.
     *
     * @return array{can_optimize: bool, is_already_cheapest: bool, current_brand: string|null, cheapest_brand: string|null, cheapest_brand_label: string|null, cheapest_unit_cost: float, cheapest_product_id: int|null, cheapest_product_name: string|null, cheapest_sku: string|null}
     */
    public function findCheapestAlternative(BomItem $item): array
    {
        $result = [
            'can_optimize' => false,
            'is_already_cheapest' => false,
            'current_brand' => null,
            'cheapest_brand' => null,
            'cheapest_brand_label' => null,
            'cheapest_unit_cost' => $item->unit_cost,
            'cheapest_product_id' => null,
            'cheapest_product_name' => null,
            'cheapest_sku' => null,
        ];

        if (! $item->product_id) {
            return $result;
        }

        // Find component standard for this product
        $currentMapping = ComponentBrandMapping::query()
            ->where('product_id', $item->product_id)
            ->first();

        if (! $currentMapping) {
            return $result;
        }

        $result['current_brand'] = $currentMapping->brand;

        // Get all brand mappings for this component standard
        $allMappings = ComponentBrandMapping::query()
            ->with('product')
            ->where('component_standard_id', $currentMapping->component_standard_id)
            ->whereHas('product')
            ->get();

        if ($allMappings->isEmpty()) {
            return $result;
        }

        // Find the cheapest one
        $cheapest = $allMappings
            ->filter(fn ($m) => $m->product && $m->product->purchase_price > 0)
            ->sortBy(fn ($m) => $m->product->purchase_price)
            ->first();

        if (! $cheapest) {
            return $result;
        }

        $result['can_optimize'] = true;
        $result['cheapest_brand'] = $cheapest->brand;
        $result['cheapest_brand_label'] = ComponentBrandMapping::getBrands()[$cheapest->brand] ?? ucfirst($cheapest->brand);
        $result['cheapest_unit_cost'] = $cheapest->product->purchase_price;
        $result['cheapest_product_id'] = $cheapest->product->id;
        $result['cheapest_product_name'] = $cheapest->product->name;
        $result['cheapest_sku'] = $cheapest->brand_sku;

        // Check if current is already the cheapest
        if ($cheapest->product_id === $item->product_id) {
            $result['is_already_cheapest'] = true;
        }

        return $result;
    }

    /**
     * Apply cost optimization to create a new BOM with cheapest alternatives.
     *
     * @param  array<int>  $itemIds  BOM item IDs to optimize (empty = all)
     * @return array{bom: Bom, optimization_report: array}
     */
    public function applyOptimization(Bom $bom, array $itemIds = []): array
    {
        return $this->executeInTransaction('apply_optimization', function () use ($bom, $itemIds) {
            // Create new BOM variant
            $newBom = $bom->replicate(['bom_number', 'status', 'approved_by', 'approved_at']);
            $newBom->status = DocumentStatus::Draft;
            $newBom->name = $bom->name.' (Budget Optimized)';
            $newBom->variant_name = 'Budget Optimized';
            $newBom->variant_label = 'Mixed brands - lowest cost';
            $newBom->save();

            $report = [
                'total_items' => 0,
                'optimized' => 0,
                'kept_original' => 0,
                'total_savings' => 0,
                'items' => [],
            ];

            // Process material items
            foreach ($bom->materialItems as $item) {
                $report['total_items']++;
                $shouldOptimize = empty($itemIds) || in_array($item->id, $itemIds);

                $newItem = $item->replicate();
                $newItem->bom_id = $newBom->id;

                if ($shouldOptimize) {
                    $optimization = $this->findCheapestAlternative($item);

                    if ($optimization['can_optimize'] && ! $optimization['is_already_cheapest']) {
                        // Apply the optimization
                        $newItem->product_id = $optimization['cheapest_product_id'];
                        $newItem->description = $optimization['cheapest_product_name'];
                        $newItem->unit_cost = (int) $optimization['cheapest_unit_cost'];
                        $newItem->calculateTotalCost();

                        $savings = ($item->unit_cost - $optimization['cheapest_unit_cost']) * $item->quantity;
                        $report['total_savings'] += $savings;
                        $report['optimized']++;

                        $report['items'][] = [
                            'status' => 'optimized',
                            'original' => [
                                'description' => $item->description,
                                'brand' => $optimization['current_brand'],
                                'unit_cost' => $item->unit_cost,
                            ],
                            'new' => [
                                'description' => $optimization['cheapest_product_name'],
                                'brand' => $optimization['cheapest_brand'],
                                'brand_label' => $optimization['cheapest_brand_label'],
                                'unit_cost' => $optimization['cheapest_unit_cost'],
                                'sku' => $optimization['cheapest_sku'],
                            ],
                            'savings' => $savings,
                        ];
                    } else {
                        $report['kept_original']++;
                        $report['items'][] = [
                            'status' => $optimization['is_already_cheapest'] ? 'already_cheapest' : 'no_alternative',
                            'original' => [
                                'description' => $item->description,
                                'brand' => $optimization['current_brand'],
                                'unit_cost' => $item->unit_cost,
                            ],
                            'new' => null,
                            'savings' => 0,
                        ];
                    }
                } else {
                    $report['kept_original']++;
                    $report['items'][] = [
                        'status' => 'excluded',
                        'original' => [
                            'description' => $item->description,
                            'unit_cost' => $item->unit_cost,
                        ],
                        'new' => null,
                        'savings' => 0,
                    ];
                }

                $newItem->save();
            }

            // Copy labor and overhead items unchanged
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

            // Recalculate costs
            $newBom->calculateTotals();
            $newBom->save();

            return [
                'bom' => $newBom->fresh(['items.product', 'product']),
                'optimization_report' => $report,
            ];
        }, ['source_bom_id' => $bom->id, 'items_count' => count($itemIds)]);
    }
}
