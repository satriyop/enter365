<?php

declare(strict_types=1);

namespace App\Contracts\Services\Domains;

use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Projects\Project;

/**
 * Interface for WorkOrder service operations.
 */
interface WorkOrderServiceInterface
{
    /**
     * Create a new work order.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): WorkOrder;

    /**
     * Create work order from a project.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromProject(Project $project, array $data): WorkOrder;

    /**
     * Create work order from a BOM.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromBom(Bom $bom, array $data): WorkOrder;

    /**
     * Create a sub work order from a parent.
     *
     * @param  array<string, mixed>  $data
     */
    public function createSubWorkOrder(WorkOrder $parent, array $data): WorkOrder;

    /**
     * Update an existing work order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(WorkOrder $wo, array $data): WorkOrder;

    /**
     * Delete a work order.
     */
    public function delete(WorkOrder $wo): bool;

    /**
     * Confirm a work order.
     */
    public function confirm(WorkOrder $wo, ?int $userId = null): WorkOrder;

    /**
     * Start a work order.
     */
    public function start(WorkOrder $wo, ?int $userId = null): WorkOrder;

    /**
     * Complete a work order.
     */
    public function complete(WorkOrder $wo, ?int $userId = null): WorkOrder;

    /**
     * Cancel a work order.
     */
    public function cancel(WorkOrder $wo, ?string $reason = null, ?int $userId = null): WorkOrder;

    /**
     * Record output produced.
     */
    public function recordOutput(WorkOrder $wo, float $quantity, float $scrapped = 0): WorkOrder;

    /**
     * Record material consumption.
     *
     * @param  array<string, mixed>  $consumptions
     */
    public function recordConsumption(WorkOrder $wo, array $consumptions): void;

    /**
     * Get cost summary for a work order.
     *
     * @return array<string, mixed>
     */
    public function getCostSummary(WorkOrder $wo): array;

    /**
     * Get material status for a work order.
     *
     * @return array<string, mixed>
     */
    public function getMaterialStatus(WorkOrder $wo): array;

    /**
     * Get work order statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null): array;
}
