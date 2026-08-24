<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\DocumentStatus;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\MrpDemand;
use App\Models\Manufacturing\MrpRun;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use Illuminate\Support\Collection;

/**
 * Service for MRP demand collection and supply calculation.
 *
 * Handles collecting demands from work orders, calculating supply/availability,
 * and exploding BOMs to create component-level demands.
 */
class MrpDemandService
{
    /**
     * Collect demands from confirmed/in-progress work orders.
     */
    public function collectDemands(MrpRun $run): void
    {
        // Clear existing demands
        $run->demands()->delete();

        // Get work orders within the planning horizon
        $workOrders = WorkOrder::query()
            ->whereIn('status', [DocumentStatus::Confirmed, DocumentStatus::InProgress])
            ->where(function ($q) use ($run) {
                $q->whereBetween('planned_end_date', [$run->planning_horizon_start->toDateString(), $run->planning_horizon_end->toDateString().' 23:59:59'])
                    ->orWhereNull('planned_end_date');
            })
            ->when($run->warehouse_id, fn ($q) => $q->where('warehouse_id', $run->warehouse_id))
            ->with(['items.product'])
            ->get();

        foreach ($workOrders as $wo) {
            foreach ($wo->materialItems as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $requiredDate = $wo->planned_end_date ?? now()->addWeeks(2);
                $remainingQty = $item->getRemainingQuantity();

                if ($remainingQty <= 0) {
                    continue;
                }

                MrpDemand::create([
                    'mrp_run_id' => $run->id,
                    'product_id' => $item->product_id,
                    'demand_source_type' => WorkOrder::class,
                    'demand_source_id' => $wo->id,
                    'demand_source_number' => $wo->wo_number,
                    'required_date' => $requiredDate,
                    'week_bucket' => MrpDemand::calculateWeekBucket($requiredDate),
                    'quantity_required' => $remainingQty,
                    'warehouse_id' => $wo->warehouse_id ?? $run->warehouse_id,
                    'bom_level' => 0,
                ]);
            }
        }
    }

    /**
     * Calculate supply (on-hand, on-order, reserved) for each demand.
     */
    public function calculateSupply(MrpRun $run): void
    {
        $demands = $run->demands()->get();

        $stocksByProduct = ProductStock::query()
            ->whereIn('product_id', $demands->pluck('product_id')->unique()->filter())
            ->get()
            ->groupBy('product_id');

        // Group by product and warehouse
        $grouped = $demands->groupBy(fn ($d) => $d->product_id.'-'.($d->warehouse_id ?? 'all'));

        foreach ($grouped as $key => $productDemands) {
            $firstDemand = $productDemands->first();
            $productId = $firstDemand->product_id;
            $warehouseId = $firstDemand->warehouse_id;

            $productStocks = $stocksByProduct->get($productId, collect());
            $stock = $warehouseId
                ? $productStocks->firstWhere('warehouse_id', $warehouseId)
                : $productStocks->first();
            $onHand = $stock ? (float) $stock->quantity : 0;
            $reserved = $stock ? (float) $stock->reserved_quantity : 0;

            // Get on-order from approved POs
            $onOrder = $this->getOnOrderQuantity($productId, $warehouseId);

            // Calculate available for each demand chronologically
            $runningAvailable = $onHand + $onOrder - $reserved;

            foreach ($productDemands->sortBy('required_date') as $demand) {
                $demand->quantity_on_hand = $onHand;
                $demand->quantity_on_order = $onOrder;
                $demand->quantity_reserved = $reserved;
                $demand->quantity_available = max(0, $runningAvailable);
                $demand->quantity_short = max(0, (float) $demand->quantity_required - $runningAvailable);

                $demand->save();

                // Reduce running available for next demand
                $runningAvailable = $runningAvailable - (float) $demand->quantity_required;
            }
        }
    }

    /**
     * Explode BOM for products that need to be manufactured.
     */
    public function explodeBomDemands(MrpRun $run): void
    {
        $demandsToExplode = $run->demands()
            ->where('quantity_short', '>', 0)
            ->with(['product'])
            ->get();

        $makeProductIds = $demandsToExplode
            ->filter(fn (MrpDemand $demand) => $demand->product?->procurement_type === 'make')
            ->pluck('product_id')
            ->unique()
            ->values();

        $boms = Bom::query()
            ->whereIn('product_id', $makeProductIds)
            ->where('status', DocumentStatus::Active)
            ->with(['materialItems.product'])
            ->get()
            ->keyBy('product_id');

        foreach ($demandsToExplode as $demand) {
            $product = $demand->product;
            if (! $product || $product->procurement_type !== 'make') {
                continue;
            }

            $bom = $boms->get($product->id);

            if (! $bom) {
                continue;
            }

            // Calculate how many we need to make
            $qtyToMake = (float) $demand->quantity_short;
            $multiplier = $qtyToMake / (float) $bom->output_quantity;

            foreach ($bom->materialItems as $bomItem) {
                if (! $bomItem->product_id) {
                    continue;
                }

                $componentQty = $bomItem->getEffectiveQuantity() * $multiplier;

                // Get lead time for component
                $componentProduct = $bomItem->product;
                $leadTime = $componentProduct->lead_time_days ?? 0;
                $requiredDate = $demand->required_date->copy()->subDays($leadTime);

                // Create child demand
                $childDemand = MrpDemand::create([
                    'mrp_run_id' => $run->id,
                    'product_id' => $bomItem->product_id,
                    'demand_source_type' => WorkOrder::class,
                    'demand_source_id' => $demand->demand_source_id,
                    'demand_source_number' => $demand->demand_source_number,
                    'required_date' => $requiredDate,
                    'week_bucket' => MrpDemand::calculateWeekBucket($requiredDate),
                    'quantity_required' => $componentQty,
                    'warehouse_id' => $demand->warehouse_id,
                    'bom_level' => $demand->bom_level + 1,
                ]);

                // Calculate supply for this new demand
                $this->calculateSupplyForDemand($childDemand);
            }
        }
    }

