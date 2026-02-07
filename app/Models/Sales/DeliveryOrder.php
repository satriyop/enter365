<?php

namespace App\Models\Sales;

use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Traits\Filterable;
use App\Traits\HasStatusHistory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $do_number
 * @property int|null $invoice_id
 * @property int $contact_id
 * @property int $warehouse_id
 * @property Carbon $do_date
 * @property Carbon|null $shipping_date
 * @property Carbon|null $received_date
 * @property string|null $shipping_address
 * @property string|null $shipping_method
 * @property string|null $tracking_number
 * @property string|null $driver_name
 * @property string|null $vehicle_number
 * @property string|null $notes
 * @property DocumentStatus $status
 * @property string|null $received_by
 * @property string|null $delivery_notes
 * @property int|null $created_by
 * @property int|null $confirmed_by
 * @property Carbon|null $confirmed_at
 * @property int|null $shipped_by
 * @property Carbon|null $shipped_at
 * @property int|null $delivered_by
 * @property Carbon|null $delivered_at
 * @property int|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class DeliveryOrder extends Model
{
    use Filterable, HasFactory, HasStatusHistory, SoftDeletes;

    public const SHIPPING_METHODS = [
        'courier',
        'pickup',
        'own_delivery',
        'freight',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (DeliveryOrder $do) {
            if (empty($do->do_number)) {
                $prefix = 'DO-'.now()->format('Ym').'-';
                $do->do_number = \App\Domain\Shared\DocumentNumbers::generate(
                    $prefix,
                    'delivery_orders',
                    'do_number'
                );
            }
        });
    }

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
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
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
            'cancelled_at' => 'datetime',
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
