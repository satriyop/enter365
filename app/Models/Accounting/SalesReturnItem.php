<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    use HasFactory;

    public const CONDITION_GOOD = 'good';

    public const CONDITION_DAMAGED = 'damaged';

    public const CONDITION_DEFECTIVE = 'defective';

    protected $fillable = [
        'sales_return_id',
        'invoice_item_id',
        'product_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'line_total',
        'sort_order',
        'condition',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
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
     * @return BelongsTo<SalesReturn, $this>
     */
    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
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
     * Fill item data from an invoice item.
     */
    public function fillFromInvoiceItem(InvoiceItem $invoiceItem): void
    {
        $this->invoice_item_id = $invoiceItem->id;
        $this->product_id = $invoiceItem->product_id;
        $this->description = $invoiceItem->description;
        $this->quantity = $invoiceItem->quantity;
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

    /**
     * Get available conditions.
     *
     * @return array<string, string>
     */
    public static function getConditions(): array
    {
        return [
            self::CONDITION_GOOD => 'Baik',
            self::CONDITION_DAMAGED => 'Rusak',
            self::CONDITION_DEFECTIVE => 'Cacat',
        ];
    }
}
