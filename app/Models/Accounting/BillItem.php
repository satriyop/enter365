<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_id',
        'product_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'amount',
        'expense_account_id',
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
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
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
        $this->unit_price = $product->purchase_price;
        $this->quantity = $quantity;
        $this->expense_account_id = $product->purchase_account_id ?? $product->cogs_account_id;
        $this->calculateAmount();
    }
}
