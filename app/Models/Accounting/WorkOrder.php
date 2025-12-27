<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrder extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_PRODUCTION = 'production';

    public const TYPE_INSTALLATION = 'installation';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'wo_number',
        'project_id',
        'bom_id',
        'product_id',
        'parent_work_order_id',
        'type',
        'name',
        'description',
        'quantity_ordered',
        'quantity_completed',
        'quantity_scrapped',
        'status',
        'priority',
        'progress_percentage',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'estimated_material_cost',
        'estimated_labor_cost',
        'estimated_overhead_cost',
        'estimated_total_cost',
        'actual_material_cost',
        'actual_labor_cost',
        'actual_overhead_cost',
        'actual_total_cost',
        'cost_variance',
        'warehouse_id',
        'notes',
        'created_by',
        'confirmed_by',
        'confirmed_at',
        'started_by',
        'started_at',
        'completed_by',
        'completed_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:4',
            'quantity_completed' => 'decimal:4',
            'quantity_scrapped' => 'decimal:4',
            'progress_percentage' => 'integer',
            'planned_start_date' => 'date',
            'planned_end_date' => 'date',
            'actual_start_date' => 'date',
            'actual_end_date' => 'date',
            'estimated_material_cost' => 'integer',
            'estimated_labor_cost' => 'integer',
            'estimated_overhead_cost' => 'integer',
            'estimated_total_cost' => 'integer',
            'actual_material_cost' => 'integer',
            'actual_labor_cost' => 'integer',
            'actual_overhead_cost' => 'integer',
            'actual_total_cost' => 'integer',
            'cost_variance' => 'integer',
            'confirmed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Bom, $this>
     */
    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parentWorkOrder(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_work_order_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function subWorkOrders(): HasMany
    {
        return $this->hasMany(self::class, 'parent_work_order_id');
    }

    /**
     * @return HasMany<WorkOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<WorkOrderItem, $this>
     */
    public function materialItems(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class)
            ->where('type', WorkOrderItem::TYPE_MATERIAL)
            ->orderBy('sort_order');
    }

    /**
     * @return HasMany<WorkOrderItem, $this>
     */
    public function laborItems(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class)
            ->where('type', WorkOrderItem::TYPE_LABOR)
            ->orderBy('sort_order');
    }

    /**
     * @return HasMany<WorkOrderItem, $this>
     */
    public function overheadItems(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class)
            ->where('type', WorkOrderItem::TYPE_OVERHEAD)
            ->orderBy('sort_order');
    }

    /**
     * @return HasMany<MaterialRequisition, $this>
     */
    public function materialRequisitions(): HasMany
    {
        return $this->hasMany(MaterialRequisition::class);
    }

    /**
     * @return HasMany<MaterialConsumption, $this>
     */
    public function consumptions(): HasMany
    {
        return $this->hasMany(MaterialConsumption::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Check if WO can be edited.
     */
    public function canBeEdited(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if WO can be confirmed.
     */
    public function canBeConfirmed(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->items()->exists();
    }

    /**
     * Check if WO can be started.
     */
    public function canBeStarted(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Check if WO can be completed.
     */
    public function canBeCompleted(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Check if WO can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CONFIRMED, self::STATUS_IN_PROGRESS]);
    }

    /**
     * Check if WO is production type.
     */
    public function isProduction(): bool
    {
        return $this->type === self::TYPE_PRODUCTION;
    }

    /**
     * Check if WO is installation type.
     */
    public function isInstallation(): bool
    {
        return $this->type === self::TYPE_INSTALLATION;
    }

    /**
     * Calculate estimated costs from items.
     */
    public function calculateEstimatedCosts(): void
    {
        $this->estimated_material_cost = (int) $this->items()
            ->where('type', WorkOrderItem::TYPE_MATERIAL)
            ->sum('total_estimated_cost');

        $this->estimated_labor_cost = (int) $this->items()
            ->where('type', WorkOrderItem::TYPE_LABOR)
            ->sum('total_estimated_cost');

        $this->estimated_overhead_cost = (int) $this->items()
            ->where('type', WorkOrderItem::TYPE_OVERHEAD)
            ->sum('total_estimated_cost');

        $this->estimated_total_cost = $this->estimated_material_cost
            + $this->estimated_labor_cost
            + $this->estimated_overhead_cost;
    }

    /**
     * Calculate actual costs from consumptions.
     */
    public function calculateActualCosts(): void
    {
        $this->actual_material_cost = (int) $this->items()
            ->where('type', WorkOrderItem::TYPE_MATERIAL)
            ->sum('total_actual_cost');

        $this->actual_labor_cost = (int) $this->items()
            ->where('type', WorkOrderItem::TYPE_LABOR)
            ->sum('total_actual_cost');

        $this->actual_overhead_cost = (int) $this->items()
            ->where('type', WorkOrderItem::TYPE_OVERHEAD)
            ->sum('total_actual_cost');

        $this->actual_total_cost = $this->actual_material_cost
            + $this->actual_labor_cost
            + $this->actual_overhead_cost;

        $this->cost_variance = $this->estimated_total_cost - $this->actual_total_cost;
    }

    /**
     * Get completion percentage.
     */
    public function getCompletionPercentage(): float
    {
        if ($this->quantity_ordered <= 0) {
            return 0;
        }

        return round(((float) $this->quantity_completed / (float) $this->quantity_ordered) * 100, 2);
    }

    /**
     * Update cached progress percentage.
     */
    public function updateProgressPercentage(): void
    {
        $this->progress_percentage = (int) min(100, $this->getCompletionPercentage());
    }

    /**
     * Get available types.
     *
     * @return array<string, string>
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_PRODUCTION => 'Produksi',
            self::TYPE_INSTALLATION => 'Instalasi',
        ];
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
            self::STATUS_CONFIRMED => 'Dikonfirmasi',
            self::STATUS_IN_PROGRESS => 'Dalam Proses',
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
     * Generate WO number.
     */
    public static function generateWoNumber(?Project $project = null): string
    {
        if ($project) {
            $prefix = $project->project_number.'-WO-';
            $lastWo = static::query()
                ->where('project_id', $project->id)
                ->orderByDesc('id')
                ->first();

            if ($lastWo && preg_match('/-WO-(\d+)$/', $lastWo->wo_number, $matches)) {
                $sequence = (int) $matches[1] + 1;
            } else {
                $sequence = 1;
            }

            return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
        }

        // Standalone WO
        $prefix = 'WO-'.now()->format('Ym').'-';
        $lastWo = static::query()
            ->where('wo_number', 'like', $prefix.'%')
            ->whereNull('project_id')
            ->orderByDesc('wo_number')
            ->first();

        if ($lastWo) {
            $lastNumber = (int) substr($lastWo->wo_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
