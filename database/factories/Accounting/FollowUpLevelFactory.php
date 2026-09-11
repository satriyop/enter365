<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\FollowUpLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FollowUpLevel>
 */
class FollowUpLevelFactory extends Factory
{
    protected $model = FollowUpLevel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'delay_days' => $this->faker->randomElement([-3, 1, 7, 14, 30]),
            'sequence' => 10,
            'send_email' => true,
            'join_invoices' => true,
            'message' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
