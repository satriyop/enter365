<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Bom;
use App\Models\Accounting\MrpDemand;
use App\Models\Accounting\MrpRun;
use App\Models\Accounting\MrpSuggestion;
use App\Models\Accounting\Product;
use App\Models\Accounting\ProductStock;
use App\Models\Accounting\PurchaseOrder;
use App\Models\Accounting\PurchaseOrderItem;
use App\Models\Accounting\SubcontractorWorkOrder;
use App\Models\Accounting\Warehouse;
use App\Models\Accounting\WorkOrder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MrpService
{
    /**
     * Create a new MRP run.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MrpRun
    {
        return DB::transaction(function () use ($data) {
            $run = new MrpRun($data);
            $run->run_number = MrpRun::generateRunNumber();
            $run->status = MrpRun::STATUS_DRAFT;
            $run->created_by = $data['created_by'] ?? auth()->id();
            $run->save();

            return $run->fresh(['warehouse']);
        });
    }

    /**
     * Execute MRP run - collect demands and generate suggestions.
     */
    public function execute(MrpRun $run, ?int $userId = null): MrpRun
    {
        if ($run->status !== MrpRun::STATUS_DRAFT) {
            throw new InvalidArgumentException('Hanya MRP run dalam status draft yang dapat dijalankan.');
        }

        return DB::transaction(function () use ($run) {
            $run->status = MrpRun::STATUS_PROCESSING;
            $run->save();

            try {
                // Step 1: Collect demands from work orders
                $this->collectDemands($run);

                // Step 2: Calculate supply for each demand
                $this->calculateSupply($run);

                // Step 3: Explode BOM for products that need to be made
                $this->explodeBomDemands($run);

                // Step 4: Generate suggestions for shortages
                $this->generateSuggestions($run);

                // Step 5: Update summary counts
                $run->updateSummaryCounts();

                $run->status = MrpRun::STATUS_COMPLETED;
                $run->completed_at = now();
                $run->save();

                return $run->fresh(['demands', 'suggestions', 'warehouse']);
            } catch (\Exception $e) {
                $run->status = MrpRun::STATUS_DRAFT;
                $run->save();
                throw $e;
            }
        });
    }

    /**
     * Update an MRP run (only draft).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(MrpRun $run, array $data): MrpRun
    {
        if ($run->status !== MrpRun::STATUS_DRAFT) {
            throw new InvalidArgumentException('Hanya MRP run dalam status draft yang dapat diubah.');
        }

        $run->fill($data);
        $run->save();

        return $run->fresh(['warehouse']);
    }

    /**
     * Delete an MRP run.
     */
    public function delete(MrpRun $run): bool
    {
        if (! $run->canBeDeleted()) {
            throw new InvalidArgumentException('MRP run tidak dapat dihapus.');
        }

        return DB::transaction(function () use ($run) {
            $run->demands()->delete();
            $run->suggestions()->delete();

            return $run->delete();
        });
    }

    /**
     * Collect demands from confirmed/in-progress work orders.
     */
    public function collectDemands(MrpRun $run): void
    {
        // Clear existing demands
        $run->demands()->delete();

        // Get work orders within the planning horizon
        $workOrders = WorkOrder::query()
            ->whereIn('status', [WorkOrder::STATUS_CONFIRMED, WorkOrder::STATUS_IN_PROGRESS])
            ->where(function ($q) use ($run) {
                $q->whereBetween('planned_end_date', [$run->planning_horizon_start, $run->planning_horizon_end])
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

        // Group by product and warehouse
        $grouped = $demands->groupBy(fn ($d) => $d->product_id.'-'.($d->warehouse_id ?? 'all'));

        foreach ($grouped as $key => $productDemands) {
            $firstDemand = $productDemands->first();
            $productId = $firstDemand->product_id;
            $warehouseId = $firstDemand->warehouse_id;

            // Get current stock
            $stockQuery = ProductStock::where('product_id', $productId);
            if ($warehouseId) {
                $stockQuery->where('warehouse_id', $warehouseId);
            }

            $stock = $stockQuery->first();
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

        foreach ($demandsToExplode as $demand) {
            $product = $demand->product;
            if (! $product) {
                continue;
            }

            // Only explode if product is manufactured (make) or has a BOM
            if ($product->procurement_type !== 'make') {
                continue;
            }

            $bom = Bom::where('product_id', $product->id)
                ->where('status', Bom::STATUS_ACTIVE)
                ->first();

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
                $leadTime = $componentProduct?->lead_time_days ?? 0;
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
     * Generate suggestions for shortages.
     */
    public function generateSuggestions(MrpRun $run): void
    {
        // Clear existing suggestions
        $run->suggestions()->delete();

        $shortages = $run->demands()
            ->where('quantity_short', '>', 0)
            ->with(['product'])
            ->get();

        // Group by product to consolidate suggestions
        $groupedShortages = $shortages->groupBy('product_id');

        foreach ($groupedShortages as $productId => $productShortages) {
            $product = $productShortages->first()->product;
            if (! $product) {
                continue;
            }

            // Sum up all shortages for this product
            $totalShort = $productShortages->sum('quantity_short');
            $earliestDue = $productShortages->min('required_date');

            // Apply MOQ and order multiple
            $suggestedQty = $this->applyOrderConstraints($product, $totalShort);

            // Determine suggestion type based on procurement type
            $suggestionType = $this->getSuggestionType($product);

            // Calculate order date based on lead time
            $leadTimeDays = $product->lead_time_days ?? 0;
            $orderDate = $earliestDue->copy()->subDays($leadTimeDays);

            // Determine priority
            $priority = $this->calculatePriority($orderDate);

            MrpSuggestion::create([
                'mrp_run_id' => $run->id,
                'product_id' => $productId,
                'suggestion_type' => $suggestionType,
                'action' => MrpSuggestion::ACTION_CREATE,
                'suggested_order_date' => $orderDate,
                'suggested_due_date' => $earliestDue,
                'quantity_required' => $totalShort,
                'suggested_quantity' => $suggestedQty,
                'suggested_supplier_id' => $product->default_supplier_id,
                'suggested_warehouse_id' => $run->warehouse_id,
                'estimated_unit_cost' => $product->purchase_price ?? 0,
                'estimated_total_cost' => (int) round($suggestedQty * ($product->purchase_price ?? 0)),
                'priority' => $priority,
                'status' => MrpSuggestion::STATUS_PENDING,
                'reason' => $this->generateReason($product, $totalShort, $productShortages->count()),
            ]);
        }
    }

    /**
     * Accept a suggestion.
     */
    public function acceptSuggestion(MrpSuggestion $suggestion): MrpSuggestion
    {
        $suggestion->accept();

        return $suggestion->fresh();
    }

    /**
     * Reject a suggestion.
     */
    public function rejectSuggestion(MrpSuggestion $suggestion, ?string $reason = null): MrpSuggestion
    {
        $suggestion->reject($reason);

        return $suggestion->fresh();
    }

    /**
     * Update suggestion quantity.
     */
    public function updateSuggestionQuantity(MrpSuggestion $suggestion, float $quantity): MrpSuggestion
    {
        if (! $suggestion->isPending() && ! $suggestion->isAccepted()) {
            throw new InvalidArgumentException('Hanya saran pending atau diterima yang dapat diubah kuantitasnya.');
        }

        $suggestion->adjusted_quantity = $quantity;
        $suggestion->calculateEstimatedCosts();
        $suggestion->save();

        return $suggestion->fresh();
    }

    /**
     * Convert suggestion to Purchase Order.
     */
    public function convertToPurchaseOrder(MrpSuggestion $suggestion, ?int $userId = null): PurchaseOrder
    {
        if (! $suggestion->canBeConverted()) {
            throw new InvalidArgumentException('Saran harus diterima terlebih dahulu sebelum dikonversi.');
        }

        if ($suggestion->suggestion_type !== MrpSuggestion::TYPE_PURCHASE) {
            throw new InvalidArgumentException('Hanya saran pembelian yang dapat dikonversi ke PO.');
        }

        return DB::transaction(function () use ($suggestion, $userId) {
            $product = $suggestion->product;
            $quantity = $suggestion->getEffectiveQuantity();

            // Create PO
            $po = PurchaseOrder::create([
                'po_number' => PurchaseOrder::generatePoNumber(),
                'contact_id' => $suggestion->suggested_supplier_id,
                'po_date' => now(),
                'expected_date' => $suggestion->suggested_due_date,
                'reference' => 'MRP: '.$suggestion->mrpRun->run_number,
                'subject' => 'PO dari MRP untuk '.$product->name,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'currency' => 'IDR',
                'exchange_rate' => 1,
                'tax_rate' => config('accounting.tax.default_rate', 11.00),
                'subtotal' => $suggestion->estimated_total_cost,
                'tax_amount' => 0,
                'total' => $suggestion->estimated_total_cost,
                'base_currency_total' => $suggestion->estimated_total_cost,
                'created_by' => $userId ?? auth()->id(),
            ]);

            // Create PO item
            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => $quantity,
                'unit' => $product->unit ?? 'unit',
                'unit_price' => $suggestion->estimated_unit_cost ?? 0,
                'line_total' => $suggestion->estimated_total_cost ?? 0,
            ]);

            // Recalculate PO totals
            $po->calculateTotals();
            $po->save();

            // Mark suggestion as converted
            $suggestion->markAsConverted(PurchaseOrder::class, $po->id, $userId);

            return $po->fresh(['items', 'contact']);
        });
    }

    /**
     * Convert suggestion to Work Order.
     */
    public function convertToWorkOrder(MrpSuggestion $suggestion, ?int $userId = null): WorkOrder
    {
        if (! $suggestion->canBeConverted()) {
            throw new InvalidArgumentException('Saran harus diterima terlebih dahulu sebelum dikonversi.');
        }

        if ($suggestion->suggestion_type !== MrpSuggestion::TYPE_WORK_ORDER) {
            throw new InvalidArgumentException('Hanya saran work order yang dapat dikonversi ke WO.');
        }

        return DB::transaction(function () use ($suggestion, $userId) {
            $product = $suggestion->product;
            $quantity = $suggestion->getEffectiveQuantity();

            // Find BOM for product
            $bom = Bom::where('product_id', $product->id)
                ->where('status', Bom::STATUS_ACTIVE)
                ->first();

            $woService = app(WorkOrderService::class);

            if ($bom) {
                $wo = $woService->createFromBom($bom, [
                    'quantity' => $quantity,
                    'warehouse_id' => $suggestion->suggested_warehouse_id,
                    'planned_start_date' => $suggestion->suggested_order_date,
                    'planned_end_date' => $suggestion->suggested_due_date,
                    'notes' => 'Dibuat dari MRP: '.$suggestion->mrpRun->run_number,
                    'created_by' => $userId ?? auth()->id(),
                ]);
            } else {
                $wo = $woService->create([
                    'product_id' => $product->id,
                    'type' => WorkOrder::TYPE_PRODUCTION,
                    'name' => 'Produksi '.$product->name,
                    'quantity_ordered' => $quantity,
                    'warehouse_id' => $suggestion->suggested_warehouse_id,
                    'planned_start_date' => $suggestion->suggested_order_date,
                    'planned_end_date' => $suggestion->suggested_due_date,
                    'notes' => 'Dibuat dari MRP: '.$suggestion->mrpRun->run_number,
                    'created_by' => $userId ?? auth()->id(),
                ]);
            }

            // Mark suggestion as converted
            $suggestion->markAsConverted(WorkOrder::class, $wo->id, $userId);

            return $wo;
        });
    }

    /**
     * Convert suggestion to Subcontractor Work Order.
     */
    public function convertToSubcontractorWorkOrder(
        MrpSuggestion $suggestion,
        int $subcontractorId,
        ?int $userId = null
    ): SubcontractorWorkOrder {
        if (! $suggestion->canBeConverted()) {
            throw new InvalidArgumentException('Saran harus diterima terlebih dahulu sebelum dikonversi.');
        }

        if ($suggestion->suggestion_type !== MrpSuggestion::TYPE_SUBCONTRACT) {
            throw new InvalidArgumentException('Hanya saran subkontrak yang dapat dikonversi ke SC WO.');
        }

        return DB::transaction(function () use ($suggestion, $subcontractorId, $userId) {
            $product = $suggestion->product;
            $quantity = $suggestion->getEffectiveQuantity();

            $scWo = SubcontractorWorkOrder::create([
                'sc_wo_number' => SubcontractorWorkOrder::generateScWoNumber(),
                'subcontractor_id' => $subcontractorId,
                'name' => 'Subkontrak '.$product->name,
                'description' => "Produksi {$quantity} {$product->unit} {$product->name}",
                'scope_of_work' => 'Dibuat dari MRP: '.$suggestion->mrpRun->run_number,
                'status' => SubcontractorWorkOrder::STATUS_DRAFT,
                'agreed_amount' => $suggestion->estimated_total_cost ?? 0,
                'scheduled_start_date' => $suggestion->suggested_order_date,
                'scheduled_end_date' => $suggestion->suggested_due_date,
                'created_by' => $userId ?? auth()->id(),
            ]);

            // Mark suggestion as converted
            $suggestion->markAsConverted(SubcontractorWorkOrder::class, $scWo->id, $userId);

            return $scWo->fresh(['subcontractor']);
        });
    }

    /**
     * Bulk accept suggestions.
     *
     * @param  array<int>  $suggestionIds
     */
    public function bulkAccept(array $suggestionIds): int
    {
        $count = 0;

        foreach ($suggestionIds as $id) {
            $suggestion = MrpSuggestion::find($id);
            if ($suggestion && $suggestion->canBeAccepted()) {
                $suggestion->accept();
                $count++;
            }
        }

        return $count;
    }

    /**
     * Bulk reject suggestions.
     *
     * @param  array<int>  $suggestionIds
     */
    public function bulkReject(array $suggestionIds, ?string $reason = null): int
    {
        $count = 0;

        foreach ($suggestionIds as $id) {
            $suggestion = MrpSuggestion::find($id);
            if ($suggestion && $suggestion->canBeRejected()) {
                $suggestion->reject($reason);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get shortage report without saving (quick analysis).
     *
     * @return array<string, mixed>
     */
    public function getShortageReport(
        \DateTimeInterface $horizonStart,
        \DateTimeInterface $horizonEnd,
        ?int $warehouseId = null
    ): array {
        $shortages = [];

        // Get work orders
        $workOrders = WorkOrder::query()
            ->whereIn('status', [WorkOrder::STATUS_CONFIRMED, WorkOrder::STATUS_IN_PROGRESS])
            ->whereBetween('planned_end_date', [$horizonStart, $horizonEnd])
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

        foreach ($grouped as $productId => $productDemands) {
            $product = Product::find($productId);
            if (! $product) {
                continue;
            }

            $totalRequired = $productDemands->sum('quantity');
            $warehouseId = $productDemands->first()['warehouse_id'];

            // Get current supply
            $stockQuery = ProductStock::where('product_id', $productId);
            if ($warehouseId) {
                $stockQuery->where('warehouse_id', $warehouseId);
            }
            $stock = $stockQuery->first();

            $onHand = $stock ? (float) $stock->quantity : 0;
            $reserved = $stock ? (float) $stock->reserved_quantity : 0;
            $onOrder = $this->getOnOrderQuantity($productId, $warehouseId);

            $available = $onHand + $onOrder - $reserved;
            $shortage = max(0, $totalRequired - $available);

            if ($shortage > 0) {
                $shortages[] = [
                    'product_id' => $productId,
                    'product_code' => $product->code,
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
            'horizon_start' => $horizonStart->format('Y-m-d'),
            'horizon_end' => $horizonEnd->format('Y-m-d'),
            'warehouse_id' => $warehouseId,
            'total_shortages' => count($shortages),
            'shortages' => $shortages,
        ];
    }

    /**
     * Get MRP run statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $total = MrpRun::count();
        $byStatus = [];

        foreach (MrpRun::getStatuses() as $status => $label) {
            $byStatus[$status] = MrpRun::where('status', $status)->count();
        }

        $lastRun = MrpRun::where('status', MrpRun::STATUS_COMPLETED)
            ->orderByDesc('completed_at')
            ->first();

        $lastApplied = MrpRun::where('status', MrpRun::STATUS_APPLIED)
            ->orderByDesc('applied_at')
            ->first();

        return [
            'total_runs' => $total,
            'by_status' => $byStatus,
            'last_completed_run' => $lastRun ? [
                'id' => $lastRun->id,
                'run_number' => $lastRun->run_number,
                'completed_at' => $lastRun->completed_at,
                'total_suggestions' => $lastRun->total_purchase_suggestions
                    + $lastRun->total_work_order_suggestions
                    + $lastRun->total_subcontract_suggestions,
            ] : null,
            'last_applied_run' => $lastApplied ? [
                'id' => $lastApplied->id,
                'run_number' => $lastApplied->run_number,
                'applied_at' => $lastApplied->applied_at,
            ] : null,
        ];
    }

    /**
     * Calculate supply for a single demand.
     */
    private function calculateSupplyForDemand(MrpDemand $demand): void
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
    private function getOnOrderQuantity(int $productId, ?int $warehouseId): float
    {
        return (float) PurchaseOrderItem::query()
            ->whereHas('purchaseOrder', function ($q) {
                $q->whereIn('status', [
                    PurchaseOrder::STATUS_APPROVED,
                    PurchaseOrder::STATUS_PARTIAL,
                ]);
            })
            ->where('product_id', $productId)
            ->selectRaw('SUM(quantity - quantity_received) as pending')
            ->value('pending') ?? 0;
    }

    /**
     * Apply MOQ and order multiple constraints.
     */
    private function applyOrderConstraints(Product $product, float $requiredQty): float
    {
        $moq = (float) ($product->min_order_qty ?? 1);
        $multiple = (float) ($product->order_multiple ?? 1);

        // Apply MOQ
        $qty = max($requiredQty, $moq);

        // Apply order multiple (round up)
        if ($multiple > 1) {
            $qty = ceil($qty / $multiple) * $multiple;
        }

        return $qty;
    }

    /**
     * Get suggestion type based on product procurement type.
     */
    private function getSuggestionType(Product $product): string
    {
        return match ($product->procurement_type) {
            'buy' => MrpSuggestion::TYPE_PURCHASE,
            'make' => MrpSuggestion::TYPE_WORK_ORDER,
            'subcontract' => MrpSuggestion::TYPE_SUBCONTRACT,
            default => MrpSuggestion::TYPE_PURCHASE,
        };
    }

    /**
     * Calculate priority based on urgency.
     */
    private function calculatePriority(\DateTimeInterface $orderDate): string
    {
        $daysUntilOrder = now()->diffInDays($orderDate, false);

        if ($daysUntilOrder < 0) {
            return MrpSuggestion::PRIORITY_URGENT;
        }
        if ($daysUntilOrder <= 3) {
            return MrpSuggestion::PRIORITY_HIGH;
        }
        if ($daysUntilOrder <= 7) {
            return MrpSuggestion::PRIORITY_NORMAL;
        }

        return MrpSuggestion::PRIORITY_LOW;
    }

    /**
     * Generate reason text for suggestion.
     */
    private function generateReason(Product $product, float $shortage, int $demandCount): string
    {
        $type = match ($product->procurement_type) {
            'buy' => 'dibeli',
            'make' => 'diproduksi',
            'subcontract' => 'disubkontrakkan',
            default => 'diadakan',
        };

        return "Kekurangan {$shortage} {$product->unit} untuk {$demandCount} permintaan. Produk perlu {$type}.";
    }
}
