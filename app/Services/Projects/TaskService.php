<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Projects\TaskServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Projects\Project;
use App\Models\Projects\Task;
use App\Services\Base\BaseService;
use Illuminate\Support\Collection;

class TaskService extends BaseService implements TaskServiceInterface
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * {@inheritdoc}
     */
    public function create(Project $project, array $data): Task
    {
        return $this->executeInTransaction('create_task', function () use ($project, $data) {
            $data['project_id'] = $project->id;
            $data['created_by'] = $data['created_by'] ?? $this->getUserId();

            $task = new Task($data);
            $task->save();

            return $task->fresh(['project', 'assignee', 'creator']);
        }, ['project_id' => $project->id]);
    }

    /**
     * {@inheritdoc}
     */
    public function update(Task $task, array $data): Task
    {
        if (! $task->canBeEdited()) {
            throw DocumentLockedException::cannotEdit($task, 'Hanya tugas dengan status "Belum Dikerjakan" yang dapat diedit.');
        }

        $task->fill($data);
        $task->save();

        return $task->fresh(['project', 'assignee', 'creator']);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Task $task): bool
    {
        if (! $task->isDeletable()) {
            throw DocumentLockedException::cannotDelete($task, 'Hanya tugas dengan status "Belum Dikerjakan" yang dapat dihapus.');
        }

        return $this->executeInTransaction('delete_task', function () use ($task) {
            // Soft delete subtasks first (FK cascade only works for hard deletes)
            $task->subtasks()->delete();

            return $task->delete();
        }, ['task_id' => $task->id]);
    }

    /**
     * {@inheritdoc}
     */
    public function start(Task $task, ?int $userId = null): Task
    {
        if (! $task->stateMachine()->canStart()) {
            throw StateTransitionException::wrongStateForOperation(
                'Task',
                'dimulai',
                $task->status->value,
                'todo'
            );
        }

        $task->transitionTo(DocumentStatus::InProgress, $userId);

        return $task->fresh();
    }

    /**
     * {@inheritdoc}
     */
    public function complete(Task $task, ?int $userId = null): Task
    {
        if (! $task->stateMachine()->canComplete()) {
            throw StateTransitionException::wrongStateForOperation(
                'Task',
                'diselesaikan',
                $task->status->value,
                'in_progress'
            );
        }

        $task->transitionTo(DocumentStatus::Done, $userId);

        return $task->fresh();
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(Task $task, ?string $reason = null, ?int $userId = null): Task
    {
        if (! $task->stateMachine()->canCancel()) {
            throw StateTransitionException::wrongStateForOperation(
                'Task',
                'dibatalkan',
                $task->status->value,
                'todo atau in_progress'
            );
        }

        return $this->executeInTransaction('cancel_task', function () use ($task, $reason, $userId) {
            // Cascade cancel open subtasks
            $openSubtasks = $task->subtasks()
                ->whereNotIn('status', [DocumentStatus::Done->value, DocumentStatus::Cancelled->value])
                ->get();

            foreach ($openSubtasks as $subtask) {
                $subtask->transitionTo(DocumentStatus::Cancelled, $userId, [
                    'cancellation_reason' => 'Tugas induk dibatalkan.',
                ]);
            }

            $task->transitionTo(DocumentStatus::Cancelled, $userId, [
                'cancellation_reason' => $reason,
            ]);

            return $task->fresh();
        }, ['task_id' => $task->id]);
    }

    /**
     * {@inheritdoc}
     */
    public function addSubtask(Task $parentTask, array $data): Task
    {
        // Enforce one-level: parent cannot itself be a subtask
        if ($parentTask->isSubtask()) {
            throw BusinessRuleException::operationNotAllowed(
                'tambah sub-tugas',
                'Sub-tugas tidak dapat memiliki sub-tugas sendiri (hanya satu level).'
            );
        }

        $data['project_id'] = $parentTask->project_id;
        $data['parent_id'] = $parentTask->id;
        $data['created_by'] = $data['created_by'] ?? $this->getUserId();

        return $this->executeInTransaction('add_subtask', function () use ($data) {
            $subtask = new Task($data);
            $subtask->save();

            return $subtask->fresh(['project', 'parent', 'assignee', 'creator']);
        }, ['parent_id' => $parentTask->id]);
    }

    /**
     * {@inheritdoc}
     */
    public function reorder(Project $project, array $orderedIds): void
    {
        $this->executeInTransaction('reorder_tasks', function () use ($project, $orderedIds) {
            foreach ($orderedIds as $index => $taskId) {
                Task::where('id', $taskId)
                    ->where('project_id', $project->id)
                    ->update(['sort_order' => $index]);
            }
        }, ['project_id' => $project->id]);
    }

    /**
     * {@inheritdoc}
     */
    public function addDependency(Task $task, Task $dependency): void
    {
        // Must be in the same project
        if ($task->project_id !== $dependency->project_id) {
            throw BusinessRuleException::operationNotAllowed(
                'tambah dependensi',
                'Dependensi hanya dapat dibuat antar tugas dalam proyek yang sama.'
            );
        }

        // Cannot depend on itself
        if ($task->id === $dependency->id) {
            throw BusinessRuleException::operationNotAllowed(
                'tambah dependensi',
                'Tugas tidak dapat bergantung pada dirinya sendiri.'
            );
        }

        // Check for circular dependency
        if ($this->wouldCreateCircularDependency($task, $dependency)) {
            throw BusinessRuleException::operationNotAllowed(
                'tambah dependensi',
                'Dependensi ini akan membuat ketergantungan melingkar (circular dependency).'
            );
        }

        // Use attach with existence check to avoid duplicates
        if (! $task->dependencies()->where('tasks.id', $dependency->id)->exists()) {
            $task->dependencies()->attach($dependency->id, [
                'dependency_type' => 'finish_to_start',
                'created_at' => now(),
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeDependency(Task $task, Task $dependency): void
    {
        $task->dependencies()->detach($dependency->id);
    }

    /**
     * {@inheritdoc}
     */
    public function getTaskTree(Project $project): Collection
    {
        return $project->rootTasks()
            ->with(['subtasks' => fn ($q) => $q->orderBy('sort_order'), 'assignee', 'creator'])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getTaskStatistics(Project $project): array
    {
        $tasks = $project->tasks()->withTrashed(false)->get();

        $byStatus = [];
        foreach (DocumentStatus::forTask() as $status) {
            $byStatus[$status->value] = $tasks->where('status', $status)->count();
        }

        $byAssignee = $tasks->groupBy('assigned_to')
            ->map(fn (Collection $group) => $group->count())
            ->toArray();

        $overdueCount = $tasks->filter(fn (Task $t) => $t->isOverdue())->count();

        return [
            'total' => $tasks->count(),
            'by_status' => $byStatus,
            'by_assignee' => $byAssignee,
            'overdue_count' => $overdueCount,
        ];
    }

    /**
     * Check if adding dependency would create a circular dependency.
     */
    private function wouldCreateCircularDependency(Task $task, Task $dependency): bool
    {
        // If dependency (directly or transitively) depends on task, it's circular
        $visited = [];

        return $this->hasTransitiveDependency($dependency, $task->id, $visited);
    }

    /**
     * Recursively check if source transitively depends on targetId.
     *
     * @param  array<int, bool>  $visited
     */
    private function hasTransitiveDependency(Task $source, int $targetId, array &$visited): bool
    {
        if (isset($visited[$source->id])) {
            return false;
        }
        $visited[$source->id] = true;

        // Check if any of source's dependencies is the target
        $dependencyIds = $source->dependencies()->pluck('tasks.id');
        if ($dependencyIds->contains($targetId)) {
            return true;
        }

        // Check transitive
        foreach ($source->dependencies as $dep) {
            if ($this->hasTransitiveDependency($dep, $targetId, $visited)) {
                return true;
            }
        }

        return false;
    }
}
