<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\AuditLog;
use App\Models\Accounting\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'user_name' => $this->faker->name(),
            'action' => AuditLog::ACTION_CREATED,
            'auditable_type' => Invoice::class,
            'auditable_id' => Invoice::factory(),
            'auditable_label' => 'INV-' . $this->faker->numerify('####'),
            'old_values' => null,
            'new_values' => ['status' => 'draft'],
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'notes' => null,
        ];
    }

    public function created(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => AuditLog::ACTION_CREATED,
            'old_values' => null,
        ]);
    }

    public function updated(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => AuditLog::ACTION_UPDATED,
            'old_values' => ['status' => 'draft'],
            'new_values' => ['status' => 'sent'],
        ]);
    }

    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => AuditLog::ACTION_DELETED,
            'new_values' => null,
        ]);
    }

    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => AuditLog::ACTION_POSTED,
        ]);
    }

    public function bySystem(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'user_name' => 'System',
        ]);
    }

    public function forModel($model): static
    {
        return $this->state(fn (array $attributes) => [
            'auditable_type' => $model::class,
            'auditable_id' => $model->id,
        ]);
    }
}
