<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\Manufacturing;

use App\Contracts\Repositories\RepositoryInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\WorkOrder;
use Illuminate\Support\Collection;

/**
 * Repository interface for WorkOrder entities.
 *
 * @extends RepositoryInterface<WorkOrder>
 */
interface WorkOrderRepositoryInterface extends RepositoryInterface
{
    /**
     * Find work orders by status.
     *
     * @return Collection<int, WorkOrder>
     */
    public function findByStatus(DocumentStatus $status): Collection;

    /**
     * Find work orders for project.
     *
     * @return Collection<int, WorkOrder>
     */
    public function findByProject(int $projectId): Collection;

    /**
     * Find work orders for product.
     *
     * @return Collection<int, WorkOrder>
     */
    public function findByProduct(int $productId): Collection;

    /**
     * Find in-progress work orders.
     *
     * @return Collection<int, WorkOrder>
     */
    public function findInProgress(): Collection;

    /**
     * Find overdue work orders (past planned end date).
     *
     * @return Collection<int, WorkOrder>
     */
    public function findOverdue(): Collection;

    /**
     * Get work order with all relations.
     */
    public function findWithRelations(int $id): ?WorkOrder;

    /**
     * Get material requirements for work order.
     *
     * @return Collection<int, array{product_id: int, quantity: float, unit: string}>
     */
    public function getMaterialRequirements(int $workOrderId): Collection;

    /**
     * Get statistics for date range.
     *
     * @return array{total: int, completed: int, in_progress: int, total_cost: int}
     */
    public function getStatistics(DateRange $range): array;

    /**
     * Find by work order number.
     */
    public function findByNumber(string $woNumber): ?WorkOrder;
}
