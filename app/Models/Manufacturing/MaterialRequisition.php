<?php

namespace App\Models\Manufacturing;

use App\Enums\DocumentStatus;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Traits\Filterable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $requisition_number
 * @property int $work_order_id
 * @property int $warehouse_id
 * @property DocumentStatus $status
 * @property Carbon $requested_date
 * @property Carbon|null $required_date
 * @property int $total_items
 * @property string $total_quantity
 * @property string|null $notes
 * @property int|null $requested_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $issued_by
 * @property Carbon|null $issued_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class MaterialRequisition extends Model
{
    use Filterable, HasFactory, SoftDeletes;

    protected $fillable = [
        'requisition_number',
        'work_order_id',
        'warehouse_id',
        'status',
        'requested_date',
        'required_date',
        'total_items',
        'total_quantity',
        'notes',
        'requested_by',
        'approved_by',
        'approved_at',
        'issued_by',
        'issued_at',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (MaterialRequisition $requisition) {
            if (empty($requisition->requisition_number)) {
                $requisition->requisition_number = \App\Domain\Shared\DocumentNumbers::generate(
                    'REQ-'.now()->format('Ym').'-',
                    'material_requisitions',
                    'requisition_number'
                );
            }
        });
    }

    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
            'required_date' => 'date',
            'total_quantity' => 'decimal:4',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
            'status' => DocumentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<MaterialRequisitionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MaterialRequisitionItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Check if MR can be edited.
     */
    public function canBeEdited(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    public function isEditable(): bool
    {
        return $this->canBeEdited();
    }

    public function isDeletable(): bool
    {
        return $this->isEditable();
    }

    /**
     * Check if MR can be approved.
     */
    public function canBeApproved(): bool
    {
        return $this->status === DocumentStatus::Draft && $this->items()->exists();
    }

    /**
     * Check if MR can be issued.
     */
    public function canBeIssued(): bool
    {
        return in_array($this->status, [DocumentStatus::Approved, DocumentStatus::Partial], true);
    }

    /**
     * Check if MR can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            DocumentStatus::Draft,
            DocumentStatus::Approved,
            DocumentStatus::Partial,
        ], true);
    }

    /**
     * Check if MR is fully issued.
     */
    public function isFullyIssued(): bool
    {
        return $this->items()->where('quantity_pending', '>', 0)->count() === 0;
    }

    /**
     * Update totals from items.
     */
    public function updateTotals(): void
    {
        $this->total_items = $this->items()->count();
        $this->total_quantity = (float) $this->items()->sum('quantity_requested');
    }

    /**
     * Get available statuses.
     *
     * @return array<string, string>
     */
    public static function getStatuses(): array
    {
        return collect(DocumentStatus::forMaterialRequisition())
            ->mapWithKeys(fn (DocumentStatus $status) => [$status->value => $status->label()])
            ->toArray();
    }

    public function stateMachine(): \App\Domain\Manufacturing\MaterialRequisitions\MaterialRequisitionStateMachine
    {
        return \App\Domain\Manufacturing\MaterialRequisitions\MaterialRequisitionStateMachine::fromMaterialRequisition($this);
    }

    public function transitionTo(\App\Enums\DocumentStatus $status, ?int $userId = null, array $context = []): self
    {
        $context = array_merge(['user_id' => $userId], $context);
        $this->stateMachine()->transitionTo($status, $context);

        return $this->refresh();
    }
}