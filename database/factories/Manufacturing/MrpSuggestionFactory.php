<?php

namespace Database\Factories\Manufacturing;

use App\Enums\MrpSuggestionStatus;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\MrpRun;
use App\Models\Manufacturing\MrpSuggestion;
use App\Models\Manufacturing\SubcontractorWorkOrder;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Purchasing\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MrpSuggestion>
 */
class MrpSuggestionFactory extends Factory
{
    protected $model = MrpSuggestion::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $quantityRequired = $this->faker->numberBetween(10, 100);
        $suggestedQty = $this->faker->numberBetween($quantityRequired, $quantityRequired + 20);
        $unitCost = $this->faker->numberBetween(10000, 500000);
        $dueDate = $this->faker->dateTimeBetween('+1 week', '+4 weeks');
        $leadTimeDays = $this->faker->numberBetween(3, 14);

        return [
            'mrp_run_id' => MrpRun::factory(),
            'product_id' => Product::factory(),
            'suggestion_type' => MrpSuggestion::TYPE_PURCHASE,
            'action' => MrpSuggestion::ACTION_CREATE,
            'suggested_order_date' => \Carbon\Carbon::parse($dueDate)->subDays($leadTimeDays),
            'suggested_due_date' => $dueDate,
            'quantity_required' => $quantityRequired,
            'suggested_quantity' => $suggestedQty,
            'adjusted_quantity' => null,
            'suggested_supplier_id' => null,
            'suggested_warehouse_id' => null,
            'estimated_unit_cost' => $unitCost,
            'estimated_total_cost' => $unitCost * $suggestedQty,
            'priority' => MrpSuggestion::PRIORITY_NORMAL,
            'status' => MrpSuggestionStatus::Pending,
            'reason' => 'Kekurangan stok untuk memenuhi permintaan',
            'notes' => $this->faker->optional()->sentence(),
            'converted_to_type' => null,
            'converted_to_id' => null,
            'converted_at' => null,
            'converted_by' => null,
        ];
    }

    /**
     * Purchase suggestion.
     */
    public function purchase(): static
    {
        return $this->state(fn (array $attributes) => [
            'suggestion_type' => MrpSuggestion::TYPE_PURCHASE,
            'reason' => 'Produk perlu dibeli dari supplier',
        ]);
    }

    /**
     * Work order suggestion.
     */
    public function workOrder(): static
    {
        return $this->state(fn (array $attributes) => [
            'suggestion_type' => MrpSuggestion::TYPE_WORK_ORDER,
            'reason' => 'Produk perlu diproduksi internal',
        ]);
    }

    /**
     * Subcontract suggestion.
     */
    public function subcontract(): static
    {
        return $this->state(fn (array $attributes) => [
            'suggestion_type' => MrpSuggestion::TYPE_SUBCONTRACT,
            'reason' => 'Produk perlu dikerjakan oleh subkontraktor',
        ]);
    }

    /**
     * Pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MrpSuggestionStatus::Pending,
        ]);
    }

    /**
     * Accepted status.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MrpSuggestionStatus::Accepted,
        ]);
    }

    /**
     * Rejected status.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MrpSuggestionStatus::Rejected,
            'notes' => 'Tidak diperlukan saat ini',
        ]);
    }

    /**
     * Converted status.
     */
    public function converted(?string $type = null, ?int $id = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MrpSuggestionStatus::Converted,
            'converted_to_type' => $type ?? PurchaseOrder::class,
            'converted_to_id' => $id ?? $this->faker->numberBetween(1, 100),
            'converted_at' => now(),
        ]);
    }

    /**
     * Converted to Purchase Order.
     */
    public function convertedToPurchaseOrder(PurchaseOrder $po): static
    {
        return $this->state(fn (array $attributes) => [
            'suggestion_type' => MrpSuggestion::TYPE_PURCHASE,
            'status' => MrpSuggestionStatus::Converted,
            'converted_to_type' => PurchaseOrder::class,
            'converted_to_id' => $po->id,
            'converted_at' => now(),
        ]);
    }

    /**
     * Converted to Work Order.
     */
    public function convertedToWorkOrder(WorkOrder $wo): static
    {
        return $this->state(fn (array $attributes) => [
            'suggestion_type' => MrpSuggestion::TYPE_WORK_ORDER,
            'status' => MrpSuggestionStatus::Converted,
            'converted_to_type' => WorkOrder::class,
            'converted_to_id' => $wo->id,
            'converted_at' => now(),
        ]);
    }

    /**
     * Converted to Subcontractor Work Order.
     */
    public function convertedToSubcontractorWorkOrder(SubcontractorWorkOrder $scWo): static
    {
        return $this->state(fn (array $attributes) => [
            'suggestion_type' => MrpSuggestion::TYPE_SUBCONTRACT,
            'status' => MrpSuggestionStatus::Converted,
            'converted_to_type' => SubcontractorWorkOrder::class,
            'converted_to_id' => $scWo->id,
            'converted_at' => now(),
        ]);
    }

    /**
     * For specific MRP run.
     */
    public function forMrpRun(MrpRun $run): static
    {
        return $this->state(fn (array $attributes) => [
            'mrp_run_id' => $run->id,
        ]);
    }

    /**
     * For specific product.
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
            'estimated_unit_cost' => $product->purchase_price ?? $this->faker->numberBetween(10000, 500000),
        ]);
    }

    /**
     * With suggested supplier.
     */
    public function withSupplier(?Contact $supplier = null): static
    {
        return $this->state(function (array $attributes) use ($supplier) {
            $s = $supplier ?? Contact::factory()->supplier()->create();

            return [
                'suggested_supplier_id' => $s->id,
            ];
        });
    }

    /**
     * With suggested warehouse.
     */
    public function withWarehouse(?Warehouse $warehouse = null): static
    {
        return $this->state(function (array $attributes) use ($warehouse) {
            $w = $warehouse ?? Warehouse::factory()->create();

            return [
                'suggested_warehouse_id' => $w->id,
            ];
        });
    }

    /**
     * Urgent priority.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => MrpSuggestion::PRIORITY_URGENT,
        ]);
    }

    /**
     * High priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => MrpSuggestion::PRIORITY_HIGH,
        ]);
    }

    /**
     * With adjusted quantity.
     */
    public function withAdjustedQuantity(float $quantity): static
    {
        return $this->state(function (array $attributes) use ($quantity) {
            $unitCost = $attributes['estimated_unit_cost'] ?? 10000;

            return [
                'adjusted_quantity' => $quantity,
                'estimated_total_cost' => (int) round($unitCost * $quantity),
            ];
        });
    }
}
