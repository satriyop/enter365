<?php

declare(strict_types=1);

namespace App\Contracts\Projects;

use App\Models\Projects\Project;
use App\Models\Projects\Task;
use Illuminate\Support\Collection;

/**
 * Interface for Task service operations.
 */
interface TaskServiceInterface
{
    /**
     * Create a new task for a project.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Project $project, array $data): Task;

    /**
     * Update a task.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, array $data): Task;

    /**
     * Delete a task (soft delete).
     */
    public function delete(Task $task): bool;

    /**
     * Start a task (Todo → InProgress).
     */
    public function start(Task $task, ?int $userId = null): Task;

    /**
     * Complete a task (InProgress → Done).
     */
    public function complete(Task $task, ?int $userId = null): Task;

    /**
     * Cancel a task (→ Cancelled).
     */
    public function cancel(Task $task, ?string $reason = null, ?int $userId = null): Task;

    /**
     * Add a subtask to a parent task.
     *
     * @param  array<string, mixed>  $data
     */
    public function addSubtask(Task $parentTask, array $data): Task;

    /**
     * Reorder tasks within a project.
     *
     * @param  array<int, int>  $orderedIds  Task IDs in desired order
     */
    public function reorder(Project $project, array $orderedIds): void;

    /**
     * Add a dependency between tasks.
     */
    public function addDependency(Task $task, Task $dependency): void;

    /**
     * Remove a dependency between tasks.
     */
    public function removeDependency(Task $task, Task $dependency): void;

    /**
     * Get task tree (root tasks with subtasks) for a project.
     *
     * @return Collection<int, Task>
     */
    public function getTaskTree(Project $project): Collection;

    /**
     * Get task statistics for a project.
     *
     * @return array<string, mixed>
     */
    public function getTaskStatistics(Project $project): array;
}
