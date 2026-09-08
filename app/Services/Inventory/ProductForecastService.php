<?php

namespace App\Services\Inventory;

use App\Enums\DocumentStatus;
use App\Models\Inventory\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductForecastService
{
    /**
     * @param  Collection<int, Product>|iterable<Product>  $products
     */
    public function applyTo(iterable $products): void
    {
        $models = Collection::make($products)->filter();
        $ids = $models->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $forecasts = $this->forProductIds($ids);

        foreach ($models as $product) {
            $row = $forecasts[$product->id] ?? ['incoming' => 0, 'outgoing' => 0];
            $onHand = (int) $product->current_stock;
            $product->setAttribute('incoming_qty', $row['incoming']);
            $product->setAttribute('outgoing_qty', $row['outgoing']);
            $product->setAttribute('forecasted_qty', $onHand + $row['incoming'] - $row['outgoing']);
        }
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, array{incoming: int, outgoing: int}>
     */
    public function forProductIds(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter($productIds)));
        $result = [];

        foreach ($productIds as $id) {
            $result[$id] = ['incoming' => 0, 'outgoing' => 0];
        }

        if ($productIds === []) {
            return $result;
        }

        foreach ($this->incomingByProduct($productIds) as $productId => $qty) {
            $result[(int) $productId]['incoming'] = (int) $qty;
        }

        foreach ($this->outgoingByProduct($productIds) as $productId => $qty) {
            $result[(int) $productId]['outgoing'] = (int) $qty;
        }

        return $result;
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, int>
     */
    private function incomingByProduct(array $productIds): array
    {
        return DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->whereIn('poi.product_id', $productIds)
            ->whereIn('po.status', [
                DocumentStatus::Approved->value,
                DocumentStatus::Partial->value,
            ])
            ->whereNull('po.deleted_at')
            ->selectRaw('poi.product_id, COALESCE(SUM(poi.quantity - poi.quantity_received), 0) as incoming')
            ->groupBy('poi.product_id')
            ->pluck('incoming', 'product_id')
            ->map(fn ($qty) => (int) round((float) $qty))
            ->all();
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, int>
     */
    private function outgoingByProduct(array $productIds): array
    {
        $reserved = DB::table('product_stocks')
            ->whereIn('product_id', $productIds)
            ->selectRaw('product_id, COALESCE(SUM(reserved_quantity), 0) as reserved')
            ->groupBy('product_id')
            ->pluck('reserved', 'product_id')
            ->map(fn ($qty) => (int) $qty)
            ->all();

        $deliveries = DB::table('delivery_order_items as doi')
            ->join('delivery_orders as do', 'do.id', '=', 'doi.delivery_order_id')
            ->whereIn('doi.product_id', $productIds)
            ->whereIn('do.status', [
                DocumentStatus::Draft->value,
                DocumentStatus::Confirmed->value,
            ])
            ->whereNull('do.deleted_at')
            ->selectRaw('doi.product_id, COALESCE(SUM(doi.quantity - doi.quantity_delivered), 0) as outgoing')
            ->groupBy('doi.product_id')
            ->pluck('outgoing', 'product_id')
            ->map(fn ($qty) => (int) round((float) $qty))
            ->all();

        $outgoing = [];
        foreach ($productIds as $id) {
            $outgoing[$id] = (int) ($reserved[$id] ?? 0) + (int) ($deliveries[$id] ?? 0);
        }

        return $outgoing;
    }
}
