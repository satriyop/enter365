<?php

namespace App\Models\Manufacturing;

use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Projects\Project;
use App\Models\Sales\Invoice;
use App\Models\Shared\SubcontractorInvoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $sc_wo_number
 * @property int $subcontractor_id
 * @property int|null $work_order_id
 * @property int|null $project_id
 * @property string $name
 * @property string|null $description
 * @property string|null $scope_of_work
 * @property DocumentStatus $status
 * @property int $agreed_amount
 * @property int $actual_amount
 * @property string $retention_percent
 * @property int $retention_amount
 * @property int $amount_invoiced
 * @property int $amount_paid
 * @property int $amount_due
 * @property Carbon|null $scheduled_start_date
 * @property Carbon|null $scheduled_end_date
 * @property Carbon|null $actual_start_date
 * @property Carbon|null $actual_end_date
 * @property int|null $completion_percentage
 * @property string|null $work_location
 * @property string|null $location_address
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $assigned_by
 * @property Carbon|null $assigned_at
 * @property int|null $started_by
 * @property Carbon|null $started_at
 * @property int|null $completed_by
 * @property Carbon|null $completed_at
 * @property int|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class SubcontractorWorkOrder extends Model
{
    use HasFactory, SoftDeletes;

    public const DEFAULT_RETENTION_PERCENT = 5.00;

    protected $fillable = [
        'sc_wo_number',
        'subcontractor_id',
        'work_order_id',
        'project_id',
        'name',
        'description',
        'scope_of_work',
        'status',
        'agreed_amount',
        'actual_amount',
        'retention_percent',
        'retention_amount',
        'amount_invoiced',
        'amount_paid',
        'amount_due',
        'scheduled_start_date',
        'scheduled_end_date',
        'actual_start_date',
        'actual_end_date',
        'completion_percentage',
        'work_location',
        'location_address',
        'notes',
        'created_by',
        'assigned_by',
        'assigned_at',
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
            'agreed_amount' => 'integer',
            'actual_amount' => 'integer',
            'retention_percent' => 'decimal:2',
            'retention_amount' => 'integer',
            'amount_invoiced' => 'integer',
            'amount_paid' => 'integer',
            'amount_due' => 'integer',
            'scheduled_start_date' => 'date',
            'scheduled_end_date' => 'date',
            'actual_start_date' => 'date',
            'actual_end_date' => 'date',
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'status' => DocumentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'subcontractor_id');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<SubcontractorInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(SubcontractorInvoice::class);
    }

    /**
     * @return HasMany<SubcontractorInvoice, $this>
     */
    public function approvedInvoices(): HasMany
    {
        return $this->hasMany(SubcontractorInvoice::class)
            ->where('status', SubcontractorInvoice::STATUS_APPROVED);
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
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
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
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Check if work order is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    /**
     * Check if work order is assigned.
     */
    public function isAssigned(): bool
    {
        return $this->status === DocumentStatus::Assigned;
    }

    /**
     * Check if work order is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === DocumentStatus::InProgress;
    }

    /**
     * Check if work order is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === DocumentStatus::Completed;
    }

    /**
     * Check if work order is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === DocumentStatus::Cancelled;
    }

    /**
     * Check if work order can be edited.
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, [DocumentStatus::Draft, DocumentStatus::Assigned], true);
    }

    public function isEditable(): bool
    {
        return $this->canBeEdited();
    }

    public function isDeletable(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    /**
     * Check if work order can be assigned.
     */
    public function canBeAssigned(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    /**
     * Check if work order can be started.
     */
    public function canBeStarted(): bool
    {
        return $this->status === DocumentStatus::Assigned;
    }

    /**
     * Check if progress can be updated.
     */
    public function canUpdateProgress(): bool
    {
        return $this->status === DocumentStatus::InProgress;
    }

    /**
     * Check if work order can be completed.
     */
    public function canBeCompleted(): bool
    {
        return $this->status === DocumentStatus::InProgress;
    }

    /**
     * Check if work order can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            DocumentStatus::Draft,
            DocumentStatus::Assigned,
            DocumentStatus::InProgress,
        ], true);
    }

    /**
     * Check if invoice can be created.
     */
    public function canCreateInvoice(): bool
    {
        return in_array($this->status, [DocumentStatus::InProgress, DocumentStatus::Completed], true);
    }

    /**
     * Calculate retention amount.
     */
    public function calculateRetention(): int
    {
        $amount = $this->actual_amount > 0 ? $this->actual_amount : $this->agreed_amount;

        return (int) round($amount * ((float) $this->retention_percent / 100));
    }

    /**
     * Calculate amount due (after retention).
     */
    public function calculateAmountDue(): int
    {
        $amount = $this->actual_amount > 0 ? $this->actual_amount : $this->agreed_amount;

        return $amount - $this->retention_amount - $this->amount_paid;
    }

    /**
     * Recalculate financials.
     */
    public function recalculateFinancials(): void
    {
        $this->retention_amount = $this->calculateRetention();
        $this->amount_invoiced = (int) $this->invoices()->sum('gross_amount');
        $this->amount_due = $this->calculateAmountDue();
    }

    /**
     * Get remaining invoiceable amount.
     */
    public function getRemainingInvoiceableAmount(): int
    {
        $total = $this->actual_amount > 0 ? $this->actual_amount : $this->agreed_amount;

        return max(0, $total - $this->amount_invoiced);
    }

    /**
     * Check if fully invoiced.
     */
    public function isFullyInvoiced(): bool
    {
        return $this->getRemainingInvoiceableAmount() <= 0;
    }

    /**
     * Get available statuses.
     *
     * @return array<string, string>
     */
    public static function getStatuses(): array
    {
        return collect(DocumentStatus::forSubcontractorWorkOrder())
            ->mapWithKeys(fn (DocumentStatus $status) => [$status->value => $status->label()])
            ->toArray();
    }

    /**
     * Generate SC WO number.
     */
    public static function generateScWoNumber(?Project $project = null): string
    {
        if ($project) {
            $prefix = $project->project_number.'-SC-';
            $lastScWo = static::query()
                ->where('project_id', $project->id)
                ->orderByDesc('id')
                ->first();

            if ($lastScWo && preg_match('/-SC-(\d+)$/', $lastScWo->sc_wo_number, $matches)) {
                $sequence = (int) $matches[1] + 1;
            } else {
                $sequence = 1;
            }

            return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
        }

        // Standalone SC WO
        $prefix = 'SC-'.now()->format('Ym').'-';
        $lastScWo = static::query()
            ->where('sc_wo_number', 'like', $prefix.'%')
            ->whereNull('project_id')
            ->orderByDesc('sc_wo_number')
            ->first();

        if ($lastScWo) {
            $lastNumber = (int) substr($lastScWo->sc_wo_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Scope by status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<SubcontractorWorkOrder>  $query
     * @return \Illuminate\Database\Eloquent\Builder<SubcontractorWorkOrder>
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for active work orders.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<SubcontractorWorkOrder>  $query
     * @return \Illuminate\Database\Eloquent\Builder<SubcontractorWorkOrder>
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            DocumentStatus::Draft,
            DocumentStatus::Assigned,
            DocumentStatus::InProgress,
        ]);
    }

    /**
     * Scope for subcontractor.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<SubcontractorWorkOrder>  $query
     * @return \Illuminate\Database\Eloquent\Builder<SubcontractorWorkOrder>
     */
    public function scopeForSubcontractor($query, int $subcontractorId)
    {
        return $query->where('subcontractor_id', $subcontractorId);
    }

    /**
     * Scope for project.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<SubcontractorWorkOrder>  $query
     * @return \Illuminate\Database\Eloquent\Builder<SubcontractorWorkOrder>
     */
    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function stateMachine(): \App\Domain\Manufacturing\SubcontractorWorkOrders\SubcontractorWorkOrderStateMachine
    {
        return \App\Domain\Manufacturing\SubcontractorWorkOrders\SubcontractorWorkOrderStateMachine::fromSubcontractorWorkOrder($this);
    }

    public function transitionTo(DocumentStatus $status, ?int $userId = null, array $context = []): self
    {
        $this->stateMachine()->transitionTo($status, array_merge(['user_id' => $userId], $context));

        return $this->refresh();
    }
}
