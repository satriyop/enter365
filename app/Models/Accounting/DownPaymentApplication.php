<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DownPaymentApplication extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'down_payment_id',
        'applicable_type',
        'applicable_id',
        'amount',
        'applied_date',
        'notes',
        'journal_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'applied_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<DownPayment, $this>
     */
    public function downPayment(): BelongsTo
    {
        return $this->belongsTo(DownPayment::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function applicable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if applied to an invoice.
     */
    public function isAppliedToInvoice(): bool
    {
        return $this->applicable_type === Invoice::class;
    }

    /**
     * Check if applied to a bill.
     */
    public function isAppliedToBill(): bool
    {
        return $this->applicable_type === Bill::class;
    }
}
