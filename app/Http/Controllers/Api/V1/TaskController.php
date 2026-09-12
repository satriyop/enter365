<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\TaskFilter;
use App\Http\Requests\Api\V1\StoreTaskRequest;
use App\Http\Requests\Api\V1\UpdateTaskRequest;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Projects\Project;
use App\Models\Projects\Task;
use App\Services\Projects\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskController extends Controller
{
    public function __construct(
        private TaskService $taskService
    ) {}

    /**
     * List tasks across all projects (Odoo Project › All Tasks).
     *
     * @queryParam project_id int Filter by project. Example: 1
     * @queryParam status string todo, in_progress, done, cancelled. Example: todo
     * @queryParam priority string low, normal, high, urgent. Example: high
     * @queryParam assigned_to int Filter by assignee. Example: 2
     * @queryParam overdue_only bool Only overdue open tasks. Example: 1
     * @queryParam search string Search number or title. Example: TSK
     * @queryParam per_page int Default 25. Example: 50
     */
    public function all(TaskFilter $filter): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Task::class);

        $tasks = Task::query()
            ->with(['assignee', 'creator', 'project'])
            ->filter($filter)
            ->paginate($filter->getRequest()->input('per_page', 25));

        return TaskResource::collection($tasks);
    }

    /**
     * List tasks assigned to the current user (Odoo Project › My Tasks).
     *
     * @queryParam project_id int Filter by project. Example: 1
     * @queryParam status string todo, in_progress, done, cancelled. Example: todo
     * @queryParam priority string low, normal, high, urgent. Example: high
     * @queryParam overdue_only bool Only overdue open tasks. Example: 1
     * @queryParam search string Search number or title. Example: TSK
     * @queryParam per_page int Default 25. Example: 50
     */
    public function mine(TaskFilter $filter): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Task::class);

        $tasks = Task::query()
            ->where('assigned_to', $filter->getRequest()->user()?->id)
            ->with(['assignee', 'creator', 'project'])
            ->filter($filter)
            ->paginate($filter->getRequest()->input('per_page', 25));

        return TaskResource::collection($tasks);
    }

    /**
     * List tasks for a project.
     */
    public function index(Project $project, TaskFilter $filter): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Task::class);

        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->with(['assignee', 'creator'])
            ->filter($filter)
            ->paginate($filter->getRequest()->input('per_page', 25));

        return TaskResource::collection($tasks);
    }

    /**
     * Create a new task for a project.
     */
    public function store(StoreTaskRequest $request, Project $project): JsonResponse
    {
        $this->authorize('create', Task::class);

        $task = $this->taskService->create($project, $request->validated());

        return (new TaskResource($task))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display a specific task.
     */
    public function show(Project $project, Task $task): TaskResource|JsonResponse
    {
        $this->authorize('view', $task);

        if ($task->project_id !== $project->id) {
            return response()->json(['message' => 'Tugas tidak ditemukan untuk proyek ini.'], 404);
        }

        $task->loadMissing(['project', 'parent', 'subtasks.assignee', 'assignee', 'creator', 'dependencies']);

        return new TaskResource($task);
    }

    /**
     * Update a task.
     */
    public function update(UpdateTaskRequest $request, Project $project, Task $task): TaskResource|JsonResponse
    {
        $this->authorize('update', $task);

        if ($task->project_id !== $project->id) {
            return response()->json(['message' => 'Tugas tidak ditemukan untuk proyek ini.'], 404);
        }

        $task = $this->taskService->update($task, $request->validated());

        return new TaskResource($task);
    }

    /**
     * Delete a task.
     */
    public function destroy(Project $project, Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        if ($task->project_id !== $project->id) {
            return response()->json(['message' => 'Tugas tidak ditemukan untuk proyek ini.'], 404);
        }

        $this->taskService->delete($task);

        return response()->json(['message' => 'Tugas berhasil dihapus.']);
    }

    /**
     * Start a task (Todo → InProgress).
     */
    public function start(Project $project, Task $task): TaskResource|JsonResponse
    {
        $this->authorize('update', $task);

        if ($task->project_id !== $project->id) {
            return response()->json(['message' => 'Tugas tidak ditemukan untuk proyek ini.'], 404);
        }

        $task = $this->taskService->start($task);

        return new TaskResource($task);
    }

    /**
     * Complete a task (InProgress → Done).
     */
    public function complete(Project $project, Task $task): TaskResource|JsonResponse
    {
        $this->authorize('update', $task);

        if ($task->project_id !== $project->id) {
            return response()->json(['message' => 'Tugas tidak ditemukan untuk proyek ini.'], 404);
        }

        $task = $this->taskService->complete($task);

        return new TaskResource($task);
    }

    /**
     * Cancel a task.
     */
    public function cancel(Request $request, Project $project, Task $task): TaskResource|JsonResponse
    {
        $this->authorize('update', $task);

        if ($task->project_id !== $project->id) {
            return response()->json(['message' => 'Tugas tidak ditemukan untuk proyek ini.'], 404);
        }

        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $task = $this->taskService->cancel($task, $request->input('reason'));

        return new TaskResource($task);
    }

    /**
     * Add a subtask to a task.
     */
    public function addSubtask(StoreTaskRequest $request, Project $project, Task $task): JsonResponse
    {
        $this->authorize('create', Task::class);

        if ($task->project_id !== $project->id) {
            return response()->json(['message' => 'Tugas tidak ditemukan untuk proyek ini.'], 404);
        }

        $subtask = $this->taskService->addSubtask($task, $request->validated());

        return (new TaskResource($subtask))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Add a dependency to a task.
     */
    public function addDependency(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        if ($task->project_id !== $project->id) {
            return response()->json(['message' => 'Tugas tidak ditemukan untuk proyek ini.'], 404);
        }

        $request->validate([
            'dependency_id' => ['required', 'integer', 'exists:tasks,id'],
        ], [
            'dependency_id.required' => 'ID tugas dependensi harus diisi.',
            'dependency_id.exists' => 'Tugas dependensi tidak ditemukan.',
        ]);

        $dependency = Task::findOrFail($request->input('dependency_id'));

        $this->taskService->addDependency($task, $dependency);

        return response()->json(['message' => 'Dependensi berhasil ditambahkan.']);
    }

    /**
     * Remove a dependency from a task.
     */
    public function removeDependency(Project $project, Task $task, Task $dependency): JsonResponse
    {
        $this->authorize('update', $task);

        if ($task->project_id !== $project->id) {
            return response()->json(['message' => 'Tugas tidak ditemukan untuk proyek ini.'], 404);
        }

        $this->taskService->removeDependency($task, $dependency);

        return response()->json(['message' => 'Dependensi berhasil dihapus.']);
    }

    /**
     * Reorder tasks within a project.
     */
    public function reorder(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $request->validate([
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['integer', 'exists:tasks,id'],
        ], [
            'task_ids.required' => 'Daftar tugas harus diisi.',
        ]);

        $this->taskService->reorder($project, $request->input('task_ids'));

        return response()->json(['message' => 'Urutan tugas berhasil diperbarui.']);
    }

    /**
     * Get task statistics for a project.
     */
    public function statistics(Project $project): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $statistics = $this->taskService->getTaskStatistics($project);

        return response()->json(['data' => $statistics]);
    }
}
