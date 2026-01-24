<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Manufacturing\WorkOrders\WorkOrderStateMachine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Manufacturing\WorkOrder
 */
class WorkOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   wo_number: string,
     *   type: string,
     *   name: string,
     *   description: string|null,
     *   status: array{value: string, label: string, color: string},
     *   priority: string,
     *   quantity_ordered: float,
     *   quantity_completed: float,
     *   quantity_scrapped: float,
     *   completion_percentage: float,
     *   planned_start_date: string|null,
     *   planned_end_date: string|null,
     *   actual_start_date: string|null,
     *   actual_end_date: string|null,
     *   estimated_material_cost: int,
     *   estimated_labor_cost: int,
     *   estimated_overhead_cost: int,
     *   estimated_total_cost: int,
     *   actual_material_cost: int,
     *   actual_labor_cost: int,
     *   actual_overhead_cost: int,
     *   actual_total_cost: int,
     *   cost_variance: int,
     *   notes: string|null,
     *   project_id: int|null,
     *   project?: array{id: int, project_number: string, name: string}|null,
     *   bom_id: int|null,
     *   bom?: array{id: int, bom_number: string, name: string}|null,
     *   product_id: int|null,
     *   product?: array{id: int, sku: string, name: string}|null,
     *   warehouse_id: int,
     *   warehouse?: array{id: int, name: string}|null,
     *   parent_work_order_id: int|null,
     *   parent_work_order?: array{id: int, wo_number: string, name: string}|null,
     *   items?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   sub_work_orders?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   consumptions?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   workflow?: array<string, mixed>,
     *   status_history?: array<mixed>,
     *   actions: array{
     *     can_edit: bool,
     *     can_confirm: bool,
     *     can_start: bool,
     *     can_complete: bool,
     *     can_cancel: bool,
     *     can_delete: bool
     *   },
     *   confirmed_at: string|null,
     *   started_at: string|null,
     *   completed_at: string|null,
     *   cancelled_at: string|null,
     *   cancellation_reason: string|null,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        $stateMachine = WorkOrderStateMachine::fromWorkOrder($this->resource);

        return [
            'id' => $this->id,
            'wo_number' => $this->wo_number,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'priority' => $this->priority,

            // Quantities
            'quantity_ordered' => (float) $this->quantity_ordered,
            'quantity_completed' => (float) $this->quantity_completed,
            'quantity_scrapped' => (float) $this->quantity_scrapped,
            'completion_percentage' => $this->getCompletionPercentage(),

            // Dates
            'planned_start_date' => $this->planned_start_date?->toDateString(),
            'planned_end_date' => $this->planned_end_date?->toDateString(),
            'actual_start_date' => $this->actual_start_date?->toDateString(),
            'actual_end_date' => $this->actual_end_date?->toDateString(),

            // Costs
            'estimated_material_cost' => $this->estimated_material_cost,
            'estimated_labor_cost' => $this->estimated_labor_cost,
            'estimated_overhead_cost' => $this->estimated_overhead_cost,
            'estimated_total_cost' => $this->estimated_total_cost,
            'actual_material_cost' => $this->actual_material_cost,
            'actual_labor_cost' => $this->actual_labor_cost,
            'actual_overhead_cost' => $this->actual_overhead_cost,
            'actual_total_cost' => $this->actual_total_cost,
            'cost_variance' => $this->cost_variance,

            'notes' => $this->notes,

            // Relationships
            'project_id' => $this->project_id,
            'project' => $this->when($this->relationLoaded('project'), function () {
                return $this->project ? [
                    'id' => $this->project->id,
                    'project_number' => $this->project->project_number,
                    'name' => $this->project->name,
                ] : null;
            }),

            'bom_id' => $this->bom_id,
            'bom' => $this->when($this->relationLoaded('bom'), function () {
                return $this->bom ? [
                    'id' => $this->bom->id,
                    'bom_number' => $this->bom->bom_number,
                    'name' => $this->bom->name,
                ] : null;
            }),

            'product_id' => $this->product_id,
            'product' => $this->when($this->relationLoaded('product'), function () {
                return $this->product ? [
                    'id' => $this->product->id,
                    'sku' => $this->product->sku,
                    'name' => $this->product->name,
                ] : null;
            }),

            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->when($this->relationLoaded('warehouse'), function () {
                return $this->warehouse ? [
                    'id' => $this->warehouse->id,
                    'name' => $this->warehouse->name,
                ] : null;
            }),

            'parent_work_order_id' => $this->parent_work_order_id,
            'parent_work_order' => $this->when($this->relationLoaded('parentWorkOrder'), function () {
                return $this->parentWorkOrder ? [
                    'id' => $this->parentWorkOrder->id,
                    'wo_number' => $this->parentWorkOrder->wo_number,
                    'name' => $this->parentWorkOrder->name,
                ] : null;
            }),

            'items' => WorkOrderItemResource::collection($this->whenLoaded('items')),
            'sub_work_orders' => WorkOrderResource::collection($this->whenLoaded('subWorkOrders')),
            'consumptions' => MaterialConsumptionResource::collection($this->whenLoaded('consumptions')),

            // Workflow information (optional)
            'workflow' => $this->when(
                $request->boolean('include_workflow'),
                fn () => $stateMachine->getWorkflowMetadata()
            ),

            // Status history (optional)
            'status_history' => $this->when(
                $request->boolean('include_history'),
                fn () => $this->getStatusTimeline()
            ),

            // Available actions
            'actions' => [
                'can_edit' => $stateMachine->canEdit(),
                'can_confirm' => $stateMachine->canConfirm(),
                'can_start' => $stateMachine->canStart(),
                'can_complete' => $stateMachine->canComplete(),
                'can_cancel' => $stateMachine->canCancel(),
                'can_delete' => $stateMachine->canDelete(),
            ],

            // Timestamps
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
