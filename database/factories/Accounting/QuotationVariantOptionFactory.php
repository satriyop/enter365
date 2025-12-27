<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Bom;
use App\Models\Accounting\Quotation;
use App\Models\Accounting\QuotationVariantOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationVariantOption>
 */
class QuotationVariantOptionFactory extends Factory
{
    protected $model = QuotationVariantOption::class;

    private static int $sortOrder = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $variants = ['Budget', 'Standard', 'Premium', 'Enterprise'];
        $taglines = [
            'Budget' => 'Solusi Ekonomis',
            'Standard' => 'Pilihan Terbaik',
            'Premium' => 'Kualitas Premium',
            'Enterprise' => 'Tingkat Enterprise',
        ];

        $variantName = $this->faker->randomElement($variants);

        return [
            'quotation_id' => Quotation::factory()->multiOption(),
            'bom_id' => Bom::factory(),
            'display_name' => $variantName,
            'tagline' => $taglines[$variantName] ?? null,
            'is_recommended' => false,
            'selling_price' => $this->faker->randomElement([25000000, 50000000, 75000000, 100000000]),
            'features' => $this->faker->randomElements([
                'Garansi 1 Tahun',
                'Garansi 2 Tahun',
                'Garansi 3 Tahun',
                'Support 24/7',
                'Instalasi Gratis',
                'Training Gratis',
                'Maintenance Gratis 6 Bulan',
            ], 3),
            'specifications' => [
                'material' => $this->faker->randomElement(['Lokal', 'Import', 'Premium Import']),
                'efficiency' => $this->faker->randomElement(['Standard', 'High', 'Ultra High']),
            ],
            'warranty_terms' => $this->faker->randomElement(['1 Tahun', '2 Tahun', '3 Tahun']),
            'sort_order' => self::$sortOrder++,
        ];
    }

    public function budget(): static
    {
        return $this->state(fn (array $attributes) => [
            'display_name' => 'Budget',
            'tagline' => 'Solusi Ekonomis',
            'selling_price' => 25000000,
            'features' => ['Garansi 1 Tahun', 'Instalasi Gratis'],
            'is_recommended' => false,
        ]);
    }

    public function standard(): static
    {
        return $this->state(fn (array $attributes) => [
            'display_name' => 'Standard',
            'tagline' => 'Pilihan Terbaik',
            'selling_price' => 50000000,
            'features' => ['Garansi 2 Tahun', 'Instalasi Gratis', 'Training Gratis'],
            'is_recommended' => true,
        ]);
    }

    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'display_name' => 'Premium',
            'tagline' => 'Kualitas Premium',
            'selling_price' => 75000000,
            'features' => ['Garansi 3 Tahun', 'Support 24/7', 'Instalasi Gratis', 'Training Gratis', 'Maintenance Gratis 6 Bulan'],
            'is_recommended' => false,
        ]);
    }

    public function recommended(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_recommended' => true,
        ]);
    }

    public function forQuotation(Quotation $quotation): static
    {
        return $this->state(fn (array $attributes) => [
            'quotation_id' => $quotation->id,
        ]);
    }

    public function forBom(Bom $bom): static
    {
        return $this->state(fn (array $attributes) => [
            'bom_id' => $bom->id,
        ]);
    }

    public function withSortOrder(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'sort_order' => $order,
        ]);
    }
}
