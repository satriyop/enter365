<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Projects\Task
 */
class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_number' => $this->task_number,
            'title' => $this->title,
            'description' => $this->description,
            'project_id' => $this->project_id,
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'project_number' => $this->project->project_number,
                'name' => $this->project->name,
            ]),
            'parent_id' => $this->parent_id,
            'parent' => $this->whenLoaded('parent', fn () => [
                'id' => $this->parent->id,
                'task_number' => $this->parent->task_number,
                'title' => $this->parent->title,
            ]),
            'status' => new StatusResource($this->status),
            'priority' => $this->priority,
            'assigned_to' => $this->assigned_to,
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'estimated_hours' => $this->estimated_hours ? (float) $this->estimated_hours : null,
            'actual_hours' => $this->actual_hours ? (float) $this->actual_hours : null,
            'sort_order' => $this->sort_order,
            'notes' => $this->notes,
            'is_overdue' => $this->isOverdue(),
            'is_subtask' => $this->isSubtask(),
            'progress' => $this->when($this->relationLoaded('subtasks'), fn () => $this->getProgressPercentage()),
            'subtasks' => TaskResource::collection($this->whenLoaded('subtasks')),
            'subtasks_count' => $this->whenCounted('subtasks'),
            'dependencies' => TaskResource::collection($this->whenLoaded('dependencies')),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
