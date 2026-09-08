<?php

namespace App\Models\Inventory;

use App\Domain\Shared\DocumentNumbers;
use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\User;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransfer extends Model
{
    use Filterable, HasFactory, SoftDeletes;

    public const OPERATION_INTERNAL = 'internal';

    public const OPERATION_RECEIPT = 'receipt';

    public const OPERATION_DELIVERY = 'delivery';

    protected $fillable = [
        'transfer_number',
        'operation_type',
        'from_warehouse_id',
        'to_warehouse_id',
        'contact_id',
        'scheduled_date',
        'source_document',
        'status',
        'notes',
        'completed_at',
        'cancelled_at',
        'created_by',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (StockTransfer $transfer) {
            if (empty($transfer->transfer_number)) {
                $transfer->transfer_number = DocumentNumbers::generate(
                    'TRF-'.now()->format('Ym').'-',
                    'stock_transfers',
                    'transfer_number'
                );
            }
        });
    }

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'status' => DocumentStatus::class,
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public static function operationTypes(): array
    {
        return [
            self::OPERATION_INTERNAL,
            self::OPERATION_RECEIPT,
            self::OPERATION_DELIVERY,
        ];
    }

    /**
     * @return HasMany<StockTransferItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    public function isCompleted(): bool
    {
        return $this->status === DocumentStatus::Completed;
    }
}
