<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders;

use App\Contracts\Events\EventDispatcherInterface;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;

/**
 * Factory for creating work order domain objects with proper dependency injection.
 *
 * Centralizes domain object creation for WorkOrder, providing a single entry point
 * for state machine access, cost calculations, and progress tracking.
 *
 * @example
 * // In a service:
 * public function __construct(private WorkOrderDomainFactory $domainFactory) {}
 *
 * public function confirmWorkOrder(WorkOrder $workOrder): WorkOrder
 * {
 *     $stateMachine = $this->domainFactory->stateMachine($workOrder);
 *     if (!$stateMachine->canConfirm()) {
 *         throw new InvalidArgumentException('Cannot confirm work order');
 *     }
 *     $stateMachine->transitionTo(DocumentStatus::Confirmed);
 * }
 */
class WorkOrderDomainFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Create a state machine for the given work order.
     *
     * The state machine is created fresh each time since it holds
     * work order-specific state.
     */
    public function stateMachine(WorkOrder $workOrder): WorkOrderStateMachine
    {
        return WorkOrderStateMachine::fromWorkOrder($workOrder, $this->eventDispatcher);
    }

    /**
     * Calculate estimated costs from work order items.
     *
     * Always queries items to avoid stale relation state after item mutations.
     *
     * @return WorkOrderCosts Calculated estimated costs
     */
    public function calculateEstimatedCosts(WorkOrder $workOrder): WorkOrderCosts
    {
        $items = $workOrder->items()->get();

        $materialCost = (int) $items
            ->where('type', WorkOrderItem::TYPE_MATERIAL)
            ->sum('total_estimated_cost');

        $laborCost = (int) $items
            ->where('type', WorkOrderItem::TYPE_LABOR)
            ->sum('total_estimated_cost');

        $overheadCost = (int) $items
            ->where('type', WorkOrderItem::TYPE_OVERHEAD)
            ->sum('total_estimated_cost');

        return new WorkOrderCosts($materialCost, $laborCost, $overheadCost);
    }

    /**
     * Calculate actual costs from work order items.
     *
     * Always queries items to avoid stale relation state after item mutations.
     *
     * @return WorkOrderCosts Calculated actual costs
     */
    public function calculateActualCosts(WorkOrder $workOrder): WorkOrderCosts
    {
        $items = $workOrder->items()->get();

        $materialCost = (int) $items
            ->where('type', WorkOrderItem::TYPE_MATERIAL)
            ->sum('total_actual_cost');

        $laborCost = (int) $items
            ->where('type', WorkOrderItem::TYPE_LABOR)
            ->sum('total_actual_cost');

        $overheadCost = (int) $items
            ->where('type', WorkOrderItem::TYPE_OVERHEAD)
            ->sum('total_actual_cost');

        return new WorkOrderCosts($materialCost, $laborCost, $overheadCost);
    }

    /**
     * Apply estimated costs to a work order (without saving).
     *
     * Updates the work order's estimated cost fields based on items.
     */
    public function applyEstimatedCosts(WorkOrder $workOrder): WorkOrder
    {
        $costs = $this->calculateEstimatedCosts($workOrder);

        $workOrder->estimated_material_cost = $costs->materialCost;
        $workOrder->estimated_labor_cost = $costs->laborCost;
        $workOrder->estimated_overhead_cost = $costs->overheadCost;
        $workOrder->estimated_total_cost = $costs->totalCost;

        return $workOrder;
    }

    /**
     * Apply actual costs to a work order (without saving).
     *
     * Updates the work order's actual cost fields and variance.
     */
    public function applyActualCosts(WorkOrder $workOrder): WorkOrder
    {
        $actualCosts = $this->calculateActualCosts($workOrder);

        $workOrder->actual_material_cost = $actualCosts->materialCost;
        $workOrder->actual_labor_cost = $actualCosts->laborCost;
        $workOrder->actual_overhead_cost = $actualCosts->overheadCost;
        $workOrder->actual_total_cost = $actualCosts->totalCost;
        $workOrder->cost_variance = $workOrder->estimated_total_cost - $actualCosts->totalCost;

        return $workOrder;
    }

    /**
     * Get completion percentage for a work order.
     */
    public function getCompletionPercentage(WorkOrder $workOrder): float
    {
        if ($workOrder->quantity_ordered <= 0) {
            return 0.0;
        }

        return round(
            ((float) $workOrder->quantity_completed / (float) $workOrder->quantity_ordered) * 100,
            2
        );
    }

    /**
     * Get remaining quantity to produce.
     */
    public function getRemainingQuantity(WorkOrder $workOrder): float
    {
        return max(0, (float) $workOrder->quantity_ordered - (float) $workOrder->quantity_completed);
    }

    /**
     * Check if work order is on schedule.
     *
     * Returns true if:
     * - Work order has not started yet and planned_start_date is in the future
     * - Work order is in progress and actual_end_date is before or on planned_end_date
     * - Work order is completed
     */
    public function isOnSchedule(WorkOrder $workOrder): bool
    {
        $status = $workOrder->status;
        $statusValue = $status instanceof \App\Enums\DocumentStatus ? $status->value : (string) $status;
        $plannedStart = $workOrder->planned_start_date;
        $plannedEnd = $workOrder->planned_end_date;
        $actualEnd = $workOrder->actual_end_date;

        if ($statusValue === 'completed') {
            // Check if completed before planned end date
            if ($actualEnd instanceof \Carbon\Carbon && $plannedEnd instanceof \Carbon\Carbon) {
                return $actualEnd <= $plannedEnd;
            }

            return true;
        }

        if ($statusValue === 'draft' || $statusValue === 'confirmed') {
            // Not started yet, check if we're past planned start
            return ! ($plannedStart instanceof \Carbon\Carbon && $plannedStart->isPast());
        }

        if ($statusValue === 'in_progress') {
            // In progress, check if we're past planned end
            return ! ($plannedEnd instanceof \Carbon\Carbon && $plannedEnd->isPast());
        }

        return true;
    }

    /**
     * Get days until planned end date (negative if overdue).
     */
    public function getDaysUntilDue(WorkOrder $workOrder): int
    {
        $plannedEnd = $workOrder->planned_end_date;

        if (! $plannedEnd instanceof \Carbon\Carbon) {
            return 0;
        }

        return (int) now()->diffInDays($plannedEnd, false);
    }

    /**
     * Calculate cost variance percentage.
     *
     * Positive = under budget, Negative = over budget.
     */
    public function getCostVariancePercentage(WorkOrder $workOrder): float
    {
        if ($workOrder->estimated_total_cost === 0) {
            return 0.0;
        }

        return round(
            (($workOrder->cost_variance ?? 0) / $workOrder->estimated_total_cost) * 100,
            2
        );
    }
}
