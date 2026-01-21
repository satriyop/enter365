<?php

namespace App\Models\Sales;

use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Traits\Filterable;
use App\Traits\HasStatusHistory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryOrder extends Model
{
    use Filterable, HasFactory, HasStatusHistory, SoftDeletes;

    public const SHIPPING_METHODS = [
        'courier',
        'pickup',
        'own_delivery',
        'freight',
    ];

    protected $fillable = [
        'do_number',
        'invoice_id',
        'contact_id',
        'warehouse_id',
        'do_date',
        'shipping_date',
        'received_date',
        'shipping_address',
        'shipping_method',
        'tracking_number',
        'driver_name',
        'vehicle_number',
        'notes',
        'status',
        'received_by',
        'delivery_notes',
        'created_by',
        'confirmed_by',
        'confirmed_at',
        'shipped_by',
        'shipped_at',
        'delivered_by',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'do_date' => 'date',
            'shipping_date' => 'date',
            'received_date' => 'date',
            'confirmed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'status' => DocumentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<DeliveryOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DeliveryOrderItem::class);
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
    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function shippedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deliveredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    /**
     * Check if DO is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    /**
     * Check if DO is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->status === DocumentStatus::Confirmed;
    }

    /**
     * Check if DO is shipped.
     */
    public function isShipped(): bool
    {
        return $this->status === DocumentStatus::Shipped;
    }

    /**
     * Check if DO is delivered.
     */
    public function isDelivered(): bool
    {
        return $this->status === DocumentStatus::Delivered;
    }

    /**
     * Check if DO is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === DocumentStatus::Cancelled;
    }

    /**
     * Check if DO can be edited.
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
     * Check if DO can be confirmed.
     */
    public function canBeConfirmed(): bool
    {
        return $this->status === DocumentStatus::Draft && $this->items()->exists();
    }

    /**
     * Check if DO can be shipped.
     */
    public function canBeShipped(): bool
    {
        return $this->status === DocumentStatus::Confirmed;
    }

    /**
     * Check if DO can be marked as delivered.
     */
    public function canBeDelivered(): bool
    {
        return $this->status === DocumentStatus::Shipped;
    }

    /**
     * Check if DO can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [DocumentStatus::Draft, DocumentStatus::Confirmed], true);
    }

    /**
     * Get total items count.
     */
    public function getTotalItemsCount(): int
    {
        return $this->items()->count();
    }

    /**
     * Get total quantity.
     */
    public function getTotalQuantity(): float
    {
        return (float) $this->items()->sum('quantity');
    }

    /**
     * Get total delivered quantity.
     */
    public function getTotalDeliveredQuantity(): float
    {
        return (float) $this->items()->sum('quantity_delivered');
    }

    /**
     * Get delivery progress percentage.
     */
    public function getDeliveryProgress(): float
    {
        $totalQty = $this->getTotalQuantity();
        if ($totalQty <= 0) {
            return 0;
        }

        return round(($this->getTotalDeliveredQuantity() / $totalQty) * 100, 2);
    }

    /**
     * Generate the next DO number.
     */
    public static function generateDoNumber(): string
    {
        $prefix = 'DO-'.now()->format('Ym').'-';
        $lastDo = static::query()
            ->where('do_number', 'like', $prefix.'%')
            ->orderBy('do_number', 'desc')
            ->first();

        if ($lastDo) {
            $lastNumber = (int) substr($lastDo->do_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get the state machine instance for this delivery order.
     */
    public function stateMachine(): \App\Domain\Sales\DeliveryOrders\DeliveryOrderStateMachine
    {
        return \App\Domain\Sales\DeliveryOrders\DeliveryOrderStateMachine::fromDeliveryOrder($this);
    }

    /**
     * Transition this delivery order to a new status.
     */
    public function transitionTo(DocumentStatus $status, ?int $userId = null, array $context = []): self
    {
        $this->stateMachine()->transitionTo($status, array_merge(['user_id' => $userId], $context));

        return $this->refresh();
    }
}
