<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Enums\DocumentStatus;
use Illuminate\Support\Facades\DB;

class TaskAnalysisService
{
    /**
     * @return array{report_name: string, from: string|null, to: string|null, totals: array<string, int|float|null>, by_status: list<array<string, mixed>>, by_project: list<array<string, mixed>>, by_priority: list<array<string, mixed>>, by_assignee: list<array<string, mixed>>}
     */
    public function tasksAnalysis(?string $from = null, ?string $to = null): array
    {
        $query = DB::table('tasks')
            ->whereNull('deleted_at');

        if (filled($from)) {
            $query->where(function ($inner) use ($from) {
                $inner->whereDate('due_date', '>=', $from)
                    ->orWhere(function ($open) use ($from) {
                        $open->whereNull('due_date')->whereDate('created_at', '>=', $from);
                    });
            });
        }
        if (filled($to)) {
            $query->where(function ($inner) use ($to) {
                $inner->whereDate('due_date', '<=', $to)
                    ->orWhere(function ($open) use ($to) {
                        $open->whereNull('due_date')->whereDate('created_at', '<=', $to);
                    });
            });
        }

        $tasks = (clone $query)->get([
            'id', 'project_id', 'status', 'priority', 'assigned_to', 'due_date', 'estimated_hours', 'actual_hours',
        ]);

        $openStatuses = [DocumentStatus::Todo->value, DocumentStatus::InProgress->value];
        $today = now()->toDateString();

        $byStatus = [];
        $byProject = [];
        $byPriority = [];
        $byAssignee = [];
        $overdue = 0;
        $done = 0;

        foreach ($tasks as $task) {
            $status = (string) $task->status;
            $priority = (string) $task->priority;
            $projectId = (int) $task->project_id;
            $assigneeId = $task->assigned_to ? (int) $task->assigned_to : 0;
            $isOpen = in_array($status, $openStatuses, true);
            $due = $task->due_date ? substr((string) $task->due_date, 0, 10) : null;
            $isOverdue = $isOpen && $due !== null && $due < $today;

            if ($isOverdue) {
                $overdue++;
            }
            if ($status === DocumentStatus::Done->value) {
                $done++;
            }

            $byStatus[$status] = $byStatus[$status] ?? ['status' => $status, 'count' => 0, 'overdue' => 0];
            $byStatus[$status]['count']++;
            if ($isOverdue) {
                $byStatus[$status]['overdue']++;
            }

            $byPriority[$priority] = $byPriority[$priority] ?? ['priority' => $priority, 'count' => 0];
            $byPriority[$priority]['count']++;

            $byProject[$projectId] = $byProject[$projectId] ?? [
                'project_id' => $projectId,
                'count' => 0,
                'done' => 0,
                'overdue' => 0,
            ];
            $byProject[$projectId]['count']++;
            if ($status === DocumentStatus::Done->value) {
                $byProject[$projectId]['done']++;
            }
            if ($isOverdue) {
                $byProject[$projectId]['overdue']++;
            }

            $byAssignee[$assigneeId] = $byAssignee[$assigneeId] ?? [
                'user_id' => $assigneeId ?: null,
                'count' => 0,
                'done' => 0,
            ];
            $byAssignee[$assigneeId]['count']++;
            if ($status === DocumentStatus::Done->value) {
                $byAssignee[$assigneeId]['done']++;
            }
        }

        $projects = DB::table('projects')->whereIn('id', array_keys($byProject))->get(['id', 'name', 'project_number'])->keyBy('id');
        $users = DB::table('users')->whereIn('id', array_filter(array_keys($byAssignee)))->get(['id', 'name'])->keyBy('id');

        $projectRows = [];
        foreach ($byProject as $id => $row) {
            $project = $projects->get($id);
            $projectRows[] = [
                ...$row,
                'project_number' => $project->project_number ?? null,
                'project_name' => $project->name ?? null,
            ];
        }

        $assigneeRows = [];
        foreach ($byAssignee as $id => $row) {
            $user = $users->get($id);
            $assigneeRows[] = [
                ...$row,
                'name' => $id === 0 ? 'Unassigned' : ($user->name ?? null),
            ];
        }

        $total = $tasks->count();

        return [
            'report_name' => 'Tasks Analysis',
            'from' => $from,
            'to' => $to,
            'totals' => [
                'count' => $total,
                'done' => $done,
                'overdue' => $overdue,
                'completion_rate' => $total > 0 ? round($done / $total * 100, 1) : 0,
            ],
            'by_status' => array_values($byStatus),
            'by_priority' => array_values($byPriority),
            'by_project' => $projectRows,
            'by_assignee' => $assigneeRows,
        ];
    }

    /**
     * @return array{report_name: string, totals: array{count: int, average: float|null}, by_rating: list<array{rating: int, count: int}>, rows: list<array<string, mixed>>}
     */
    public function customerRatings(?int $projectId = null): array
    {
        $query = DB::table('customer_ratings as r')
            ->leftJoin('projects as p', 'p.id', '=', 'r.project_id')
            ->leftJoin('contacts as c', 'c.id', '=', 'r.contact_id')
            ->leftJoin('tasks as t', function ($join) {
                $join->on('t.id', '=', 'r.rateable_id')
                    ->where('r.rateable_type', '=', 'task');
            });

        if ($projectId) {
            $query->where('r.project_id', $projectId);
        }

        $rows = $query
            ->orderByDesc('r.rated_at')
            ->orderByDesc('r.id')
            ->get([
                'r.id',
                'r.project_id',
                'p.name as project_name',
                'p.project_number',
                'r.rateable_type',
                'r.rateable_id',
                't.title as task_title',
                'r.contact_id',
                'c.name as contact_name',
                'r.rating',
                'r.comment',
                'r.rated_at',
            ]);

        $byRating = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $sum = 0;
        $mapped = [];
        foreach ($rows as $row) {
            $rating = (int) $row->rating;
            $byRating[$rating] = ($byRating[$rating] ?? 0) + 1;
            $sum += $rating;
            $mapped[] = [
                'id' => (int) $row->id,
                'project_id' => (int) $row->project_id,
                'project_name' => $row->project_name,
                'project_number' => $row->project_number,
                'rateable_type' => $row->rateable_type,
                'rateable_id' => $row->rateable_id ? (int) $row->rateable_id : null,
                'task_title' => $row->task_title,
                'contact_id' => $row->contact_id ? (int) $row->contact_id : null,
                'contact_name' => $row->contact_name,
                'rating' => $rating,
                'comment' => $row->comment,
                'rated_at' => $row->rated_at,
            ];
        }

        $count = count($mapped);

        return [
            'report_name' => 'Customer Ratings',
            'totals' => [
                'count' => $count,
                'average' => $count > 0 ? round($sum / $count, 2) : null,
            ],
            'by_rating' => collect($byRating)->map(fn (int $count, int $rating) => [
                'rating' => $rating,
                'count' => $count,
            ])->values()->all(),
            'rows' => $mapped,
        ];
    }
}
