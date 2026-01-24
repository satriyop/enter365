<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Projects\Project
 */
class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   project_number: string,
     *   name: string,
     *   description: string|null,
     *   contact_id: int,
     *   contact?: array{id: int, name: string, phone: string|null},
     *   quotation_id: int|null,
     *   quotation?: array{id: int, quotation_number: string, total_amount: int},
     *   start_date: string|null,
     *   end_date: string|null,
     *   actual_start_date: string|null,
     *   actual_end_date: string|null,
     *   status: string,
     *   budget_amount: int,
     *   contract_amount: int,
     *   total_cost: int,
     *   total_revenue: int,
     *   gross_profit: int,
     *   profit_margin: float,
     *   progress_percentage: float,
     *   priority: string,
     *   location: string|null,
     *   notes: string|null,
     *   budget_utilization: float,
     *   budget_variance: int,
     *   is_over_budget: bool,
     *   is_overdue: bool,
     *   days_until_deadline: int|null,
     *   duration_days: int,
     *   costs?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   costs_count?: int,
     *   revenues?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   revenues_count?: int,
     *   cost_breakdown?: array<string, mixed>,
     *   manager_id: int|null,
     *   manager?: array{id: int, name: string},
     *   created_by: int|null,
     *   creator?: array{id: int, name: string},
     *   created_at: string,
     *   updated_at: string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_number' => $this->project_number,
            'name' => $this->name,
            'description' => $this->description,
            'contact_id' => $this->contact_id,
            'contact' => $this->whenLoaded('contact', fn () => [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
                'phone' => $this->contact->phone,
            ]),
            'quotation_id' => $this->quotation_id,
            'quotation' => $this->whenLoaded('quotation', fn () => [
                'id' => $this->quotation->id,
                'quotation_number' => $this->quotation->quotation_number,
                'total_amount' => $this->quotation->total_amount,
            ]),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'actual_start_date' => $this->actual_start_date?->format('Y-m-d'),
            'actual_end_date' => $this->actual_end_date?->format('Y-m-d'),
            'status' => $this->status,
            'budget_amount' => $this->budget_amount,
            'contract_amount' => $this->contract_amount,
            'total_cost' => $this->total_cost,
            'total_revenue' => $this->total_revenue,
            'gross_profit' => $this->gross_profit,
            'profit_margin' => (float) $this->profit_margin,
            'progress_percentage' => (float) $this->progress_percentage,
            'priority' => $this->priority,
            'location' => $this->location,
            'notes' => $this->notes,
            'budget_utilization' => $this->getBudgetUtilization(),
            'budget_variance' => $this->getBudgetVariance(),
            'is_over_budget' => $this->isOverBudget(),
            'is_overdue' => $this->isOverdue(),
            'days_until_deadline' => $this->getDaysUntilDeadline(),
            'duration_days' => $this->getDurationDays(),
            'costs' => ProjectCostResource::collection($this->whenLoaded('costs')),
            'costs_count' => $this->whenCounted('costs'),
            'revenues' => ProjectRevenueResource::collection($this->whenLoaded('revenues')),
            'revenues_count' => $this->whenCounted('revenues'),
            'cost_breakdown' => $this->when($this->relationLoaded('costs'), fn () => $this->getCostBreakdown()),
            'manager_id' => $this->manager_id,
            'manager' => $this->whenLoaded('manager', fn () => [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
            ]),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
