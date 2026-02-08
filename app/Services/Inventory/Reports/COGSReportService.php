<?php

namespace App\Services\Inventory\Reports;

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class COGSReportService
{
    /**
     * Get COGS summary report.
     *
     * Uses perpetual inventory method where COGS is tracked via inventory movements.
     *
     * @return array<string, mixed>
     */
    public function getCOGSSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? now()->endOfMonth()->toDateString();

        // Beginning inventory value (sum of all product costs at start)
        $beginningInventory = $this->getInventoryValueAtDate(Carbon::parse($startDate)->subDay());

        // Purchases during period (inventory IN movements)
        $purchases = InventoryMovement::query()
            ->where('type', InventoryMovement::TYPE_IN)
            ->whereBetween('movement_date', [$startDate, $endDate.' 23:59:59'])
            ->sum('total_cost');

        // Ending inventory value
        $endingInventory = $this->getInventoryValueAtDate(Carbon::parse($endDate));

        // COGS = Beginning + Purchases - Ending
        $cogs = $beginningInventory + (int) $purchases - $endingInventory;

        // Alternative: Sum of all OUT movements with cost tracking
        $cogsFromMovements = InventoryMovement::query()
            ->where('type', InventoryMovement::TYPE_OUT)
            ->whereBetween('movement_date', [$startDate, $endDate.' 23:59:59'])
            ->sum('total_cost');

        // Use movement-based COGS as it's more accurate in perpetual inventory
        $cogs = max($cogs, (int) $cogsFromMovements);

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'beginning_inventory' => $beginningInventory,
            'purchases' => (int) $purchases,
            'goods_available' => $beginningInventory + (int) $purchases,
            'ending_inventory' => $endingInventory,
            'cogs' => $cogs,
            'cogs_from_movements' => (int) $cogsFromMovements,
        ];
    }

    /**
     * Get COGS breakdown by product.
     */
    public function getCOGSByProduct(?string $startDate = null, ?string $endDate = null): Collection
    {
        $startDate = $startDate ?? now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? now()->endOfMonth()->toDateString();

        $results = DB::table('inventory_movements as m')
            ->join('products as p', 'm.product_id', '=', 'p.id')
            ->leftJoin('product_categories as c', 'p.category_id', '=', 'c.id')
            ->where('m.type', InventoryMovement::TYPE_OUT)
            ->whereBetween('m.movement_date', [$startDate, $endDate.' 23:59:59'])
            ->groupBy('p.id', 'p.sku', 'p.name', 'c.name')
            ->select([
                'p.id as product_id',
                'p.sku',
                'p.name',
                'c.name as category',
                DB::raw('SUM(m.quantity) as quantity_sold'),
                DB::raw('SUM(m.total_cost) as total_cogs'),
                DB::raw('CASE WHEN SUM(m.quantity) > 0 THEN ROUND(SUM(m.total_cost) / SUM(m.quantity)) ELSE 0 END as average_unit_cost'),
            ])
            ->orderByDesc('total_cogs')
            ->get();

        $totalCogs = $results->sum('total_cogs');

        return $results->map(fn (\stdClass $row) => [
            'product_id' => $row->product_id,
            'sku' => $row->sku,
            'name' => $row->name,
            'category' => $row->category,
            'quantity_sold' => (int) $row->quantity_sold,
            'average_unit_cost' => (int) $row->average_unit_cost,
            'total_cogs' => (int) $row->total_cogs,
            'percentage' => $totalCogs > 0
                ? round(($row->total_cogs / $totalCogs) * 100, 2)
                : 0,
        ]);
    }

    /**
     * Get COGS breakdown by category.
     */
    public function getCOGSByCategory(?string $startDate = null, ?string $endDate = null): Collection
    {
        $startDate = $startDate ?? now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? now()->endOfMonth()->toDateString();

        $results = DB::table('inventory_movements as m')
            ->join('products as p', 'm.product_id', '=', 'p.id')
            ->leftJoin('product_categories as c', 'p.category_id', '=', 'c.id')
            ->where('m.type', InventoryMovement::TYPE_OUT)
            ->whereBetween('m.movement_date', [$startDate, $endDate.' 23:59:59'])
            ->groupBy('c.id', 'c.name')
            ->select([
                'c.id as category_id',
                DB::raw("COALESCE(c.name, 'Tanpa Kategori') as category_name"),
                DB::raw('COUNT(DISTINCT p.id) as product_count'),
                DB::raw('SUM(m.quantity) as quantity_sold'),
                DB::raw('SUM(m.total_cost) as total_cogs'),
            ])
            ->orderByDesc('total_cogs')
            ->get();

        $totalCogs = $results->sum('total_cogs');

        return $results->map(fn ($row) => [
            'category_id' => $row->category_id,
            'category_name' => $row->category_name,
            'product_count' => (int) $row->product_count,
            'quantity_sold' => (int) $row->quantity_sold,
            'total_cogs' => (int) $row->total_cogs,
            'percentage' => $totalCogs > 0
                ? round(($row->total_cogs / $totalCogs) * 100, 2)
                : 0,
        ]);
    }

    /**
     * Get monthly COGS trend.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getMonthlyCOGSTrend(int $year): Collection
    {
        $months = collect();

        for ($month = 1; $month <= 12; $month++) {
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            // Skip future months
            if ($startDate->isFuture()) {
                continue;
            }

            $summary = $this->getCOGSSummary(
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );

            $months->push([
                'month' => $startDate->format('Y-m'),
                'month_name' => $startDate->translatedFormat('F'),
                'beginning_inventory' => $summary['beginning_inventory'],
                'purchases' => $summary['purchases'],
                'ending_inventory' => $summary['ending_inventory'],
                'cogs' => $summary['cogs'],
            ]);
        }

        return $months;
    }

    /**
     * Get top products by COGS.
     */
    public function getTopProductsByCOGS(?string $startDate = null, ?string $endDate = null, int $limit = 10): Collection
    {
        $startDate = $startDate ?? now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? now()->endOfMonth()->toDateString();

        return DB::table('inventory_movements as m')
            ->join('products as p', 'm.product_id', '=', 'p.id')
            ->where('m.type', InventoryMovement::TYPE_OUT)
            ->whereBetween('m.movement_date', [$startDate, $endDate.' 23:59:59'])
            ->groupBy('p.id', 'p.sku', 'p.name', 'p.selling_price')
            ->select([
                'p.id as product_id',
                'p.sku',
                'p.name',
                'p.selling_price as average_selling_price',
                DB::raw('SUM(m.quantity) as quantity_sold'),
                DB::raw('SUM(m.total_cost) as total_cogs'),
            ])
            ->orderByDesc('total_cogs')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $revenue = $row->quantity_sold * $row->average_selling_price;
                $grossProfit = $revenue - $row->total_cogs;

                return [
                    'product_id' => $row->product_id,
                    'sku' => $row->sku,
                    'name' => $row->name,
                    'quantity_sold' => (int) $row->quantity_sold,
                    'total_cogs' => (int) $row->total_cogs,
                    'average_selling_price' => (int) $row->average_selling_price,
                    'estimated_revenue' => (int) $revenue,
                    'estimated_gross_profit' => (int) $grossProfit,
                    'gross_margin_percent' => $revenue > 0
                        ? round(($grossProfit / $revenue) * 100, 2)
                        : 0,
                ];
            });
    }

    /**
     * Get inventory value at a specific date.
     *
     * Uses 3 batch queries instead of per-product loops (O(1) vs O(N*4)).
     */
    protected function getInventoryValueAtDate(Carbon $asOfDate): int
    {
        // Batch query 1: net quantity per product
        // Uses ABS() to normalize sign conventions (stockOut stores negative, factories store positive)
        $productData = DB::table('inventory_movements as m')
            ->join('products as p', 'm.product_id', '=', 'p.id')
            ->where('p.track_inventory', true)
            ->where('p.is_active', true)
            ->whereDate('m.movement_date', '<=', $asOfDate)
            ->groupBy('m.product_id')
            ->select([
                'm.product_id',
                DB::raw("SUM(CASE WHEN m.type IN ('in', 'transfer_in') THEN ABS(m.quantity) ELSE 0 END) as in_qty"),
                DB::raw("SUM(CASE WHEN m.type IN ('out', 'transfer_out') THEN ABS(m.quantity) ELSE 0 END) as out_qty"),
                DB::raw("SUM(CASE WHEN m.type = 'adjustment' THEN m.quantity ELSE 0 END) as adj_qty"),
            ])
            ->get();

        if ($productData->isEmpty()) {
            return 0;
        }

        $productIds = $productData->pluck('product_id');

        // Batch query 2: weighted average cost per product from IN movements
        $costs = DB::table('inventory_movements')
            ->whereIn('product_id', $productIds)
            ->where('type', InventoryMovement::TYPE_IN)
            ->whereDate('movement_date', '<=', $asOfDate)
            ->where('quantity', '>', 0)
            ->groupBy('product_id')
            ->select([
                'product_id',
                DB::raw('SUM(total_cost) as total_cost'),
                DB::raw('SUM(quantity) as total_qty'),
            ])
            ->get()
            ->keyBy('product_id');

        // Batch query 3: fallback purchase prices for products without IN movements
        $fallbackPrices = DB::table('products')
            ->whereIn('id', $productIds)
            ->pluck('purchase_price', 'id');

        $totalValue = 0;

        foreach ($productData as $row) {
            $quantity = (int) $row->in_qty - (int) $row->out_qty + (int) $row->adj_qty;

            if ($quantity <= 0) {
                continue;
            }

            $costData = $costs->get($row->product_id);
            if ($costData && $costData->total_qty > 0) {
                $avgCost = (int) round($costData->total_cost / $costData->total_qty);
            } else {
                $avgCost = (int) ($fallbackPrices->get($row->product_id) ?? 0);
            }

            $totalValue += $quantity * $avgCost;
        }

        return $totalValue;
    }

    /**
     * Get COGS detail movements for a specific product.
     */
    public function getProductCOGSDetail(Product $product, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $startDate = $startDate ?? now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? now()->endOfMonth()->toDateString();

        return InventoryMovement::query()
            ->where('product_id', $product->id)
            ->where('type', InventoryMovement::TYPE_OUT)
            ->whereBetween('movement_date', [$startDate, $endDate.' 23:59:59'])
            ->orderBy('movement_date')
            ->get()
            ->map(fn (InventoryMovement $m) => [
                'id' => $m->id,
                'date' => $m->movement_date->format('Y-m-d'),
                'movement_number' => $m->movement_number,
                'reference_type' => $m->reference_type,
                'reference_id' => $m->reference_id,
                'quantity' => $m->quantity,
                'unit_cost' => $m->unit_cost,
                'total_cost' => $m->total_cost,
                'notes' => $m->notes,
            ]);
    }
}
