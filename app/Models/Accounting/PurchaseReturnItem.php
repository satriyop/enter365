<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnItem extends Model
{
    use HasFactory;

    public const CONDITION_GOOD = 'good';

    public const CONDITION_DAMAGED = 'damaged';

    public const CONDITION_DEFECTIVE = 'defective';

    protected $fillable = [
        'purchase_return_id',
        'bill_item_id',
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
     * @return BelongsTo<PurchaseReturn, $this>
     */
    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    /**
     * @return BelongsTo<BillItem, $this>
     */
    public function billItem(): BelongsTo
    {
        return $this->belongsTo(BillItem::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Fill item data from a bill item.
     */
    public function fillFromBillItem(BillItem $billItem): void
    {
        $this->bill_item_id = $billItem->id;
        $this->product_id = $billItem->product_id;
        $this->description = $billItem->description;
        $this->quantity = $billItem->quantity;
        $this->unit = $billItem->unit;
        $this->unit_price = $billItem->unit_price;
        $this->discount_percent = $billItem->discount_percent;
        $this->discount_amount = $billItem->discount_amount;
        $this->tax_rate = $billItem->tax_rate;
        $this->tax_amount = $billItem->tax_amount;
        $this->line_total = $billItem->line_total;
        $this->sort_order = $billItem->sort_order;
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
