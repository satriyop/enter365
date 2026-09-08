<?php

namespace App\Models\Tax;

use App\Models\Accounting\Account;
use App\Models\Accounting\TaxTag;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TaxRecord extends Model
{
    /** @use HasFactory<\Database\Factories\Tax\TaxRecordFactory> */
    use HasFactory;

    public const APPLICABILITY_SALES = 'sales';

    public const APPLICABILITY_PURCHASE = 'purchase';

    public const APPLICABILITY_BOTH = 'both';

    public const COMPUTATION_PERCENTAGE = 'percentage';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'computation' => self::COMPUTATION_PERCENTAGE,
    ];

    protected $fillable = [
        'code',
        'name',
        'rate',
        'computation',
        'applicability',
        'is_active',
        'invoice_account_id',
        'refund_account_id',
        'tax_tag_id',
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

    /**
     * @return BelongsTo<Account, $this>
     */
    public function invoiceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'invoice_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function refundAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'refund_account_id');
    }

    /**
     * @return BelongsTo<TaxTag, $this>
     */
    public function taxTag(): BelongsTo
    {
        return $this->belongsTo(TaxTag::class);
    }
}
