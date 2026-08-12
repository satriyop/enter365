<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Projects\ProjectServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectCost;
use App\Models\Projects\ProjectRevenue;
use App\Models\Sales\Quotation;
use App\Services\Base\BaseService;

class ProjectService extends BaseService implements ProjectServiceInterface
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a new project.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Project
    {
        return $this->executeInTransaction('create', function () use ($data) {
            $project = new Project($data);
            $project->save();

            return $project->fresh(['contact', 'manager']);
        }, ['contact_id' => $data['contact_id'] ?? null]);
    }

    /**
     * Create project from quotation.
     */
    public function createFromQuotation(Quotation $quotation, array $data = []): Project
    {
        return $this->executeInTransaction('create_from_quotation', function () use ($quotation, $data) {
            $project = new Project([
                'name' => $data['name'] ?? 'Project: '.$quotation->quotation_number,
                'description' => $data['description'] ?? $quotation->notes,
                'contact_id' => $quotation->contact_id,
                'quotation_id' => $quotation->id,
                'contract_amount' => $quotation->total_amount,
                'budget_amount' => $data['budget_amount'] ?? $quotation->total_amount,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'priority' => $data['priority'] ?? Project::PRIORITY_NORMAL,
                'location' => $data['location'] ?? null,
                'notes' => $data['notes'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
                'created_by' => $data['created_by'] ?? $this->getUserId(),
            ]);
            $project->status = DocumentStatus::Planning;
            $project->save();

            // Link quotation to project
            $quotation->update(['project_id' => $project->id]);

            return $project->fresh(['contact', 'quotation', 'manager']);
        }, ['quotation_id' => $quotation->id]);
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
        if (! $project->isDeletable()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotDelete($project, 'Only draft or planning projects can be deleted.');
        }

        return $this->executeInTransaction('delete', function () use ($project) {
            $project->costs()->delete();
            $project->revenues()->delete();

            return $project->delete();
        }, ['project_id' => $project->id]);
    }

    /**
     * Start a project.
     */
    public function start(Project $project, ?int $userId = null): Project
    {
        if (! $project->stateMachine()->canStart()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Project',
                'dimulai',
                $project->status->value,
                'planning'
            );
        }

        $project->transitionTo(DocumentStatus::InProgress, $userId);

        return $project->fresh();
    }

    /**
     * Put project on hold.
     */
    public function putOnHold(Project $project, ?string $reason = null, ?int $userId = null): Project
    {
        if (! $project->stateMachine()->canPutOnHold()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Project',
                'ditunda',
                $project->status->value,
                'in progress'
            );
        }

        if ($reason) {
            $project->notes = ($project->notes ? $project->notes."\n" : '').'Ditunda: '.$reason;
            $project->save();
        }

        $project->transitionTo(DocumentStatus::OnHold, $userId, [
            'hold_reason' => $reason,
        ]);

        return $project->fresh();
    }

    /**
     * Cancel a project.
     */
    public function cancel(Project $project, ?string $reason = null, ?int $userId = null): Project
    {
        if (! $project->stateMachine()->canCancel()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Project',
                'dibatalkan',
                $project->status->value,
                'draft, planning, in progress, atau on hold'
            );
        }

        if ($reason) {
            $project->notes = ($project->notes ? $project->notes."\n" : '').'Dibatalkan: '.$reason;
            $project->save();
        }

        $project->transitionTo(DocumentStatus::Cancelled, $userId, [
            'cancellation_reason' => $reason,
        ]);

        return $project->fresh();
    }

    /**
     * Resume a project.
     */
    public function resume(Project $project, ?int $userId = null): Project
    {
        if (! $project->stateMachine()->canResume()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Project',
                'dilanjutkan',
                $project->status->value,
                'on hold'
            );
        }

        $project->transitionTo(DocumentStatus::InProgress, $userId);

        return $project->fresh();
    }

    /**
     * Complete a project.
     */
    public function complete(Project $project, ?int $userId = null): Project
    {
        if (! $project->stateMachine()->canComplete()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Project',
                'diselesaikan',
                $project->status->value,
                'in progress'
            );
        }

        return $this->executeInTransaction('complete', function () use ($project, $userId) {
            $project->transitionTo(DocumentStatus::Completed, $userId);

            $project->refresh();
            $project->calculateFinancials();
            $project->save();

            return $project->fresh();
        }, ['project_id' => $project->id]);
    }

    /**
     * Update project progress.
     */
    public function updateProgress(Project $project, float $percentage): Project
    {
        if ($project->status !== DocumentStatus::InProgress) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Project',
                'update progress',
                $project->status->value,
                'in progress'
            );
        }

        $project->progress_percentage = (string) min(100, max(0, $percentage));
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
        return $this->executeInTransaction('add_cost', function () use ($project, $data) {
            $cost = new ProjectCost($data);
            $cost->project_id = $project->id;
            $cost->calculateTotalCost();
            $cost->save();

            // Update project totals
            $project->calculateFinancials();
            $project->save();

            return $cost;
        }, ['project_id' => $project->id]);
    }

    /**
     * Update project cost.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCost(ProjectCost $cost, array $data): ProjectCost
    {
        return $this->executeInTransaction('update_cost', function () use ($cost, $data) {
            $cost->fill($data);
            $cost->calculateTotalCost();
            $cost->save();

            // Update project totals
            $cost->project->calculateFinancials();
            $cost->project->save();

            return $cost;
        }, ['cost_id' => $cost->id]);
    }

    /**
     * Delete project cost.
     */
    public function deleteCost(ProjectCost $cost): bool
    {
        return $this->executeInTransaction('delete_cost', function () use ($cost) {
            $project = $cost->project;
            $cost->delete();

            // Update project totals
            $project->calculateFinancials();
            $project->save();

            return true;
        }, ['cost_id' => $cost->id]);
    }

    /**
     * Ensure the cost belongs to the given project.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function ensureCostBelongsToProject(Project $project, ProjectCost $cost): void
    {
        if ($cost->project_id !== $project->id) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)
                ->setModel(ProjectCost::class, [$cost->id]);
        }
    }

    /**
     * Add revenue to project.
     *
     * @param  array<string, mixed>  $data
     */
    public function addRevenue(Project $project, array $data): ProjectRevenue
    {
        return $this->executeInTransaction('add_revenue', function () use ($project, $data) {
            $revenue = new ProjectRevenue($data);
            $revenue->project_id = $project->id;
            $revenue->save();

            // Update project totals
            $project->calculateFinancials();
            $project->save();

            return $revenue;
        }, ['project_id' => $project->id]);
    }

    /**
     * Update project revenue.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateRevenue(ProjectRevenue $revenue, array $data): ProjectRevenue
    {
        return $this->executeInTransaction('update_revenue', function () use ($revenue, $data) {
            $revenue->fill($data);
            $revenue->save();

            // Update project totals
            $revenue->project->calculateFinancials();
            $revenue->project->save();

            return $revenue;
        }, ['revenue_id' => $revenue->id]);
    }

    /**
     * Delete project revenue.
     */
    public function deleteRevenue(ProjectRevenue $revenue): bool
    {
        return $this->executeInTransaction('delete_revenue', function () use ($revenue) {
            $project = $revenue->project;
            $revenue->delete();

            // Update project totals
            $project->calculateFinancials();
            $project->save();

            return true;
        }, ['revenue_id' => $revenue->id]);
    }

    /**
     * Ensure the revenue belongs to the given project.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function ensureRevenueBelongsToProject(Project $project, ProjectRevenue $revenue): void
    {
        if ($revenue->project_id !== $project->id) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)
                ->setModel(ProjectRevenue::class, [$revenue->id]);
        }
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
        $activeProjects = $projects->where('status', DocumentStatus::InProgress);

        return [
            'total_count' => $projects->count(),
            'by_status' => [
                'draft' => $projects->where('status', DocumentStatus::Draft)->count(),
                'planning' => $projects->where('status', DocumentStatus::Planning)->count(),
                'in_progress' => $projects->where('status', DocumentStatus::InProgress)->count(),
                'on_hold' => $projects->where('status', DocumentStatus::OnHold)->count(),
                'completed' => $projects->where('status', DocumentStatus::Completed)->count(),
                'cancelled' => $projects->where('status', DocumentStatus::Cancelled)->count(),
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
