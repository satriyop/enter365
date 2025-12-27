<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MrpSuggestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mrp_run_id' => $this->mrp_run_id,

            // Suggestion type and action
            'suggestion_type' => $this->suggestion_type,
            'action' => $this->action,
            'status' => $this->status,
            'priority' => $this->priority,

            // Product
            'product_id' => $this->product_id,
            'product' => $this->when($this->relationLoaded('product'), function () {
                return $this->product ? [
                    'id' => $this->product->id,
                    'sku' => $this->product->sku,
                    'name' => $this->product->name,
                    'unit' => $this->product->unit,
                    'procurement_type' => $this->product->procurement_type,
                ] : null;
            }),

            // Dates
            'suggested_order_date' => $this->suggested_order_date?->toDateString(),
            'suggested_due_date' => $this->suggested_due_date?->toDateString(),

            // Quantities
            'quantity_required' => (float) $this->quantity_required,
            'suggested_quantity' => (float) $this->suggested_quantity,
            'adjusted_quantity' => $this->adjusted_quantity ? (float) $this->adjusted_quantity : null,
            'effective_quantity' => $this->getEffectiveQuantity(),

            // Cost estimates
            'estimated_unit_cost' => $this->estimated_unit_cost,
            'estimated_total_cost' => $this->estimated_total_cost,

            // Suggested supplier
            'suggested_supplier_id' => $this->suggested_supplier_id,
            'suggested_supplier' => $this->when($this->relationLoaded('suggestedSupplier'), function () {
                return $this->suggestedSupplier ? [
                    'id' => $this->suggestedSupplier->id,
                    'name' => $this->suggestedSupplier->name,
                ] : null;
            }),

            // Suggested warehouse
            'suggested_warehouse_id' => $this->suggested_warehouse_id,
            'suggested_warehouse' => $this->when($this->relationLoaded('suggestedWarehouse'), function () {
                return $this->suggestedWarehouse ? [
                    'id' => $this->suggestedWarehouse->id,
                    'name' => $this->suggestedWarehouse->name,
                ] : null;
            }),

            // Reason and notes
            'reason' => $this->reason,
            'notes' => $this->notes,

            // Conversion info
            'converted_to_type' => $this->converted_to_type ? class_basename($this->converted_to_type) : null,
            'converted_to_id' => $this->converted_to_id,
            'converted_at' => $this->converted_at?->toIso8601String(),

            // Flags
            'can_be_accepted' => $this->canBeAccepted(),
            'can_be_rejected' => $this->canBeRejected(),
            'can_be_converted' => $this->canBeConverted(),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
