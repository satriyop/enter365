<?php

declare(strict_types=1);

namespace App\Services\Manufacturing\Subcontractor;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\SubcontractorWorkOrder;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectCost;
use App\Services\Base\BaseService;

/**
 * Handles subcontractor work order CRUD and workflow operations.
 *
 * Extracted from SubcontractorService as part of the Coordinator Pattern refactoring.
 *
 * @see \App\Services\Manufacturing\SubcontractorService The coordinator service
 */
class SubcontractorWorkOrderService extends BaseService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a subcontractor work order.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SubcontractorWorkOrder
    {
        return $this->executeInTransaction('create', function () use ($data) {
            $project = isset($data['project_id']) ? Project::find($data['project_id']) : null;

            $scWo = new SubcontractorWorkOrder($data);
            $scWo->sc_wo_number = SubcontractorWorkOrder::generateScWoNumber($project);
            $scWo->status = DocumentStatus::Draft;
            $scWo->retention_percent = $data['retention_percent'] ?? SubcontractorWorkOrder::DEFAULT_RETENTION_PERCENT;
            $scWo->created_by = $data['created_by'] ?? $this->getUserId();
            $scWo->save();

            // Calculate financials
            $scWo->recalculateFinancials();
            $scWo->save();

            return $scWo->fresh(['subcontractor', 'project', 'workOrder']);
        }, ['subcontractor_id' => $data['subcontractor_id'] ?? null, 'project_id' => $data['project_id'] ?? null]);
    }

    /**
     * Update a subcontractor work order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SubcontractorWorkOrder $scWo, array $data): SubcontractorWorkOrder
    {
        if (! $scWo->canBeEdited()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($scWo, 'SC WO hanya dapat diedit dalam status draft atau ditugaskan.');
        }

        return $this->executeInTransaction('update', function () use ($scWo, $data) {
            $scWo->fill($data);
            $scWo->recalculateFinancials();
            $scWo->save();

            return $scWo->fresh(['subcontractor', 'project', 'workOrder']);
        }, ['sc_wo_id' => $scWo->id]);
    }

    /**
     * Delete a subcontractor work order.
     */
    public function delete(SubcontractorWorkOrder $scWo): bool
    {
        if (! $scWo->isDeletable()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotDelete($scWo, 'Hanya SC WO draft yang dapat dihapus.');
        }

        return $this->executeInTransaction('delete', function () use ($scWo) {
            $scWo->invoices()->delete();

            return $scWo->delete();
        }, ['sc_wo_id' => $scWo->id]);
    }

    /**
     * Assign work order to subcontractor.
     */
    public function assign(SubcontractorWorkOrder $scWo, ?int $userId = null): SubcontractorWorkOrder
    {
        if (! $scWo->stateMachine()->canAssign()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation('SC WO', 'ditugaskan', $scWo->status->value, 'draft');
        }

        $scWo->transitionTo(DocumentStatus::Assigned, $userId);

        return $scWo->fresh();
    }

    /**
     * Start work order.
     */
    public function start(SubcontractorWorkOrder $scWo, ?int $userId = null): SubcontractorWorkOrder
    {
        if (! $scWo->stateMachine()->canStart()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation('SC WO', 'dimulai', $scWo->status->value, 'ditugaskan');
        }

        $scWo->transitionTo(DocumentStatus::InProgress, $userId);

        return $scWo->fresh();
    }

    /**
     * Update progress.
     */
    public function updateProgress(SubcontractorWorkOrder $scWo, int $percentage): SubcontractorWorkOrder
    {
        if (! $scWo->canUpdateProgress()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'SC WO',
                'update progres',
                $scWo->status->value,
                'dalam proses'
            );
        }

        if ($percentage < 0 || $percentage > 100) {
            throw \App\Exceptions\Domain\BusinessRuleException::quantityValidation(
                'Persentase progres',
                $percentage,
                100,
                'invalid'
            );
        }

        $scWo->completion_percentage = $percentage;
        $scWo->save();

        return $scWo->fresh();
    }

    /**
     * Complete work order.
     */
    public function complete(
        SubcontractorWorkOrder $scWo,
        ?int $actualAmount = null,
        ?int $userId = null
    ): SubcontractorWorkOrder {
        if (! $scWo->stateMachine()->canComplete()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'SC WO',
                'diselesaikan',
                $scWo->status->value,
                'dalam proses'
            );
        }

        return $this->executeInTransaction('complete', function () use ($scWo, $actualAmount, $userId) {
            $scWo->transitionTo(DocumentStatus::Completed, $userId);

            $scWo->refresh();

            if ($actualAmount !== null) {
                $scWo->actual_amount = $actualAmount;
            } else {
                $scWo->actual_amount = $scWo->agreed_amount;
            }

            $scWo->recalculateFinancials();
            $scWo->save();

            $this->createProjectCost($scWo);

            return $scWo->fresh();
        }, ['sc_wo_id' => $scWo->id]);
    }

    /**
     * Cancel work order.
     */
    public function cancel(
        SubcontractorWorkOrder $scWo,
        ?string $reason = null,
        ?int $userId = null
    ): SubcontractorWorkOrder {
        if (! $scWo->stateMachine()->canCancel()) {
            throw \App\Exceptions\Domain\StateTransitionException::actionNotAvailable('dibatalkan', $scWo->status->value);
        }

        $scWo->transitionTo(DocumentStatus::Cancelled, $userId, [
            'cancellation_reason' => $reason,
        ]);

        return $scWo->fresh();
    }

    /**
     * Create project cost entry for completed subcontractor work.
     */
    private function createProjectCost(SubcontractorWorkOrder $scWo): void
    {
        if (! $scWo->project_id) {
            return;
        }

        // Check if ProjectCost model exists
        if (! class_exists(ProjectCost::class)) {
            return;
        }

        $amount = $scWo->actual_amount > 0 ? $scWo->actual_amount : $scWo->agreed_amount;

        ProjectCost::create([
            'project_id' => $scWo->project_id,
            'cost_type' => 'subcontractor',
            'description' => "Subkontrak: {$scWo->name}",
            'reference_type' => SubcontractorWorkOrder::class,
            'reference_id' => $scWo->id,
            'total_cost' => $amount,
            'cost_date' => now(),
            'created_by' => $this->getUserId(),
        ]);

        // Recalculate project financials
        $project = $scWo->project;
        if ($project && method_exists($project, 'calculateFinancials')) {
            $project->calculateFinancials();
            $project->save();
        }
    }
}
