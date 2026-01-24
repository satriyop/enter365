<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Manufacturing\MrpRun
 */
class MrpRunResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   run_number: string,
     *   name: string,
     *   status: string,
     *   planning_horizon_start: string|null,
     *   planning_horizon_end: string|null,
     *   parameters: array<string, mixed>|null,
     *   total_products_analyzed: int,
     *   total_demands: int,
     *   total_shortages: int,
     *   total_purchase_suggestions: int,
     *   total_work_order_suggestions: int,
     *   total_subcontract_suggestions: int,
     *   is_outdated?: bool,
     *   outdated_reason?: string|null,
     *   notes: string|null,
     *   warehouse_id: int|null,
     *   warehouse?: array{id: int, name: string}|null,
     *   demands?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   suggestions?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   completed_at: string|null,
     *   applied_at: string|null,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
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
