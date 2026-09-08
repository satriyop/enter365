<?php

namespace App\Models\Inventory;

use App\Models\Contacts\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVendorPricelist extends Model
{
    /** @use HasFactory<\Database\Factories\Inventory\ProductVendorPricelistFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'contact_id',
        'min_qty',
        'unit',
        'price',
        'currency',
        'lead_time_days',
        'vendor_product_code',
    ];

    protected function casts(): array
    {
        return [
            'min_qty' => 'decimal:4',
            'price' => 'integer',
            'lead_time_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
