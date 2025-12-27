<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MrpSuggestion extends Model
{
    use HasFactory;

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_WORK_ORDER = 'work_order';

    public const TYPE_SUBCONTRACT = 'subcontract';

    public const ACTION_CREATE = 'create';

    public const ACTION_EXPEDITE = 'expedite';

    public const ACTION_DEFER = 'defer';

    public const ACTION_CANCEL = 'cancel';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CONVERTED = 'converted';

    protected $fillable = [
        'mrp_run_id',
        'product_id',
        'suggestion_type',
        'action',
        'suggested_order_date',
        'suggested_due_date',
        'quantity_required',
        'suggested_quantity',
        'adjusted_quantity',
        'suggested_supplier_id',
        'suggested_warehouse_id',
        'estimated_unit_cost',
        'estimated_total_cost',
        'priority',
        'status',
        'reason',
        'notes',
        'converted_to_type',
        'converted_to_id',
        'converted_at',
        'converted_by',
    ];

    protected function casts(): array
    {
        return [
            'suggested_order_date' => 'date',
            'suggested_due_date' => 'date',
            'quantity_required' => 'decimal:4',
            'suggested_quantity' => 'decimal:4',
            'adjusted_quantity' => 'decimal:4',
            'estimated_unit_cost' => 'integer',
            'estimated_total_cost' => 'integer',
            'converted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MrpRun, $this>
     */
    public function mrpRun(): BelongsTo
    {
        return $this->belongsTo(MrpRun::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function suggestedSupplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'suggested_supplier_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function suggestedWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'suggested_warehouse_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function converter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    /**
     * Polymorphic relation to converted document.
     *
     * @return MorphTo<Model, $this>
     */
    public function convertedTo(): MorphTo
    {
        return $this->morphTo('converted_to');
    }

    /**
     * Check if suggestion is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if suggestion is accepted.
     */
    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * Check if suggestion is converted.
     */
    public function isConverted(): bool
    {
        return $this->status === self::STATUS_CONVERTED;
    }

    /**
     * Check if suggestion is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if suggestion can be accepted.
     */
    public function canBeAccepted(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if suggestion can be rejected.
     */
    public function canBeRejected(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if suggestion can be converted.
     */
    public function canBeConverted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * Check if this is a purchase suggestion.
     */
    public function isPurchaseSuggestion(): bool
    {
        return $this->suggestion_type === self::TYPE_PURCHASE;
    }

    /**
     * Check if this is a work order suggestion.
     */
    public function isWorkOrderSuggestion(): bool
    {
        return $this->suggestion_type === self::TYPE_WORK_ORDER;
    }

    /**
     * Check if this is a subcontract suggestion.
     */
    public function isSubcontractSuggestion(): bool
    {
        return $this->suggestion_type === self::TYPE_SUBCONTRACT;
    }

    /**
     * Get effective quantity (adjusted if set, otherwise suggested).
     */
    public function getEffectiveQuantity(): float
    {
        return $this->adjusted_quantity !== null
            ? (float) $this->adjusted_quantity
            : (float) $this->suggested_quantity;
    }

    /**
     * Accept suggestion.
     */
    public function accept(): void
    {
        if (! $this->canBeAccepted()) {
            throw new \InvalidArgumentException('Saran tidak dapat diterima.');
        }

        $this->status = self::STATUS_ACCEPTED;
        $this->save();
    }

    /**
     * Reject suggestion.
     */
    public function reject(?string $reason = null): void
    {
        if (! $this->canBeRejected()) {
            throw new \InvalidArgumentException('Saran tidak dapat ditolak.');
        }

        $this->status = self::STATUS_REJECTED;
        if ($reason) {
            $this->notes = $reason;
        }
        $this->save();
    }

    /**
     * Mark as converted.
     */
    public function markAsConverted(string $type, int $id, ?int $userId = null): void
    {
        $this->status = self::STATUS_CONVERTED;
        $this->converted_to_type = $type;
        $this->converted_to_id = $id;
        $this->converted_at = now();
        $this->converted_by = $userId ?? auth()->id();
        $this->save();
    }

    /**
     * Calculate estimated costs.
     */
    public function calculateEstimatedCosts(): void
    {
        $product = $this->product;
        if (! $product) {
            return;
        }

        $quantity = $this->getEffectiveQuantity();

        $this->estimated_unit_cost = $product->purchase_price ?? 0;
        $this->estimated_total_cost = (int) round($quantity * $this->estimated_unit_cost);
    }

    /**
     * Get available types.
     *
     * @return array<string, string>
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_PURCHASE => 'Purchase Order',
            self::TYPE_WORK_ORDER => 'Work Order',
            self::TYPE_SUBCONTRACT => 'Subcontract',
        ];
    }

    /**
     * Get available actions.
     *
     * @return array<string, string>
     */
    public static function getActions(): array
    {
        return [
            self::ACTION_CREATE => 'Buat Baru',
            self::ACTION_EXPEDITE => 'Percepat',
            self::ACTION_DEFER => 'Tunda',
            self::ACTION_CANCEL => 'Batalkan',
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
     * Get available statuses.
     *
     * @return array<string, string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Menunggu',
            self::STATUS_ACCEPTED => 'Diterima',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_CONVERTED => 'Dikonversi',
        ];
    }

    /**
     * Scope by type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<MrpSuggestion>  $query
     * @return \Illuminate\Database\Eloquent\Builder<MrpSuggestion>
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('suggestion_type', $type);
    }

    /**
     * Scope by status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<MrpSuggestion>  $query
     * @return \Illuminate\Database\Eloquent\Builder<MrpSuggestion>
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope pending suggestions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<MrpSuggestion>  $query
     * @return \Illuminate\Database\Eloquent\Builder<MrpSuggestion>
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope accepted suggestions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<MrpSuggestion>  $query
     * @return \Illuminate\Database\Eloquent\Builder<MrpSuggestion>
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    /**
     * Scope urgent suggestions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<MrpSuggestion>  $query
     * @return \Illuminate\Database\Eloquent\Builder<MrpSuggestion>
     */
    public function scopeUrgent($query)
    {
        return $query->where('priority', self::PRIORITY_URGENT);
    }
}
