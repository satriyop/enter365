<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringTemplate extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_INVOICE = 'invoice';

    public const TYPE_BILL = 'bill';

    public const FREQUENCY_DAILY = 'daily';

    public const FREQUENCY_WEEKLY = 'weekly';

    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCY_QUARTERLY = 'quarterly';

    public const FREQUENCY_YEARLY = 'yearly';

    protected $fillable = [
        'name',
        'type',
        'contact_id',
        'frequency',
        'interval',
        'start_date',
        'end_date',
        'next_generate_date',
        'occurrences_limit',
        'occurrences_count',
        'description',
        'reference',
        'tax_rate',
        'discount_amount',
        'early_discount_percent',
        'early_discount_days',
        'payment_term_days',
        'currency',
        'items',
        'is_active',
        'auto_post',
        'auto_send',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'next_generate_date' => 'date',
            'tax_rate' => 'decimal:2',
            'discount_amount' => 'integer',
            'early_discount_percent' => 'decimal:2',
            'items' => 'array',
            'is_active' => 'boolean',
            'auto_post' => 'boolean',
            'auto_send' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<Bill, $this>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if template should generate a document.
     */
    public function shouldGenerate(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->end_date && $this->end_date->isPast()) {
            return false;
        }

        if ($this->occurrences_limit && $this->occurrences_count >= $this->occurrences_limit) {
            return false;
        }

        if ($this->next_generate_date && $this->next_generate_date->isFuture()) {
            return false;
        }

        return true;
    }

    /**
     * Calculate the next generation date based on frequency.
     */
    public function calculateNextDate(): ?\DateTimeInterface
    {
        $baseDate = $this->next_generate_date ?? $this->start_date;

        return match ($this->frequency) {
            self::FREQUENCY_DAILY => $baseDate->copy()->addDays($this->interval),
            self::FREQUENCY_WEEKLY => $baseDate->copy()->addWeeks($this->interval),
            self::FREQUENCY_MONTHLY => $baseDate->copy()->addMonths($this->interval),
            self::FREQUENCY_QUARTERLY => $baseDate->copy()->addMonths($this->interval * 3),
            self::FREQUENCY_YEARLY => $baseDate->copy()->addYears($this->interval),
            default => null,
        };
    }

    /**
     * Get frequency options with labels.
     *
     * @return array<string, string>
     */
    public static function getFrequencyOptions(): array
    {
        return config('accounting.recurring.frequencies', [
            self::FREQUENCY_DAILY => 'Harian',
            self::FREQUENCY_WEEKLY => 'Mingguan',
            self::FREQUENCY_MONTHLY => 'Bulanan',
            self::FREQUENCY_QUARTERLY => 'Triwulan',
            self::FREQUENCY_YEARLY => 'Tahunan',
        ]);
    }
}
