<?php

namespace Database\Seeders\Demo;

use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVendorPricelist;
use Illuminate\Database\Seeder;

class VendorPricelistDemoSeeder extends Seeder
{
    public const PRODUCT_LIMIT = 40;

    /**
     * Idempotent: one min-qty-1 vendor line per (purchasable product, supplier)
     * so New PO vendor + product actually hits a pricelist, not only purchase_price.
     */
    public function run(): void
    {
        $vendors = Contact::query()
            ->where('is_active', true)
            ->whereIn('type', [Contact::TYPE_SUPPLIER, Contact::TYPE_BOTH])
            ->orderBy('id')
            ->get(['id']);

        if ($vendors->isEmpty()) {
            $this->command?->warn('  ⏭  No suppliers — skip vendor pricelists');

            return;
        }

        $products = Product::query()
            ->where('is_active', true)
            ->where('is_purchasable', true)
            ->where('purchase_price', '>', 0)
            ->orderBy('sku')
            ->limit(self::PRODUCT_LIMIT)
            ->get(['id', 'sku', 'unit', 'purchase_price']);

        $kt57 = Product::query()
            ->where('sku', 'KT57-AIR')
            ->where('purchase_price', '>', 0)
            ->first(['id', 'sku', 'unit', 'purchase_price']);

        if ($kt57 && ! $products->contains('id', $kt57->id)) {
            $products = $products->prepend($kt57);
        }

        if ($products->isEmpty()) {
            $this->command?->warn('  ⏭  No purchasable products — skip vendor pricelists');

            return;
        }

        $created = 0;

        foreach ($products as $product) {
            $listPrice = max(1, (int) round(((int) $product->purchase_price) * 0.95));
            $unit = $product->unit ?: 'pcs';

            foreach ($vendors as $vendor) {
                $existing = ProductVendorPricelist::query()
                    ->where('product_id', $product->id)
                    ->where('contact_id', $vendor->id)
                    ->where('min_qty', 1)
                    ->exists();

                if ($existing) {
                    continue;
                }

                ProductVendorPricelist::query()->create([
                    'product_id' => $product->id,
                    'contact_id' => $vendor->id,
                    'min_qty' => 1,
                    'unit' => $unit,
                    'price' => $listPrice,
                    'currency' => 'IDR',
                    'lead_time_days' => 3,
                ]);
                $created++;
            }
        }

        $this->command?->info("  ✓ Vendor pricelists: {$created} new lines ({$products->count()} products × {$vendors->count()} vendors)");
    }
}
