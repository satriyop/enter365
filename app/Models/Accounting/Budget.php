<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Budget extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_ANNUAL = 'annual';

    public const TYPE_QUARTERLY = 'quarterly';

    public const TYPE_MONTHLY = 'monthly';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'name',
        'description',
        'fiscal_period_id',
        'type',
        'status',
        'total_revenue',
        'total_expense',
        'net_budget',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_revenue' => 'integer',
            'total_expense' => 'integer',
            'net_budget' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FiscalPeriod, $this>
     */
    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    /**
     * @return HasMany<BudgetLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if budget is editable.
     */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if budget is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if budget is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Approve the budget.
     */
    public function approve(?int $userId = null): bool
    {
        if (! $this->isEditable()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $userId ?? auth()->id(),
            'approved_at' => now(),
        ]);

        return true;
    }

    /**
     * Close the budget.
     */
    public function close(): bool
    {
        if ($this->status !== self::STATUS_APPROVED) {
            return false;
        }

        $this->update(['status' => self::STATUS_CLOSED]);

        return true;
    }

    /**
     * Reopen the budget (set back to draft).
     */
    public function reopen(): bool
    {
        if ($this->status === self::STATUS_CLOSED) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_DRAFT,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return true;
    }

    /**
     * Recalculate totals from lines.
     */
    public function recalculateTotals(): void
    {
        $revenue = 0;
        $expense = 0;

        foreach ($this->lines()->with('account')->get() as $line) {
            if ($line->account->type === Account::TYPE_REVENUE) {
                $revenue += $line->annual_amount;
            } elseif ($line->account->type === Account::TYPE_EXPENSE) {
                $expense += $line->annual_amount;
            }
        }

        $this->update([
            'total_revenue' => $revenue,
            'total_expense' => $expense,
            'net_budget' => $revenue - $expense,
        ]);
    }

    /**
     * Get budget amount for a specific month.
     */
    public function getMonthlyBudget(int $month): array
    {
        $revenue = 0;
        $expense = 0;

        foreach ($this->lines()->with('account')->get() as $line) {
            $monthAmount = $line->getMonthAmount($month);
            if ($line->account->type === Account::TYPE_REVENUE) {
                $revenue += $monthAmount;
            } elseif ($line->account->type === Account::TYPE_EXPENSE) {
                $expense += $monthAmount;
            }
        }

        return [
            'month' => $month,
            'revenue' => $revenue,
            'expense' => $expense,
            'net' => $revenue - $expense,
        ];
    }

    /**
     * Get status label in Indonesian.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_CLOSED => 'Ditutup',
            default => $this->status,
        };
    }

    /**
     * Get type label in Indonesian.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_ANNUAL => 'Tahunan',
            self::TYPE_QUARTERLY => 'Kuartalan',
            self::TYPE_MONTHLY => 'Bulanan',
            default => $this->type,
        };
    }

    /**
     * Scope for active (approved) budgets.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Budget>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Budget>
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for draft budgets.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Budget>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Budget>
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope for a specific fiscal period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Budget>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Budget>
     */
    public function scopeForPeriod($query, FiscalPeriod $period)
    {
        return $query->where('fiscal_period_id', $period->id);
    }
}
