<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
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
        'notes',
        'revenue_account_id',
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
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'revenue_account_id');
    }

    /**
     * Calculate and set the line total.
     */
    public function calculateLineTotal(): void
    {
        $grossAmount = (int) round($this->quantity * $this->unit_price);

        // Apply line discount
        if ($this->discount_percent > 0) {
            $this->discount_amount = (int) round($grossAmount * ($this->discount_percent / 100));
        }

        $netAmount = $grossAmount - $this->discount_amount;

        // Calculate tax
        if ($this->tax_rate > 0) {
            $this->tax_amount = (int) round($netAmount * ($this->tax_rate / 100));
        } else {
            $this->tax_amount = 0;
        }

        // Line total is net amount (tax is calculated at header level typically)
        // But we store line-level tax for reporting purposes
        $this->line_total = $netAmount;
    }

    /**
     * Fill item from product.
     */
    public function fillFromProduct(Product $product, float $quantity = 1): void
    {
        $this->product_id = $product->id;
        $this->description = $product->name;
        $this->unit = $product->unit;
        $this->unit_price = $product->selling_price;
        $this->quantity = $quantity;
        $this->tax_rate = config('accounting.tax.default_rate', 11.00);
        $this->revenue_account_id = $product->sales_account_id;
        $this->calculateLineTotal();
    }

    /**
     * Get the gross amount before discount.
     */
    public function getGrossAmount(): int
    {
        return (int) round($this->quantity * $this->unit_price);
    }
}
