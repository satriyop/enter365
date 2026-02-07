<?php

namespace Database\Factories\Projects;

use App\Enums\DocumentStatus;
use App\Models\Projects\Project;
use App\Models\Projects\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 month', '+1 month');

        return [
            'task_number' => 'TSK-'.now()->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'project_id' => Project::factory(),
            'parent_id' => null,
            'status' => DocumentStatus::Todo,
            'priority' => Task::PRIORITY_NORMAL,
            'assigned_to' => null,
            'start_date' => $this->faker->optional()->dateTimeBetween($startDate, '+2 months'),
            'due_date' => $this->faker->optional()->dateTimeBetween('+1 week', '+3 months'),
            'completed_at' => null,
            'estimated_hours' => $this->faker->optional()->randomFloat(2, 1, 80),
            'actual_hours' => null,
            'sort_order' => 0,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Todo status (default).
     */
    public function todo(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Todo,
        ]);
    }

    /**
     * In progress status.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::InProgress,
            'start_date' => now()->subDays(3),
        ]);
    }

    /**
     * Done status.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Done,
            'start_date' => now()->subWeek(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Cancelled status.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Cancelled,
        ]);
    }

    /**
     * For a specific project.
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
        ]);
    }

    /**
     * As a subtask of a parent task.
     */
    public function asSubtaskOf(Task $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
            'project_id' => $parent->project_id,
        ]);
    }

    /**
     * Assigned to a specific user.
     */
    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => $user->id,
        ]);
    }

    /**
     * High priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => Task::PRIORITY_HIGH,
        ]);
    }

    /**
     * Urgent priority.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => Task::PRIORITY_URGENT,
        ]);
    }

    /**
     * Overdue task.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::InProgress,
            'start_date' => now()->subMonth(),
            'due_date' => now()->subWeek(),
        ]);
    }

    /**
     * With estimated hours.
     */
    public function withEstimate(float $hours = 8.0): static
    {
        return $this->state(fn (array $attributes) => [
            'estimated_hours' => $hours,
        ]);
    }
}
