<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptNoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'goods_receipt_note_id',
        'purchase_order_item_id',
        'product_id',
        'unit',
        'description',
        'quantity_ordered',
        'quantity_received',
        'quantity_rejected',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'line_total',
        'sort_order',
        'rejection_reason',
        'quality_notes',
        'lot_number',
        'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'integer',
            'quantity_received' => 'integer',
            'quantity_rejected' => 'integer',
            'unit_price' => 'integer',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'integer',
            'line_total' => 'integer',
            'sort_order' => 'integer',
            'expiry_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<GoodsReceiptNote, $this>
     */
    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class);
    }

    /**
     * @return BelongsTo<PurchaseOrderItem, $this>
     */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Check if item has been received.
     */
    public function isReceived(): bool
    {
        return $this->quantity_received > 0;
    }

    /**
     * Check if item is fully received.
     */
    public function isFullyReceived(): bool
    {
        return $this->quantity_received >= $this->quantity_ordered;
    }

    /**
     * Check if item has rejections.
     */
    public function hasRejections(): bool
    {
        return $this->quantity_rejected > 0;
    }

    /**
     * Get quantity remaining to receive.
     */
    public function getQuantityRemaining(): int
    {
        return max(0, $this->quantity_ordered - $this->quantity_received - $this->quantity_rejected);
    }

    /**
     * Get total value received.
     */
    public function getTotalValueReceived(): int
    {
        return $this->quantity_received * $this->unit_price;
    }

    /**
     * Fill from purchase order item.
     */
    public function fillFromPurchaseOrderItem(PurchaseOrderItem $poItem): void
    {
        $this->purchase_order_item_id = $poItem->id;
        $this->product_id = $poItem->product_id;
        $this->unit = $poItem->unit;
        $this->description = $poItem->description;
        $this->quantity_ordered = (int) $poItem->quantity;
        $this->unit_price = $poItem->unit_price;
        $this->discount_percent = $poItem->discount_percent;
        $this->discount_amount = $poItem->discount_amount;
        $this->tax_rate = $poItem->tax_rate;
        $this->tax_amount = $poItem->tax_amount;
        $this->line_total = $poItem->line_total;
        $this->sort_order = $poItem->sort_order;
    }

    /**
     * Calculate and set the line total based on received quantity.
     */
    public function calculateLineTotal(): void
    {
        $subtotal = (int) round($this->quantity_received * $this->unit_price);
        $discountAmount = (int) round($subtotal * ($this->discount_percent ?? 0) / 100);
        $afterDiscount = $subtotal - $discountAmount;
        $taxAmount = (int) round($afterDiscount * ($this->tax_rate ?? 0) / 100);

        $this->discount_amount = $discountAmount;
        $this->tax_amount = $taxAmount;
        $this->line_total = $afterDiscount + $taxAmount;
    }

    /**
     * Record received quantity.
     */
    public function recordReceipt(int $quantityReceived, int $quantityRejected = 0, ?string $rejectionReason = null, ?string $qualityNotes = null): void
    {
        $this->quantity_received = $quantityReceived;
        $this->quantity_rejected = $quantityRejected;

        if ($quantityRejected > 0 && $rejectionReason) {
            $this->rejection_reason = $rejectionReason;
        }

        if ($qualityNotes) {
            $this->quality_notes = $qualityNotes;
        }

        $this->save();

        // Update parent totals
        $this->goodsReceiptNote->updateTotals();
    }
}
