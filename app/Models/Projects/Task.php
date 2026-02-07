<?php

namespace App\Models\Projects;

use App\Domain\Projects\Tasks\TaskStateMachine;
use App\Enums\DocumentStatus;
use App\Models\User;
use App\Traits\Filterable;
use App\Traits\HasStatusHistory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $task_number
 * @property string $title
 * @property string|null $description
 * @property int $project_id
 * @property int|null $parent_id
 * @property DocumentStatus $status
 * @property string $priority
 * @property int|null $assigned_to
 * @property Carbon|null $start_date
 * @property Carbon|null $due_date
 * @property Carbon|null $completed_at
 * @property string|null $estimated_hours
 * @property string|null $actual_hours
 * @property int $sort_order
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Task extends Model
{
    use Filterable, HasFactory, HasStatusHistory, SoftDeletes;

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'task_number',
        'title',
        'description',
        'project_id',
        'parent_id',
        'status',
        'priority',
        'assigned_to',
        'start_date',
        'due_date',
        'completed_at',
        'estimated_hours',
        'actual_hours',
        'sort_order',
        'notes',
        'created_by',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Task $task) {
            if (empty($task->task_number)) {
                $task->task_number = \App\Domain\Shared\DocumentNumbers::generate(
                    'TSK-'.now()->format('Ym').'-',
                    'tasks',
                    'task_number'
                );
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'estimated_hours' => 'decimal:2',
            'actual_hours' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Tasks that this task depends on (must be completed before this task can start).
     *
     * @return BelongsToMany<self, $this>
     */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'task_dependencies', 'task_id', 'dependency_id')
            ->withPivot('dependency_type', 'created_at');
    }

    /**
     * Tasks that depend on this task (cannot start until this task is done).
     *
     * @return BelongsToMany<self, $this>
     */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'task_dependencies', 'dependency_id', 'task_id')
            ->withPivot('dependency_type', 'created_at');
    }

    public function isSubtask(): bool
    {
        return $this->parent_id !== null;
    }

    public function hasSubtasks(): bool
    {
        return $this->subtasks()->exists();
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->isPast()
            && ! in_array($this->status, [DocumentStatus::Done, DocumentStatus::Cancelled], true);
    }

    public function canBeEdited(): bool
    {
        return $this->status === DocumentStatus::Todo;
    }

    public function isDeletable(): bool
    {
        return $this->status === DocumentStatus::Todo;
    }

    /**
     * Get progress percentage based on subtask completion.
     */
    public function getProgressPercentage(): float
    {
        if (! $this->relationLoaded('subtasks') && ! $this->hasSubtasks()) {
            return $this->status === DocumentStatus::Done ? 100 : 0;
        }

        $subtasks = $this->subtasks;
        if ($subtasks->isEmpty()) {
            return $this->status === DocumentStatus::Done ? 100 : 0;
        }

        $completed = $subtasks->filter(
            fn (self $t) => in_array($t->status, [DocumentStatus::Done, DocumentStatus::Cancelled], true)
        )->count();

        return round(($completed / $subtasks->count()) * 100, 2);
    }

    public function hasUnfinishedSubtasks(): bool
    {
        return $this->subtasks()
            ->whereNotIn('status', [DocumentStatus::Done->value, DocumentStatus::Cancelled->value])
            ->exists();
    }

    public function hasBlockingDependencies(): bool
    {
        return $this->dependencies()
            ->whereNotIn('tasks.status', [DocumentStatus::Done->value, DocumentStatus::Cancelled->value])
            ->exists();
    }

    public function stateMachine(): TaskStateMachine
    {
        return TaskStateMachine::fromTask($this);
    }

    public function transitionTo(DocumentStatus $status, ?int $userId = null, array $context = []): self
    {
        $this->stateMachine()->transitionTo($status, array_merge(['user_id' => $userId], $context));

        return $this->refresh();
    }

    /**
     * Get available priorities.
     *
     * @return array<string, string>
     */
    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_LOW => 'Rendah',
            self::PRIORITY_NORMAL => 'Normal',
            self::PRIORITY_HIGH => 'Tinggi',
            self::PRIORITY_URGENT => 'Mendesak',
        ];
    }
}
