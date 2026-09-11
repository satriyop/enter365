<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property string $state
 * @property int|null $journal_id
 * @property string|null $website
 */
class PaymentProvider extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\PaymentProviderFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'is_active', 'state', 'journal_id', 'website'];

    protected $attributes = [
        'is_active' => true,
        'state' => 'disabled',
        'journal_id' => null,
        'website' => null,

    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function journal(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function paymentMethods(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(PaymentMethod::class);
    }
}
