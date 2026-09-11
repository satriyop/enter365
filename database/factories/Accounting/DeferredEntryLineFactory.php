<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\DeferredEntry;
use App\Models\Accounting\DeferredEntryLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeferredEntryLine>
 */
class DeferredEntryLineFactory extends Factory
{
    protected $model = DeferredEntryLine::class;

    public function definition(): array
    {
        return [
            'deferred_entry_id' => DeferredEntry::factory(),
            'sequence' => 1,
            'recognition_date' => now()->endOfMonth()->toDateString(),
            'amount' => 100_000,
            'remaining_amount' => 1_100_000,
            'status' => DeferredEntryLine::STATUS_DRAFT,
        ];
    }
}
