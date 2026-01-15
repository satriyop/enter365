<?php

declare(strict_types=1);

namespace App\Contracts\Services\Domains;

use App\Models\Projects\Project;
use App\Models\Projects\ProjectCost;
use App\Models\Projects\ProjectRevenue;
use App\Models\Sales\Quotation;

/**
 * Interface for Project service operations.
 */
interface ProjectServiceInterface
{
    /**
     * Create a new project.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Project;

    /**
     * Create a project from a quotation.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromQuotation(Quotation $quotation, array $data = []): Project;

    /**
     * Update a project.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Project $project, array $data): Project;

    /**
     * Delete a project.
     */
    public function delete(Project $project): bool;

    /**
     * Start a project.
     */
    public function start(Project $project): Project;

    /**
     * Put a project on hold.
     */
    public function putOnHold(Project $project, ?string $reason = null): Project;

    /**
     * Resume a project from hold.
     */
    public function resume(Project $project): Project;

    /**
     * Complete a project.
     */
    public function complete(Project $project): Project;

    /**
     * Cancel a project.
     */
    public function cancel(Project $project, ?string $reason = null): Project;

    /**
     * Update project progress percentage.
     */
    public function updateProgress(Project $project, float $percentage): Project;

    /**
     * Add a cost to a project.
     *
     * @param  array<string, mixed>  $data
     */
    public function addCost(Project $project, array $data): ProjectCost;

    /**
     * Update a project cost.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCost(ProjectCost $cost, array $data): ProjectCost;

    /**
     * Delete a project cost.
     */
    public function deleteCost(ProjectCost $cost): bool;

    /**
     * Add revenue to a project.
     *
     * @param  array<string, mixed>  $data
     */
    public function addRevenue(Project $project, array $data): ProjectRevenue;

    /**
     * Update a project revenue.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateRevenue(ProjectRevenue $revenue, array $data): ProjectRevenue;

    /**
     * Delete a project revenue.
     */
    public function deleteRevenue(ProjectRevenue $revenue): bool;

    /**
     * Get project summary.
     *
     * @return array<string, mixed>
     */
    public function getSummary(Project $project): array;

    /**
     * Get project statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null): array;
}
