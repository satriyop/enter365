<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'product_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'amount',
        'revenue_account_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'integer',
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'revenue_account_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate and set the amount.
     */
    public function calculateAmount(): void
    {
        $this->amount = (int) round($this->quantity * $this->unit_price);
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
        $this->revenue_account_id = $product->sales_account_id;
        $this->calculateAmount();
    }
}
