<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Manufacturing;

use App\Contracts\Repositories\Manufacturing\WorkOrderRepositoryInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Infrastructure\Repositories\EloquentRepository;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use Illuminate\Support\Collection;

/**
 * Eloquent implementation of WorkOrder repository.
 *
 * @extends EloquentRepository<WorkOrder>
 */
class EloquentWorkOrderRepository extends EloquentRepository implements WorkOrderRepositoryInterface
{
    protected string $modelClass = WorkOrder::class;

    protected array $with = ['project', 'product', 'warehouse'];

    public function findByStatus(DocumentStatus $status): Collection
    {
        return $this->newQuery()
            ->where('status', $status)
            ->orderBy('planned_start_date', 'asc')
            ->get();
    }

    public function findByProject(int $projectId): Collection
    {
        return $this->newQuery()
            ->where('project_id', $projectId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findByProduct(int $productId): Collection
    {
        return $this->newQuery()
            ->where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findInProgress(): Collection
    {
        return $this->findByStatus(DocumentStatus::InProgress);
    }

    public function findOverdue(): Collection
    {
        return $this->newQuery()
            ->where('planned_end_date', '<', now())
            ->whereIn('status', [DocumentStatus::Confirmed, DocumentStatus::InProgress])
            ->orderBy('planned_end_date', 'asc')
            ->get();
    }

    public function findWithRelations(int $id): ?WorkOrder
    {
        return $this->newQuery()
            ->with([
                'project',
                'product',
                'warehouse',
                'bom',
                'items.product',
                'materialRequisitions',
                'consumptions',
                'creator',
            ])
            ->find($id);
    }

    public function getMaterialRequirements(int $workOrderId): Collection
    {
        return WorkOrderItem::query()
            ->where('work_order_id', $workOrderId)
            ->where('type', WorkOrderItem::TYPE_MATERIAL)
            ->whereNotNull('product_id')
            ->select('product_id', 'quantity_required as quantity', 'unit')
            ->get()
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => (float) $item->quantity,
                'unit' => $item->unit,
            ]);
    }

    public function getStatistics(DateRange $range): array
    {
        $query = $this->newQuery()
            ->whereBetween('created_at', [
                $range->start->toDateTimeString(),
                $range->end->toDateTimeString(),
            ]);

        $total = (clone $query)->count();

        $completed = (clone $query)
            ->where('status', DocumentStatus::Completed)
            ->count();

        $inProgress = (clone $query)
            ->where('status', DocumentStatus::InProgress)
            ->count();

        $totalCost = (int) (clone $query)
            ->where('status', DocumentStatus::Completed)
            ->sum('actual_total_cost');

        return [
            'total' => $total,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'total_cost' => $totalCost,
        ];
    }

    public function findByNumber(string $woNumber): ?WorkOrder
    {
        return $this->findOneBy(['wo_number' => $woNumber]);
    }
}
