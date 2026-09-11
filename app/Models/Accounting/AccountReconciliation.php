<?php

namespace App\Models\Accounting;

use App\Models\Contacts\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $account_id
 * @property int|null $partner_id
 * @property int $amount
 * @property string|null $notes
 * @property Carbon|null $reconciled_at
 * @property int|null $reconciled_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AccountReconciliation extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\AccountReconciliationFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'partner_id',
        'amount',
        'notes',
        'reconciled_at',
        'reconciled_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'reconciled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'partner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    /**
     * @return HasMany<AccountReconciliationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(AccountReconciliationItem::class);
    }
}
