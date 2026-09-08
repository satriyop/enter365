<?php

declare(strict_types=1);

namespace App\Domain\Purchasing;

use App\Models\Inventory\Product;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;

class PurchaseOrderItemCreator
{
    public function create(PurchaseOrder $purchaseOrder, array $items): void
    {
        foreach ($items as $index => $itemData) {
            $quantity = $itemData['quantity'] ?? 1;
            $unitPrice = array_key_exists('unit_price', $itemData) && $itemData['unit_price'] !== null
                ? (int) $itemData['unit_price']
                : self::unitPriceForVendor($purchaseOrder, $itemData);
            $discountPercent = $itemData['discount_percent'] ?? 0;
            $taxRate = $itemData['tax_rate'] ?? $purchaseOrder->tax_rate;

            $grossAmount = (int) round($quantity * $unitPrice);
            $discountAmount = $discountPercent > 0
                ? (int) round($grossAmount * ($discountPercent / 100))
                : 0;
            $netAmount = $grossAmount - $discountAmount;
            $taxAmount = (int) round($netAmount * ($taxRate / 100));

            PurchaseOrderItem::create([
                'purchase_order_id' => $purchaseOrder->id,
                'product_id' => $itemData['product_id'] ?? null,
                'description' => $itemData['description'],
                'quantity' => $quantity,
                'quantity_received' => 0,
                'unit' => $itemData['unit'] ?? 'unit',
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'line_total' => $netAmount,
                'sort_order' => $itemData['sort_order'] ?? $index,
                'notes' => $itemData['notes'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $itemData
     */
    public static function unitPriceForVendor(PurchaseOrder $purchaseOrder, array $itemData): int
    {
        $productId = $itemData['product_id'] ?? null;
        if (! $productId) {
            return 0;
        }

        $product = Product::query()->with('vendorPricelists')->find((int) $productId);

        return $product?->priceForVendor(
            $purchaseOrder->contact_id,
            (float) ($itemData['quantity'] ?? 1)
        ) ?? 0;
    }

    public function copyFromPurchaseOrder(PurchaseOrder $source, PurchaseOrder $target): void
    {
        foreach ($source->items as $item) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $target->id,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'quantity_received' => 0,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'discount_percent' => $item->discount_percent,
                'discount_amount' => $item->discount_amount,
                'tax_rate' => $item->tax_rate,
                'tax_amount' => $item->tax_amount,
                'line_total' => $item->line_total,
                'sort_order' => $item->sort_order,
                'notes' => $item->notes,
            ]);
        }
    }
}
