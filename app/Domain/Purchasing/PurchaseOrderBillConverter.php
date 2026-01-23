<?php

declare(strict_types=1);

namespace App\Domain\Purchasing;

use App\Enums\DocumentStatus;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\BillItem;
use App\Models\Purchasing\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class PurchaseOrderBillConverter
{
    public function convert(PurchaseOrder $purchaseOrder): Bill
    {
        if (! $purchaseOrder->canConvert()) {
            throw new \InvalidArgumentException('PO tidak dapat dikonversi. Pastikan sudah menerima barang dan belum dikonversi.');
        }

        return DB::transaction(function () use ($purchaseOrder) {
            $bill = $this->createBill($purchaseOrder);
            $this->copyReceivedItems($purchaseOrder, $bill);
            $this->markAsConverted($purchaseOrder, $bill);

            return $bill->load('items', 'contact');
        });
    }

    private function createBill(PurchaseOrder $purchaseOrder): Bill
    {
        return Bill::create([
            'contact_id' => $purchaseOrder->contact_id,
            'bill_date' => now(),
            'due_date' => now()->addDays(config('accounting.payment.default_term_days', 30)),
            'description' => $purchaseOrder->subject,
            'reference' => $purchaseOrder->getFullNumber(),
            'subtotal' => $purchaseOrder->subtotal,
            'tax_amount' => $purchaseOrder->tax_amount,
            'tax_rate' => $purchaseOrder->tax_rate,
            'discount_amount' => $purchaseOrder->discount_amount,
            'total_amount' => $purchaseOrder->total_amount,
            'currency' => $purchaseOrder->currency,
            'exchange_rate' => $purchaseOrder->exchange_rate,
            'base_currency_total' => $purchaseOrder->base_currency_total,
            'paid_amount' => 0,
            'status' => DocumentStatus::Draft,
            'created_by' => auth()->id(),
        ]);
    }

    private function copyReceivedItems(PurchaseOrder $purchaseOrder, Bill $bill): void
    {
        foreach ($purchaseOrder->items as $item) {
            if ($item->quantity_received > 0) {
                $receivedRatio = $item->quantity_received / $item->quantity;
                $lineTotal = (int) round($item->line_total * $receivedRatio);

                BillItem::create([
                    'bill_id' => $bill->id,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity_received,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'amount' => $lineTotal,
                ]);
            }
        }
    }

    private function markAsConverted(PurchaseOrder $purchaseOrder, Bill $bill): void
    {
        $purchaseOrder->update([
            'converted_to_bill_id' => $bill->id,
            'converted_at' => now(),
        ]);
    }
}
