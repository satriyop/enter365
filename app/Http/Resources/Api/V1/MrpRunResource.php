<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MrpRunResource extends JsonResource
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
            'run_number' => $this->run_number,
            'name' => $this->name,
            'status' => $this->status,

            // Planning horizon
            'planning_horizon_start' => $this->planning_horizon_start?->toDateString(),
            'planning_horizon_end' => $this->planning_horizon_end?->toDateString(),

            // Parameters
            'parameters' => $this->parameters,

            // Summary counts
            'total_products_analyzed' => $this->total_products_analyzed,
            'total_demands' => $this->total_demands,
            'total_shortages' => $this->total_shortages,
            'total_purchase_suggestions' => $this->total_purchase_suggestions,
            'total_work_order_suggestions' => $this->total_work_order_suggestions,
            'total_subcontract_suggestions' => $this->total_subcontract_suggestions,

            // Meta
            'is_outdated' => $this->when($this->status === 'completed', fn () => $this->isOutdated()),
            'outdated_reason' => $this->when(
                $this->status === 'completed',
                fn () => $this->getOutdatedReason()
            ),

            'notes' => $this->notes,

            // Relationships
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->when($this->relationLoaded('warehouse'), function () {
                return $this->warehouse ? [
                    'id' => $this->warehouse->id,
                    'name' => $this->warehouse->name,
                ] : null;
            }),

            'demands' => MrpDemandResource::collection($this->whenLoaded('demands')),
            'suggestions' => MrpSuggestionResource::collection($this->whenLoaded('suggestions')),

            // Timestamps
            'completed_at' => $this->completed_at?->toIso8601String(),
            'applied_at' => $this->applied_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
