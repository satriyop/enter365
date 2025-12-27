<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
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
