<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialConsumption extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'work_order_item_id',
        'product_id',
        'quantity_consumed',
        'quantity_scrapped',
        'scrap_reason',
        'unit',
        'unit_cost',
        'total_cost',
        'consumed_date',
        'batch_number',
        'consumed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_consumed' => 'decimal:4',
            'quantity_scrapped' => 'decimal:4',
            'unit_cost' => 'integer',
            'total_cost' => 'integer',
            'consumed_date' => 'date',
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
     * @return BelongsTo<WorkOrderItem, $this>
     */
    public function workOrderItem(): BelongsTo
    {
        return $this->belongsTo(WorkOrderItem::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function consumer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumed_by');
    }

    /**
     * Get total quantity (consumed + scrapped).
     */
    public function getTotalQuantity(): float
    {
        return (float) $this->quantity_consumed + (float) $this->quantity_scrapped;
    }

    /**
     * Calculate total cost.
     */
    public function calculateTotalCost(): void
    {
        $totalQty = $this->getTotalQuantity();
        $this->total_cost = (int) round($totalQty * $this->unit_cost);
    }
}