    /**
     * Get shortage report without saving (quick analysis).
     *
     * @return array{horizon_start: string, horizon_end: string, warehouse_id: int|null, total_shortages: int, shortages: array}
     */
    public function getShortageReport(
        string $horizonStart,
        string $horizonEnd,
        ?int $warehouseId = null
    ): array {
        $shortages = [];

        // Get work orders
        $workOrders = WorkOrder::query()
            ->whereIn('status', [DocumentStatus::Confirmed, DocumentStatus::InProgress])
            ->whereBetween('planned_end_date', [$horizonStart, $horizonEnd.' 23:59:59'])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->with(['items.product'])
            ->get();

        // Collect demands
        $demands = collect();
        foreach ($workOrders as $wo) {
            foreach ($wo->materialItems as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $remainingQty = $item->getRemainingQuantity();
                if ($remainingQty <= 0) {
                    continue;
                }

                $demands->push([
                    'product_id' => $item->product_id,
                    'quantity' => $remainingQty,
                    'warehouse_id' => $wo->warehouse_id ?? $warehouseId,
                    'required_date' => $wo->planned_end_date,
                    'source' => $wo->wo_number,
                ]);
            }
        }

        // Calculate shortages by product
        $grouped = $demands->groupBy('product_id');
        $products = Product::query()
            ->whereIn('id', $grouped->keys())
            ->get()
            ->keyBy('id');

        foreach ($grouped as $productId => $productDemands) {
            $product = $products->get($productId);
            if (! $product) {
                continue;
            }

            $totalRequired = $productDemands->sum('quantity');
            $demandWarehouseId = $productDemands->first()['warehouse_id'];

            // Get current supply
            $stockQuery = ProductStock::where('product_id', $productId);
            if ($demandWarehouseId) {
                $stockQuery->where('warehouse_id', $demandWarehouseId);
            }
            $stock = $stockQuery->first();

            $onHand = $stock ? (float) $stock->quantity : 0;
            $reserved = $stock ? (float) $stock->reserved_quantity : 0;
            $onOrder = $this->getOnOrderQuantity($productId, $demandWarehouseId);

            $available = $onHand + $onOrder - $reserved;
            $shortage = max(0, $totalRequired - $available);

            if ($shortage > 0) {
                $shortages[] = [
                    'product_id' => $productId,
                    'product_code' => $product->sku,
                    'product_name' => $product->name,
                    'procurement_type' => $product->procurement_type,
                    'quantity_required' => $totalRequired,
                    'quantity_on_hand' => $onHand,
                    'quantity_on_order' => $onOrder,
                    'quantity_reserved' => $reserved,
                    'quantity_available' => $available,
                    'quantity_short' => $shortage,
                    'earliest_required' => $productDemands->min('required_date'),
                    'work_orders' => $productDemands->pluck('source')->unique()->values()->all(),
                ];
            }
        }

        return [
            'horizon_start' => $horizonStart,
            'horizon_end' => $horizonEnd,
            'warehouse_id' => $warehouseId,
            'total_shortages' => count($shortages),
            'shortages' => $shortages,
        ];
    }

    /**
     * Calculate supply for a single demand.
     */
    public function calculateSupplyForDemand(MrpDemand $demand): void
    {
        $productId = $demand->product_id;
        $warehouseId = $demand->warehouse_id;

        $stockQuery = ProductStock::where('product_id', $productId);
        if ($warehouseId) {
            $stockQuery->where('warehouse_id', $warehouseId);
        }

        $stock = $stockQuery->first();
        $onHand = $stock ? (float) $stock->quantity : 0;
        $reserved = $stock ? (float) $stock->reserved_quantity : 0;
        $onOrder = $this->getOnOrderQuantity($productId, $warehouseId);

        $available = max(0, $onHand + $onOrder - $reserved);
        $shortage = max(0, (float) $demand->quantity_required - $available);

        $demand->quantity_on_hand = $onHand;
        $demand->quantity_on_order = $onOrder;
        $demand->quantity_reserved = $reserved;
        $demand->quantity_available = $available;
        $demand->quantity_short = $shortage;
        $demand->save();
    }

    /**
     * Get on-order quantity from approved POs.
     */
    public function getOnOrderQuantity(int $productId, ?int $warehouseId): float
    {
        return (float) PurchaseOrderItem::query()
            ->whereHas('purchaseOrder', function ($q) {
                $q->whereIn('status', [
                    DocumentStatus::Approved,
                    DocumentStatus::Partial,
                ]);
            })
            ->where('product_id', $productId)
            ->selectRaw('SUM(quantity - quantity_received) as pending')
            ->value('pending') ?? 0;
    }
}
