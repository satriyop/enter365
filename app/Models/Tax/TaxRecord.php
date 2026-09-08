<?php

namespace App\Models\Tax;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TaxRecord extends Model
{
    /** @use HasFactory<\Database\Factories\Tax\TaxRecordFactory> */
    use HasFactory;

    public const APPLICABILITY_SALES = 'sales';

    public const APPLICABILITY_PURCHASE = 'purchase';

    public const APPLICABILITY_BOTH = 'both';

    protected $fillable = [
        'code',
        'name',
        'rate',
        'applicability',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    public static function applicabilities(): array
    {
        return [
            self::APPLICABILITY_SALES,
            self::APPLICABILITY_PURCHASE,
            self::APPLICABILITY_BOTH,
        ];
    }

    public function appliesToSales(): bool
    {
        return in_array($this->applicability, [self::APPLICABILITY_SALES, self::APPLICABILITY_BOTH], true);
    }

    public function appliesToPurchase(): bool
    {
        return in_array($this->applicability, [self::APPLICABILITY_PURCHASE, self::APPLICABILITY_BOTH], true);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_tax_records')
            ->withPivot('kind')
            ->withTimestamps();
    }
}
