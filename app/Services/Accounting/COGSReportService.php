<?php

namespace App\Services\Accounting;

use App\Models\Accounting\InventoryMovement;
use App\Models\Accounting\Product;
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
     * @return array{
     *     period: array{start: string, end: string},
     *     beginning_inventory: int,
     *     purchases: int,
     *     goods_available: int,
     *     ending_inventory: int,
     *     cogs: int,
     *     gross_profit_contribution: int
     * }
     */
    public function getCOGSSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ? Carbon::parse($startDate) : now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : now()->endOfMonth();

        // Beginning inventory value (sum of all product costs at start)
        $beginningInventory = $this->getInventoryValueAtDate($startDate->copy()->subDay());

        // Purchases during period (inventory IN movements)
        $purchases = InventoryMovement::query()
            ->where('type', InventoryMovement::TYPE_IN)
            ->whereBetween('movement_date', [$startDate, $endDate])
            ->sum('total_cost');

        // Ending inventory value
        $endingInventory = $this->getInventoryValueAtDate($endDate);

        // COGS = Beginning + Purchases - Ending
        $cogs = $beginningInventory + (int) $purchases - $endingInventory;

        // Alternative: Sum of all OUT movements with cost tracking
        $cogsFromMovements = InventoryMovement::query()
            ->where('type', InventoryMovement::TYPE_OUT)
            ->whereBetween('movement_date', [$startDate, $endDate])
            ->sum('total_cost');

        // Use movement-based COGS as it's more accurate in perpetual inventory
        $cogs = max($cogs, (int) $cogsFromMovements);

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
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
     *
     * @return Collection<int, array{
     *     product_id: int,
     *     sku: string,
     *     name: string,
     *     category: string|null,
     *     quantity_sold: int,
     *     average_unit_cost: int,
     *     total_cogs: int,
     *     percentage: float
     * }>
     */
    public function getCOGSByProduct(?string $startDate = null, ?string $endDate = null): Collection
    {
        $startDate = $startDate ? Carbon::parse($startDate) : now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : now()->endOfMonth();

        $results = DB::table('inventory_movements as m')
            ->join('products as p', 'm.product_id', '=', 'p.id')
            ->leftJoin('product_categories as c', 'p.category_id', '=', 'c.id')
            ->where('m.type', InventoryMovement::TYPE_OUT)
            ->whereBetween('m.movement_date', [$startDate, $endDate])
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

        return $results->map(fn ($row) => [
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
     *
     * @return Collection<int, array{
     *     category_id: int|null,
     *     category_name: string,
     *     product_count: int,
     *     quantity_sold: int,
     *     total_cogs: int,
     *     percentage: float
     * }>
     */
    public function getCOGSByCategory(?string $startDate = null, ?string $endDate = null): Collection
    {
        $startDate = $startDate ? Carbon::parse($startDate) : now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : now()->endOfMonth();

        $results = DB::table('inventory_movements as m')
            ->join('products as p', 'm.product_id', '=', 'p.id')
            ->leftJoin('product_categories as c', 'p.category_id', '=', 'c.id')
            ->where('m.type', InventoryMovement::TYPE_OUT)
            ->whereBetween('m.movement_date', [$startDate, $endDate])
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
     * @return Collection<int, array{
     *     month: string,
     *     beginning_inventory: int,
     *     purchases: int,
     *     ending_inventory: int,
     *     cogs: int
     * }>
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
     *
     * @return Collection<int, array{
     *     product_id: int,
     *     sku: string,
     *     name: string,
     *     quantity_sold: int,
     *     total_cogs: int,
     *     average_selling_price: int,
     *     estimated_gross_profit: int,
     *     gross_margin_percent: float
     * }>
     */
    public function getTopProductsByCOGS(?string $startDate = null, ?string $endDate = null, int $limit = 10): Collection
    {
        $startDate = $startDate ? Carbon::parse($startDate) : now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : now()->endOfMonth();

        return DB::table('inventory_movements as m')
            ->join('products as p', 'm.product_id', '=', 'p.id')
            ->where('m.type', InventoryMovement::TYPE_OUT)
            ->whereBetween('m.movement_date', [$startDate, $endDate])
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
     */
    protected function getInventoryValueAtDate(Carbon $asOfDate): int
    {
        // Sum current value of all products based on movements up to date
        $products = Product::query()
            ->where('track_inventory', true)
            ->where('is_active', true)
            ->get();

        $totalValue = 0;

        foreach ($products as $product) {
            // Get quantity at date
            $ins = InventoryMovement::query()
                ->where('product_id', $product->id)
                ->whereIn('type', [InventoryMovement::TYPE_IN, InventoryMovement::TYPE_TRANSFER_IN])
                ->whereDate('movement_date', '<=', $asOfDate)
                ->sum('quantity');

            $outs = InventoryMovement::query()
                ->where('product_id', $product->id)
                ->whereIn('type', [InventoryMovement::TYPE_OUT, InventoryMovement::TYPE_TRANSFER_OUT])
                ->whereDate('movement_date', '<=', $asOfDate)
                ->sum('quantity');

            $adjustments = InventoryMovement::query()
                ->where('product_id', $product->id)
                ->where('type', InventoryMovement::TYPE_ADJUSTMENT)
                ->whereDate('movement_date', '<=', $asOfDate)
                ->sum('quantity');

            $quantity = (int) $ins - (int) $outs + (int) $adjustments;

            // Use average cost from recent movements or purchase price
            $avgCost = $this->getAverageCost($product, $asOfDate);

            $totalValue += $quantity * $avgCost;
        }

        return $totalValue;
    }

    /**
     * Get average cost for a product up to a date.
     */
    protected function getAverageCost(Product $product, Carbon $asOfDate): int
    {
        // Calculate weighted average cost from IN movements
        $movements = InventoryMovement::query()
            ->where('product_id', $product->id)
            ->where('type', InventoryMovement::TYPE_IN)
            ->whereDate('movement_date', '<=', $asOfDate)
            ->where('quantity', '>', 0)
            ->get();

        if ($movements->isEmpty()) {
            return $product->purchase_price ?? 0;
        }

        $totalCost = $movements->sum('total_cost');
        $totalQuantity = $movements->sum('quantity');

        return $totalQuantity > 0 ? (int) round($totalCost / $totalQuantity) : 0;
    }

    /**
     * Get COGS detail movements for a specific product.
     *
     * @return Collection<int, array{
     *     id: int,
     *     date: string,
     *     reference: string|null,
     *     quantity: int,
     *     unit_cost: int,
     *     total_cost: int,
     *     notes: string|null
     * }>
     */
    public function getProductCOGSDetail(Product $product, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $startDate = $startDate ? Carbon::parse($startDate) : now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : now()->endOfMonth();

        return InventoryMovement::query()
            ->where('product_id', $product->id)
            ->where('type', InventoryMovement::TYPE_OUT)
            ->whereBetween('movement_date', [$startDate, $endDate])
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
