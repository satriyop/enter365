<?php

namespace Database\Factories\Inventory;

use App\Enums\DocumentStatus;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    public function definition(): array
    {
        return [
            'operation_type' => StockTransfer::OPERATION_INTERNAL,
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id' => Warehouse::factory(),
            'contact_id' => null,
            'scheduled_date' => now()->toDateString(),
            'source_document' => null,
            'status' => DocumentStatus::Draft,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => DocumentStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
