<?php

namespace Database\Factories\Manufacturing;

use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Manufacturing\SubcontractorWorkOrder;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Projects\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubcontractorWorkOrder>
 */
class SubcontractorWorkOrderFactory extends Factory
{
    protected $model = SubcontractorWorkOrder::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $agreedAmount = $this->faker->numberBetween(5000000, 100000000);
        $retentionPercent = SubcontractorWorkOrder::DEFAULT_RETENTION_PERCENT;
        $retentionAmount = (int) round($agreedAmount * ($retentionPercent / 100));

        $scheduledStart = $this->faker->dateTimeBetween('now', '+2 weeks');
        $scheduledEnd = $this->faker->dateTimeBetween($scheduledStart, '+2 months');

        return [
            'sc_wo_number' => 'SC-'.now()->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'subcontractor_id' => Contact::factory()->subcontractor(),
            'work_order_id' => null,
            'project_id' => null,
            'name' => 'Subkontrak '.$this->faker->words(3, true),
            'description' => $this->faker->optional()->paragraph(),
            'scope_of_work' => $this->faker->optional()->paragraph(),
            'status' => DocumentStatus::Draft,
            'agreed_amount' => $agreedAmount,
            'actual_amount' => 0,
            'retention_percent' => $retentionPercent,
            'retention_amount' => $retentionAmount,
            'amount_invoiced' => 0,
            'amount_paid' => 0,
            'amount_due' => $agreedAmount - $retentionAmount,
            'scheduled_start_date' => $scheduledStart,
            'scheduled_end_date' => $scheduledEnd,
            'actual_start_date' => null,
            'actual_end_date' => null,
            'completion_percentage' => 0,
            'work_location' => $this->faker->optional()->city(),
            'location_address' => $this->faker->optional()->address(),
            'notes' => $this->faker->optional()->sentence(),
            'created_by' => null,
        ];
    }

    /**
     * Draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Draft,
        ]);
    }

    /**
     * Assigned status.
     */
    public function assigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Assigned,
            'assigned_at' => now(),
        ]);
    }

    /**
     * In progress status.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::InProgress,
            'assigned_at' => now()->subDays(3),
            'started_at' => now(),
            'actual_start_date' => now(),
            'completion_percentage' => $this->faker->numberBetween(10, 80),
        ]);
    }

    /**
     * Completed status.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $agreedAmount = $attributes['agreed_amount'] ?? 10000000;
            $actualAmount = $agreedAmount + $this->faker->numberBetween(-1000000, 1000000);
            $retentionPercent = $attributes['retention_percent'] ?? SubcontractorWorkOrder::DEFAULT_RETENTION_PERCENT;
            $retentionAmount = (int) round($actualAmount * ($retentionPercent / 100));

            return [
                'status' => DocumentStatus::Completed,
                'assigned_at' => now()->subWeeks(2),
                'started_at' => now()->subWeek(),
                'completed_at' => now(),
                'actual_start_date' => now()->subWeek(),
                'actual_end_date' => now(),
                'actual_amount' => $actualAmount,
                'retention_amount' => $retentionAmount,
                'amount_due' => $actualAmount - $retentionAmount,
                'completion_percentage' => 100,
            ];
        });
    }

    /**
     * Cancelled status.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Dibatalkan oleh pengguna',
        ]);
    }

    /**
     * For specific subcontractor.
     */
    public function forSubcontractor(Contact $subcontractor): static
    {
        return $this->state(fn (array $attributes) => [
            'subcontractor_id' => $subcontractor->id,
        ]);
    }

    /**
     * For specific project.
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
            'sc_wo_number' => $project->project_number.'-SC-'.$this->faker->unique()->numerify('###'),
        ]);
    }

    /**
     * For specific work order.
     */
    public function forWorkOrder(WorkOrder $workOrder): static
    {
        return $this->state(fn (array $attributes) => [
            'work_order_id' => $workOrder->id,
            'project_id' => $workOrder->project_id,
        ]);
    }

    /**
     * With creator.
     */
    public function withCreator(?User $user = null): static
    {
        return $this->state(function (array $attributes) use ($user) {
            $u = $user ?? User::factory()->create();

            return [
                'created_by' => $u->id,
            ];
        });
    }

    /**
     * With specific agreed amount.
     */
    public function withAgreedAmount(int $amount): static
    {
        return $this->state(function (array $attributes) use ($amount) {
            $retentionPercent = $attributes['retention_percent'] ?? SubcontractorWorkOrder::DEFAULT_RETENTION_PERCENT;
            $retentionAmount = (int) round($amount * ($retentionPercent / 100));

            return [
                'agreed_amount' => $amount,
                'retention_amount' => $retentionAmount,
                'amount_due' => $amount - $retentionAmount,
            ];
        });
    }

    /**
     * With custom retention percentage.
     */
    public function withRetention(float $percent): static
    {
        return $this->state(function (array $attributes) use ($percent) {
            $amount = $attributes['agreed_amount'] ?? 10000000;
            $retentionAmount = (int) round($amount * ($percent / 100));

            return [
                'retention_percent' => $percent,
                'retention_amount' => $retentionAmount,
                'amount_due' => $amount - $retentionAmount,
            ];
        });
    }

    /**
     * Without retention.
     */
    public function withoutRetention(): static
    {
        return $this->state(function (array $attributes) {
            $amount = $attributes['agreed_amount'] ?? 10000000;

            return [
                'retention_percent' => 0,
                'retention_amount' => 0,
                'amount_due' => $amount,
            ];
        });
    }

    /**
     * Partially invoiced.
     */
    public function partiallyInvoiced(int $invoicedAmount): static
    {
        return $this->state(function (array $attributes) use ($invoicedAmount) {
            $amount = $attributes['actual_amount'] > 0
                ? $attributes['actual_amount']
                : ($attributes['agreed_amount'] ?? 10000000);
            $retentionAmount = $attributes['retention_amount'] ?? 0;

            return [
                'amount_invoiced' => $invoicedAmount,
                'amount_due' => $amount - $retentionAmount - $invoicedAmount,
            ];
        });
    }

    /**
     * With work location.
     */
    public function withWorkLocation(string $location, ?string $address = null): static
    {
        return $this->state(fn (array $attributes) => [
            'work_location' => $location,
            'location_address' => $address ?? $this->faker->address(),
        ]);
    }
}
