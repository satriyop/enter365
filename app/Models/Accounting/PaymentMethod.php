<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property string $direction
 * @property string $payment_type
 * @property int|null $journal_id
 */
class PaymentMethod extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\PaymentMethodFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'is_active', 'direction', 'payment_type', 'journal_id'];

    protected $attributes = [
        'is_active' => true,
        'direction' => 'inbound',
        'payment_type' => 'bank_transfer',
        'journal_id' => null,

    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function paymentProviders(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(PaymentProvider::class);
    }

    public function journal(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
