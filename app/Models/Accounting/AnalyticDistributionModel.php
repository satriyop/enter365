<?php

namespace App\Models\Accounting;

use App\Casts\AnalyticDistributionCast;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<string, float|int>|null $analytic_distribution
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AnalyticDistributionModel extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\AnalyticDistributionModelFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sequence' => 10,
        'is_active' => true,
    ];

    protected $fillable = [
        'name',
        'partner_id',
        'account_prefix',
        'product_id',
        'analytic_distribution',
        'sequence',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'is_active' => 'boolean',
            'analytic_distribution' => AnalyticDistributionCast::class,
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'partner_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
