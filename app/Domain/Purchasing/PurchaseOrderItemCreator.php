<?php

declare(strict_types=1);

namespace App\Domain\Purchasing;

use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;

class PurchaseOrderItemCreator
{
    public function create(PurchaseOrder $purchaseOrder, array $items): void
    {
        foreach ($items as $index => $itemData) {
            $quantity = $itemData['quantity'] ?? 1;
            $unitPrice = $itemData['unit_price'] ?? 0;
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
