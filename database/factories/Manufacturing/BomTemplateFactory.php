<?php

namespace Database\Factories\Manufacturing;

use App\Models\Manufacturing\BomTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BomTemplate>
 */
class BomTemplateFactory extends Factory
{
    protected $model = BomTemplate::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('TPL-???-##')),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'category' => $this->faker->randomElement(array_keys(BomTemplate::getCategories())),
            'thumbnail_path' => null,
            'is_active' => true,
            'usage_count' => 0,
            'created_by' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forCategory(string $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }

    public function withRuleSet(): static
    {
        return $this->afterCreating(function (BomTemplate $template) {
            \App\Models\ElectricalPanel\BomTemplatePanelMeta::sync(
                $template,
                \App\Models\ElectricalPanel\SpecValidationRuleSet::factory()->create()->id
            );
        });
    }

    public function withCreator(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => User::factory(),
        ]);
    }

    public function used(int $count = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_count' => $count,
        ]);
    }
}
