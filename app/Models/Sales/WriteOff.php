<?php

namespace App\Models\Sales;

use App\Models\Accounting\JournalEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $write_off_number
 * @property int $invoice_id
 * @property int $amount
 * @property int $base_amount
 * @property string $reason
 * @property Carbon $write_off_date
 * @property int|null $journal_entry_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class WriteOff extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'write_off_number',
        'invoice_id',
        'amount',
        'base_amount',
        'reason',
        'write_off_date',
        'journal_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'base_amount' => 'integer',
            'write_off_date' => 'date',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (WriteOff $writeOff) {
            if (empty($writeOff->write_off_number)) {
                $prefix = 'WO-'.now()->format('Ym').'-';
                $writeOff->write_off_number = \App\Domain\Shared\DocumentNumbers::generate(
                    $prefix,
                    'write_offs',
                    'write_off_number'
                );
            }
        });
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
}
