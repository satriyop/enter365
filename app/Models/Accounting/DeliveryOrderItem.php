<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_order_id',
        'invoice_item_id',
        'product_id',
        'description',
        'quantity',
        'quantity_delivered',
        'unit',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'line_total',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'quantity_delivered' => 'decimal:4',
            'unit_price' => 'integer',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'integer',
            'line_total' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DeliveryOrder, $this>
     */
    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    /**
     * @return BelongsTo<InvoiceItem, $this>
     */
    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get remaining quantity to deliver.
     */
    public function getRemainingQuantity(): float
    {
        return (float) $this->quantity - (float) $this->quantity_delivered;
    }

    /**
     * Check if item is fully delivered.
     */
    public function isFullyDelivered(): bool
    {
        return (float) $this->quantity_delivered >= (float) $this->quantity;
    }

    /**
     * Fill from invoice item.
     */
    public function fillFromInvoiceItem(InvoiceItem $invoiceItem, ?float $quantity = null): void
    {
        $this->invoice_item_id = $invoiceItem->id;
        $this->product_id = $invoiceItem->product_id;
        $this->description = $invoiceItem->description;
        $this->quantity = $quantity ?? $invoiceItem->quantity;
        $this->unit = $invoiceItem->unit;
        $this->unit_price = $invoiceItem->unit_price;
        $this->discount_percent = $invoiceItem->discount_percent;
        $this->discount_amount = $invoiceItem->discount_amount;
        $this->tax_rate = $invoiceItem->tax_rate;
        $this->tax_amount = $invoiceItem->tax_amount;
        $this->line_total = $invoiceItem->line_total;
        $this->sort_order = $invoiceItem->sort_order;
    }

    /**
     * Fill from product.
     */
    public function fillFromProduct(Product $product, float $quantity): void
    {
        $this->product_id = $product->id;
        $this->description = $product->name;
        $this->quantity = $quantity;
        $this->unit = $product->unit;
        $this->unit_price = $product->selling_price;
        $this->calculateLineTotal();
    }

    /**
     * Calculate and set the line total.
     */
    public function calculateLineTotal(): void
    {
        $subtotal = (int) round($this->quantity * $this->unit_price);
        $discountAmount = $this->discount_amount ?: (int) round($subtotal * ($this->discount_percent ?? 0) / 100);
        $afterDiscount = $subtotal - $discountAmount;
        $taxAmount = $this->tax_amount ?: (int) round($afterDiscount * ($this->tax_rate ?? 0) / 100);

        $this->discount_amount = $discountAmount;
        $this->tax_amount = $taxAmount;
        $this->line_total = $afterDiscount + $taxAmount;
    }
}
