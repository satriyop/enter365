<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialRequisitionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_requisition_id',
        'work_order_item_id',
        'product_id',
        'quantity_requested',
        'quantity_approved',
        'quantity_issued',
        'quantity_pending',
        'unit',
        'unit_cost',
        'estimated_total_cost',
        'warehouse_location',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_requested' => 'decimal:4',
            'quantity_approved' => 'decimal:4',
            'quantity_issued' => 'decimal:4',
            'quantity_pending' => 'decimal:4',
            'unit_cost' => 'integer',
            'estimated_total_cost' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MaterialRequisition, $this>
     */
    public function materialRequisition(): BelongsTo
    {
        return $this->belongsTo(MaterialRequisition::class);
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
     * Check if fully issued.
     */
    public function isFullyIssued(): bool
    {
        return $this->quantity_pending <= 0;
    }

    /**
     * Calculate pending quantity.
     */
    public function calculatePendingQuantity(): void
    {
        $this->quantity_pending = max(0, (float) $this->quantity_approved - (float) $this->quantity_issued);
    }

    /**
     * Calculate estimated total cost based on quantity requested and unit cost.
     */
    public function calculateEstimatedTotalCost(): void
    {
        $this->estimated_total_cost = (int) round((float) $this->quantity_requested * $this->unit_cost);
    }

    /**
     * Fill cost from product.
     */
    public function fillCostFromProduct(Product $product): void
    {
        $this->unit_cost = $product->purchase_price ?? $product->selling_price ?? 0;
        $this->calculateEstimatedTotalCost();
    }
}
