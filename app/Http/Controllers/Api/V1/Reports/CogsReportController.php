<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\Inventory\Product;
use App\Services\Accounting\Reports\ReportServiceFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CogsReportController extends Controller
{
    public function __construct(
        private ReportServiceFactory $reports
    ) {}

    /**
     * Laporan Ringkasan HPP (COGS Summary).
     *
     * @response array{data: array{report_name: string, period: array{start: string, end: string}, beginning_inventory: int, purchases: int, goods_available: int, ending_inventory: int, cogs: int, cogs_from_movements: int}}
     */
    public function cogsSummary(Request $request): JsonResponse
    {
        $this->authorize('reports.cogs');

        $report = $this->reports->cogs()->getCOGSSummary(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return $this->success([
            'report_name' => 'Laporan Harga Pokok Penjualan',
            ...$report,
        ]);
    }

    /**
     * Laporan HPP per Produk.
     *
     * @response array{data: array{report_name: string, period: array{start: string, end: string}, products: list<array{product_id: int, sku: string, name: string, category: string|null, quantity_sold: int, average_unit_cost: int, total_cogs: int, percentage: float}>, total_cogs: int}}
     */
    public function cogsByProduct(Request $request): JsonResponse
    {
        $this->authorize('reports.cogs');

        $products = $this->reports->cogs()->getCOGSByProduct(
            $request->input('start_date'),
            $request->input('end_date')
        );

        $startDate = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->input('end_date') ?? now()->endOfMonth()->toDateString();

        return $this->success([
            'report_name' => 'Laporan HPP per Produk',
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'products' => $products,
            'total_cogs' => $products->sum('total_cogs'),
        ]);
    }

    /**
     * Laporan HPP per Kategori.
     *
     * @response array{data: array{report_name: string, period: array{start: string, end: string}, categories: list<array{category_id: int|null, category_name: string, product_count: int, quantity_sold: int, total_cogs: int, percentage: float}>, total_cogs: int}}
     */
    public function cogsByCategory(Request $request): JsonResponse
    {
        $this->authorize('reports.cogs');

        $categories = $this->reports->cogs()->getCOGSByCategory(
            $request->input('start_date'),
            $request->input('end_date')
        );

        $startDate = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->input('end_date') ?? now()->endOfMonth()->toDateString();

        return $this->success([
            'report_name' => 'Laporan HPP per Kategori',
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'categories' => $categories,
            'total_cogs' => $categories->sum('total_cogs'),
        ]);
    }

    /**
     * Laporan Trend HPP Bulanan.
     *
     * @response array{data: array{report_name: string, year: int, months: list<array{month: string, month_name: string, beginning_inventory: int, purchases: int, ending_inventory: int, cogs: int}>, total_cogs: int}}
     */
    public function cogsMonthlyTrend(Request $request): JsonResponse
    {
        $this->authorize('reports.cogs');

        $year = (int) $request->input('year', now()->year);
        $months = $this->reports->cogs()->getMonthlyCOGSTrend($year);

        return $this->success([
            'report_name' => "Trend HPP Tahun {$year}",
            'year' => $year,
            'months' => $months,
            'total_cogs' => $months->sum('cogs'),
        ]);
    }

    /**
     * Laporan Detail HPP Produk.
     *
     * @response array{data: array{report_name: string, product: array{id: int, sku: string, name: string}, period: array{start: string, end: string}, movements: list<array{id: int, date: string, movement_number: string, reference_type: string|null, reference_id: int|null, quantity: float, unit_cost: int, total_cost: int, notes: string|null}>, total_quantity: float, total_cogs: int}}
     */
    public function productCOGSDetail(Request $request, Product $product): JsonResponse
    {
        $this->authorize('reports.cogs');

        $details = $this->reports->cogs()->getProductCOGSDetail(
            $product,
            $request->input('start_date'),
            $request->input('end_date')
        );

        $startDate = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->input('end_date') ?? now()->endOfMonth()->toDateString();

        return $this->success([
            'report_name' => 'Detail HPP Produk',
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
            ],
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'movements' => $details,
            'total_quantity' => $details->sum('quantity'),
            'total_cogs' => $details->sum('total_cost'),
        ]);
    }
}
