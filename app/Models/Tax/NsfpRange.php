<?php

declare(strict_types=1);

namespace App\Models\Tax;

use App\Models\Sales\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $transaction_code
 * @property string $branch_code
 * @property string $year_code
 * @property int $range_start
 * @property int $range_end
 * @property int $next_number
 * @property int $used_count
 * @property bool $is_active
 * @property string|null $description
 * @property int|null $created_by
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class NsfpRange extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_code',
        'branch_code',
        'year_code',
        'range_start',
        'range_end',
        'next_number',
        'used_count',
        'is_active',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'range_start' => 'integer',
            'range_end' => 'integer',
            'next_number' => 'integer',
            'used_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'nsfp_range_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the total capacity of this range.
     */
    public function getCapacity(): int
    {
        return $this->range_end - $this->range_start + 1;
    }

    /**
     * Get the remaining available numbers in this range.
     */
    public function getRemainingCount(): int
    {
        return $this->range_end - $this->next_number + 1;
    }

    /**
     * Check if this range is fully exhausted.
     */
    public function isExhausted(): bool
    {
        return $this->next_number > $this->range_end;
    }

    /**
     * Check if the remaining numbers are below the low stock threshold.
     */
    public function isLowStock(): bool
    {
        $threshold = (int) config('accounting.nsfp.low_stock_threshold', 50);

        return $this->getRemainingCount() <= $threshold && ! $this->isExhausted();
    }

    /**
     * Format a number into the full NSFP string.
     *
     * Format: {transaction_code}.{branch_code}-{year_code}.{8-digit number}
     * Example: 010.000-26.00000001
     */
    public function formatNumber(int $number): string
    {
        return sprintf(
            '%s.%s-%s.%s',
            $this->transaction_code,
            $this->branch_code,
            $this->year_code,
            str_pad((string) $number, 8, '0', STR_PAD_LEFT)
        );
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForYear(Builder $query, string $yearCode): Builder
    {
        return $query->where('year_code', $yearCode);
    }
}
