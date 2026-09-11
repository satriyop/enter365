<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\CheckSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CheckSetting> */
class CheckSettingFactory extends Factory
{
    protected $model = CheckSetting::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PAY-????-####')),
            'name' => fake()->words(3, true),
            'is_active' => true,
            'journal_id' => \App\Models\Accounting\Journal::factory()->state(['type' => 'bank']),
            'next_number' => 1,
            'layout' => 'top',
            'manual_numbering' => false,
        ];
    }
}
