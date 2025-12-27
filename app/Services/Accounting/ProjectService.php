<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Project;
use App\Models\Accounting\ProjectCost;
use App\Models\Accounting\ProjectRevenue;
use App\Models\Accounting\Quotation;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    /**
     * Create a new project.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Project
    {
        return DB::transaction(function () use ($data) {
            $project = new Project($data);
            $project->project_number = Project::generateProjectNumber();
            $project->save();

            return $project->fresh(['contact', 'manager']);
        });
    }

    /**
     * Create project from quotation.
     */
    public function createFromQuotation(Quotation $quotation, array $data = []): Project
    {
        return DB::transaction(function () use ($quotation, $data) {
            $project = new Project([
                'name' => $data['name'] ?? 'Project: '.$quotation->quotation_number,
                'description' => $data['description'] ?? $quotation->notes,
                'contact_id' => $quotation->contact_id,
                'quotation_id' => $quotation->id,
                'contract_amount' => $quotation->total,
                'budget_amount' => $data['budget_amount'] ?? $quotation->total,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'priority' => $data['priority'] ?? Project::PRIORITY_NORMAL,
                'location' => $data['location'] ?? null,
                'notes' => $data['notes'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
                'created_by' => $data['created_by'] ?? auth()->id(),
            ]);
            $project->project_number = Project::generateProjectNumber();
            $project->status = Project::STATUS_PLANNING;
            $project->save();

            // Link quotation to project
            $quotation->update(['project_id' => $project->id]);

            return $project->fresh(['contact', 'quotation', 'manager']);
        });
    }

    /**
     * Update a project.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Project $project, array $data): Project
    {
        $project->fill($data);
        $project->save();

        return $project->fresh(['contact', 'manager', 'quotation']);
    }

    /**
     * Delete a project.
     */
    public function delete(Project $project): bool
    {
        if (! $project->canBeEdited()) {
            throw new \InvalidArgumentException('Only draft or planning projects can be deleted.');
        }

        return DB::transaction(function () use ($project) {
            $project->costs()->delete();
            $project->revenues()->delete();

            return $project->delete();
        });
    }

    /**
     * Start a project.
     */
    public function start(Project $project): Project
    {
        if (! $project->canBeStarted()) {
            throw new \InvalidArgumentException('Project cannot be started.');
        }

        $project->status = Project::STATUS_IN_PROGRESS;
        $project->actual_start_date = now();
        $project->save();

        return $project->fresh();
    }

    /**
     * Put project on hold.
     */
    public function putOnHold(Project $project, ?string $reason = null): Project
    {
        if (! $project->canBePutOnHold()) {
            throw new \InvalidArgumentException('Only in-progress projects can be put on hold.');
        }

        $project->status = Project::STATUS_ON_HOLD;
        if ($reason) {
            $project->notes = ($project->notes ? $project->notes."\n" : '').'Ditunda: '.$reason;
        }
        $project->save();

        return $project->fresh();
    }

    /**
     * Resume a project.
     */
    public function resume(Project $project): Project
    {
        if (! $project->canBeResumed()) {
            throw new \InvalidArgumentException('Only on-hold projects can be resumed.');
        }

        $project->status = Project::STATUS_IN_PROGRESS;
        $project->save();

        return $project->fresh();
    }

    /**
     * Complete a project.
     */
    public function complete(Project $project): Project
    {
        if (! $project->canBeCompleted()) {
            throw new \InvalidArgumentException('Only in-progress projects can be completed.');
        }

        $project->status = Project::STATUS_COMPLETED;
        $project->actual_end_date = now();
        $project->progress_percentage = 100;
        $project->calculateFinancials();
        $project->save();

        return $project->fresh();
    }

    /**
     * Cancel a project.
     */
    public function cancel(Project $project, ?string $reason = null): Project
    {
        if (! $project->canBeCancelled()) {
            throw new \InvalidArgumentException('Project cannot be cancelled.');
        }

        $project->status = Project::STATUS_CANCELLED;
        if ($reason) {
            $project->notes = ($project->notes ? $project->notes."\n" : '').'Dibatalkan: '.$reason;
        }
        $project->save();

        return $project->fresh();
    }

    /**
     * Update project progress.
     */
    public function updateProgress(Project $project, float $percentage): Project
    {
        if ($project->status !== Project::STATUS_IN_PROGRESS) {
            throw new \InvalidArgumentException('Progress can only be updated for in-progress projects.');
        }

        $project->progress_percentage = min(100, max(0, $percentage));
        $project->save();

        return $project->fresh();
    }

    /**
     * Add cost to project.
     *
     * @param  array<string, mixed>  $data
     */
    public function addCost(Project $project, array $data): ProjectCost
    {
        return DB::transaction(function () use ($project, $data) {
            $cost = new ProjectCost($data);
            $cost->project_id = $project->id;
            $cost->calculateTotalCost();
            $cost->save();

            // Update project totals
            $project->calculateFinancials();
            $project->save();

            return $cost;
        });
    }

    /**
     * Update project cost.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCost(ProjectCost $cost, array $data): ProjectCost
    {
        return DB::transaction(function () use ($cost, $data) {
            $cost->fill($data);
            $cost->calculateTotalCost();
            $cost->save();

            // Update project totals
            $cost->project->calculateFinancials();
            $cost->project->save();

            return $cost;
        });
    }

    /**
     * Delete project cost.
     */
    public function deleteCost(ProjectCost $cost): bool
    {
        return DB::transaction(function () use ($cost) {
            $project = $cost->project;
            $cost->delete();

            // Update project totals
            $project->calculateFinancials();
            $project->save();

            return true;
        });
    }

    /**
     * Add revenue to project.
     *
     * @param  array<string, mixed>  $data
     */
    public function addRevenue(Project $project, array $data): ProjectRevenue
    {
        return DB::transaction(function () use ($project, $data) {
            $revenue = new ProjectRevenue($data);
            $revenue->project_id = $project->id;
            $revenue->save();

            // Update project totals
            $project->calculateFinancials();
            $project->save();

            return $revenue;
        });
    }

    /**
     * Update project revenue.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateRevenue(ProjectRevenue $revenue, array $data): ProjectRevenue
    {
        return DB::transaction(function () use ($revenue, $data) {
            $revenue->fill($data);
            $revenue->save();

            // Update project totals
            $revenue->project->calculateFinancials();
            $revenue->project->save();

            return $revenue;
        });
    }

    /**
     * Delete project revenue.
     */
    public function deleteRevenue(ProjectRevenue $revenue): bool
    {
        return DB::transaction(function () use ($revenue) {
            $project = $revenue->project;
            $revenue->delete();

            // Update project totals
            $project->calculateFinancials();
            $project->save();

            return true;
        });
    }

    /**
     * Get project summary.
     *
     * @return array<string, mixed>
     */
    public function getSummary(Project $project): array
    {
        $costBreakdown = $project->getCostBreakdown();

        return [
            'project_id' => $project->id,
            'project_number' => $project->project_number,
            'status' => $project->status,
            'progress' => $project->progress_percentage,
            'budget' => [
                'amount' => $project->budget_amount,
                'utilization' => $project->getBudgetUtilization(),
                'variance' => $project->getBudgetVariance(),
                'is_over' => $project->isOverBudget(),
            ],
            'financials' => [
                'contract_amount' => $project->contract_amount,
                'total_cost' => $project->total_cost,
                'total_revenue' => $project->total_revenue,
                'gross_profit' => $project->gross_profit,
                'profit_margin' => $project->profit_margin,
            ],
            'cost_breakdown' => $costBreakdown,
            'timeline' => [
                'start_date' => $project->start_date?->format('Y-m-d'),
                'end_date' => $project->end_date?->format('Y-m-d'),
                'actual_start' => $project->actual_start_date?->format('Y-m-d'),
                'actual_end' => $project->actual_end_date?->format('Y-m-d'),
                'duration_days' => $project->getDurationDays(),
                'days_until_deadline' => $project->getDaysUntilDeadline(),
                'is_overdue' => $project->isOverdue(),
            ],
        ];
    }

    /**
     * Get project statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null): array
    {
        $query = Project::query();

        if ($startDate) {
            $query->where('start_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('start_date', '<=', $endDate);
        }

        $projects = $query->get();
        $activeProjects = $projects->where('status', Project::STATUS_IN_PROGRESS);

        return [
            'total_count' => $projects->count(),
            'by_status' => [
                'draft' => $projects->where('status', Project::STATUS_DRAFT)->count(),
                'planning' => $projects->where('status', Project::STATUS_PLANNING)->count(),
                'in_progress' => $projects->where('status', Project::STATUS_IN_PROGRESS)->count(),
                'on_hold' => $projects->where('status', Project::STATUS_ON_HOLD)->count(),
                'completed' => $projects->where('status', Project::STATUS_COMPLETED)->count(),
                'cancelled' => $projects->where('status', Project::STATUS_CANCELLED)->count(),
            ],
            'by_priority' => [
                'low' => $projects->where('priority', Project::PRIORITY_LOW)->count(),
                'normal' => $projects->where('priority', Project::PRIORITY_NORMAL)->count(),
                'high' => $projects->where('priority', Project::PRIORITY_HIGH)->count(),
                'urgent' => $projects->where('priority', Project::PRIORITY_URGENT)->count(),
            ],
            'financials' => [
                'total_contract_value' => $projects->sum('contract_amount'),
                'total_cost' => $projects->sum('total_cost'),
                'total_revenue' => $projects->sum('total_revenue'),
                'total_profit' => $projects->sum('gross_profit'),
            ],
            'active_projects' => [
                'count' => $activeProjects->count(),
                'overdue' => $activeProjects->filter(fn ($p) => $p->isOverdue())->count(),
                'over_budget' => $activeProjects->filter(fn ($p) => $p->isOverBudget())->count(),
            ],
        ];
    }
}
