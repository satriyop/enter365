<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PLANNING = 'planning';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_ON_HOLD = 'on_hold';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'project_number',
        'name',
        'description',
        'contact_id',
        'quotation_id',
        'start_date',
        'end_date',
        'actual_start_date',
        'actual_end_date',
        'status',
        'budget_amount',
        'contract_amount',
        'total_cost',
        'total_revenue',
        'gross_profit',
        'profit_margin',
        'progress_percentage',
        'priority',
        'location',
        'notes',
        'manager_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'actual_start_date' => 'date',
            'actual_end_date' => 'date',
            'budget_amount' => 'integer',
            'contract_amount' => 'integer',
            'total_cost' => 'integer',
            'total_revenue' => 'integer',
            'gross_profit' => 'integer',
            'profit_margin' => 'decimal:2',
            'progress_percentage' => 'decimal:2',
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
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return HasMany<ProjectCost, $this>
     */
    public function costs(): HasMany
    {
        return $this->hasMany(ProjectCost::class);
    }

    /**
     * @return HasMany<ProjectRevenue, $this>
     */
    public function revenues(): HasMany
    {
        return $this->hasMany(ProjectRevenue::class);
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
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * @return HasMany<Quotation, $this>
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if project can be edited.
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PLANNING]);
    }

    /**
     * Check if project can be started.
     */
    public function canBeStarted(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PLANNING]);
    }

    /**
     * Check if project can be put on hold.
     */
    public function canBePutOnHold(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Check if project can be resumed.
     */
    public function canBeResumed(): bool
    {
        return $this->status === self::STATUS_ON_HOLD;
    }

    /**
     * Check if project can be completed.
     */
    public function canBeCompleted(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Check if project can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return ! in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    /**
     * Calculate and update financial totals.
     */
    public function calculateFinancials(): void
    {
        $this->total_cost = (int) $this->costs()->sum('total_cost');
        $this->total_revenue = (int) $this->revenues()->sum('amount');
        $this->gross_profit = $this->total_revenue - $this->total_cost;
        $this->profit_margin = $this->total_revenue > 0
            ? round(($this->gross_profit / $this->total_revenue) * 100, 2)
            : 0;
    }

    /**
     * Get cost breakdown by type.
     *
     * @return array<string, int>
     */
    public function getCostBreakdown(): array
    {
        return $this->costs()
            ->selectRaw('cost_type, SUM(total_cost) as total')
            ->groupBy('cost_type')
            ->pluck('total', 'cost_type')
            ->toArray();
    }

    /**
     * Get budget variance.
     */
    public function getBudgetVariance(): int
    {
        return $this->budget_amount - $this->total_cost;
    }

    /**
     * Check if project is over budget.
     */
    public function isOverBudget(): bool
    {
        return $this->budget_amount > 0 && $this->total_cost > $this->budget_amount;
    }

    /**
     * Get budget utilization percentage.
     */
    public function getBudgetUtilization(): float
    {
        if ($this->budget_amount <= 0) {
            return 0;
        }

        return round(($this->total_cost / $this->budget_amount) * 100, 2);
    }

    /**
     * Check if project is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->end_date
            && $this->end_date->isPast()
            && ! in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    /**
     * Get days until deadline.
     */
    public function getDaysUntilDeadline(): ?int
    {
        if (! $this->end_date) {
            return null;
        }

        return (int) now()->diffInDays($this->end_date, false);
    }

    /**
     * Get project duration in days.
     */
    public function getDurationDays(): ?int
    {
        if (! $this->start_date || ! $this->end_date) {
            return null;
        }

        return (int) $this->start_date->diffInDays($this->end_date);
    }

    /**
     * Get available statuses.
     *
     * @return array<string, string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PLANNING => 'Perencanaan',
            self::STATUS_IN_PROGRESS => 'Berjalan',
            self::STATUS_ON_HOLD => 'Ditunda',
            self::STATUS_COMPLETED => 'Selesai',
            self::STATUS_CANCELLED => 'Dibatalkan',
        ];
    }

    /**
     * Get available priorities.
     *
     * @return array<string, string>
     */
    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_LOW => 'Rendah',
            self::PRIORITY_NORMAL => 'Normal',
            self::PRIORITY_HIGH => 'Tinggi',
            self::PRIORITY_URGENT => 'Mendesak',
        ];
    }

    /**
     * Generate the next project number.
     */
    public static function generateProjectNumber(): string
    {
        $prefix = 'PRJ-'.now()->format('Ym').'-';
        $lastProject = static::query()
            ->where('project_number', 'like', $prefix.'%')
            ->orderBy('project_number', 'desc')
            ->first();

        if ($lastProject) {
            $lastNumber = (int) substr($lastProject->project_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
