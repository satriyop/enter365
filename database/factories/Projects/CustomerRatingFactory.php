<?php

namespace Database\Factories\Projects;

use App\Models\Contacts\Contact;
use App\Models\Projects\CustomerRating;
use App\Models\Projects\Project;
use App\Models\Projects\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerRating>
 */
class CustomerRatingFactory extends Factory
{
    protected $model = CustomerRating::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'contact_id' => Contact::factory()->customer(),
            'rating' => $this->faker->numberBetween(1, 5),
            'comment' => $this->faker->optional()->sentence(),
            'rated_at' => now(),
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
            'rateable_type' => 'project',
            'rateable_id' => $project->id,
        ]);
    }

    public function forTask(Task $task): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $task->project_id,
            'rateable_type' => 'task',
            'rateable_id' => $task->id,
        ]);
    }
}
