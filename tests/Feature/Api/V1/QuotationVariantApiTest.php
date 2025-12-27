<?php

use App\Models\Accounting\Bom;
use App\Models\Accounting\BomVariantGroup;
use App\Models\Accounting\Quotation;
use App\Models\Accounting\QuotationVariantOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    $user = User::factory()->create();
    Sanctum::actingAs($user);
});

describe('Multi-Option Quotation Filtering', function () {

    it('can filter quotations by quotation_type', function () {
        Quotation::factory()->singleOption()->count(3)->create();
        Quotation::factory()->multiOption()->count(2)->create();

        $response = $this->getJson('/api/v1/quotations?quotation_type=multi_option');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter only multi-option quotations using multi_option_only flag', function () {
        Quotation::factory()->singleOption()->count(3)->create();
        Quotation::factory()->multiOption()->count(2)->create();

        $response = $this->getJson('/api/v1/quotations?multi_option_only=true');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });
});

describe('Quotation Variant Options Management', function () {

    it('can get variant options for a multi-option quotation', function () {
        $quotation = Quotation::factory()->multiOption()->create();
        $bom1 = Bom::factory()->create();
        $bom2 = Bom::factory()->create();

        QuotationVariantOption::factory()
            ->forQuotation($quotation)
            ->forBom($bom1)
            ->budget()
            ->withSortOrder(0)
            ->create();

        QuotationVariantOption::factory()
            ->forQuotation($quotation)
            ->forBom($bom2)
            ->premium()
            ->withSortOrder(1)
            ->create();

        $response = $this->getJson("/api/v1/quotations/{$quotation->id}/variant-options");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.quotation_id', $quotation->id)
            ->assertJsonPath('meta.has_selected_variant', false);
    });

    it('returns error when getting variant options for a single quotation', function () {
        $quotation = Quotation::factory()->singleOption()->create();

        $response = $this->getJson("/api/v1/quotations/{$quotation->id}/variant-options");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Penawaran ini bukan tipe multi-option.');
    });

    it('can sync variant options for a quotation', function () {
        $quotation = Quotation::factory()->draft()->create();
        $bom1 = Bom::factory()->create();
        $bom2 = Bom::factory()->create();
        $bom3 = Bom::factory()->create();

        $response = $this->putJson("/api/v1/quotations/{$quotation->id}/variant-options", [
            'options' => [
                [
                    'bom_id' => $bom1->id,
                    'display_name' => 'Budget',
                    'tagline' => 'Solusi Ekonomis',
                    'selling_price' => 25000000,
                    'features' => ['Garansi 1 Tahun'],
                ],
                [
                    'bom_id' => $bom2->id,
                    'display_name' => 'Standard',
                    'tagline' => 'Pilihan Terbaik',
                    'is_recommended' => true,
                    'selling_price' => 50000000,
                    'features' => ['Garansi 2 Tahun', 'Training Gratis'],
                ],
                [
                    'bom_id' => $bom3->id,
                    'display_name' => 'Premium',
                    'selling_price' => 75000000,
                    'features' => ['Garansi 3 Tahun', 'Support 24/7'],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('message', 'Opsi varian berhasil disimpan.');

        $quotation->refresh();
        expect($quotation->isMultiOption())->toBeTrue();
        expect($quotation->variantOptions)->toHaveCount(3);
    });

    it('validates minimum 2 variant options when syncing', function () {
        $quotation = Quotation::factory()->draft()->create();
        $bom = Bom::factory()->create();

        $response = $this->putJson("/api/v1/quotations/{$quotation->id}/variant-options", [
            'options' => [
                [
                    'bom_id' => $bom->id,
                    'display_name' => 'Single Option',
                    'selling_price' => 25000000,
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['options']);
    });

    it('cannot sync variant options for non-draft quotation', function () {
        $quotation = Quotation::factory()->submitted()->create();
        $bom1 = Bom::factory()->create();
        $bom2 = Bom::factory()->create();

        $response = $this->putJson("/api/v1/quotations/{$quotation->id}/variant-options", [
            'options' => [
                ['bom_id' => $bom1->id, 'display_name' => 'A', 'selling_price' => 100000],
                ['bom_id' => $bom2->id, 'display_name' => 'B', 'selling_price' => 200000],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Penawaran ini tidak dapat diubah.');
    });
});

describe('Variant Selection', function () {

    it('can select a variant for a multi-option quotation', function () {
        $quotation = Quotation::factory()->multiOption()->create();
        $bom1 = Bom::factory()->create();
        $bom2 = Bom::factory()->create();

        $option1 = QuotationVariantOption::factory()
            ->forQuotation($quotation)
            ->forBom($bom1)
            ->create(['selling_price' => 25000000]);

        $option2 = QuotationVariantOption::factory()
            ->forQuotation($quotation)
            ->forBom($bom2)
            ->create(['selling_price' => 50000000]);

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/select-variant", [
            'variant_option_id' => $option2->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.selected_variant_id', $bom2->id)
            ->assertJsonPath('data.has_selected_variant', true)
            ->assertJsonPath('data.total', 50000000);
    });

    it('cannot select variant for a single quotation', function () {
        $quotation = Quotation::factory()->singleOption()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/select-variant", [
            'variant_option_id' => 1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Penawaran ini bukan tipe multi-option.');
    });

    it('cannot select variant from another quotation', function () {
        $quotation1 = Quotation::factory()->multiOption()->create();
        $quotation2 = Quotation::factory()->multiOption()->create();

        $bom = Bom::factory()->create();
        $option = QuotationVariantOption::factory()
            ->forQuotation($quotation2)
            ->forBom($bom)
            ->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation1->id}/select-variant", [
            'variant_option_id' => $option->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Pilihan varian tidak valid untuk penawaran ini.');
    });
});

describe('Variant Comparison', function () {

    it('can get variant comparison data', function () {
        $quotation = Quotation::factory()->multiOption()->create(['subject' => 'Solar Panel Installation']);
        $bom1 = Bom::factory()->create(['total_cost' => 20000000]);
        $bom2 = Bom::factory()->create(['total_cost' => 40000000]);

        QuotationVariantOption::factory()
            ->forQuotation($quotation)
            ->forBom($bom1)
            ->budget()
            ->create(['selling_price' => 25000000, 'sort_order' => 0]);

        QuotationVariantOption::factory()
            ->forQuotation($quotation)
            ->forBom($bom2)
            ->premium()
            ->create(['selling_price' => 75000000, 'sort_order' => 1]);

        $response = $this->getJson("/api/v1/quotations/{$quotation->id}/variant-comparison");

        $response->assertOk()
            ->assertJsonPath('data.quotation.subject', 'Solar Panel Installation')
            ->assertJsonCount(2, 'data.options')
            ->assertJsonPath('data.price_range.min', 25000000)
            ->assertJsonPath('data.price_range.max', 75000000);
    });

    it('returns error for variant comparison on single quotation', function () {
        $quotation = Quotation::factory()->singleOption()->create();

        $response = $this->getJson("/api/v1/quotations/{$quotation->id}/variant-comparison");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Penawaran ini bukan tipe multi-option.');
    });
});

describe('Quotation Show with Variants', function () {

    it('includes variant data when showing multi-option quotation', function () {
        $variantGroup = BomVariantGroup::factory()->create();
        $quotation = Quotation::factory()->multiOption()->create([
            'variant_group_id' => $variantGroup->id,
        ]);

        $bom = Bom::factory()->create();
        QuotationVariantOption::factory()
            ->forQuotation($quotation)
            ->forBom($bom)
            ->create();

        QuotationVariantOption::factory()
            ->forQuotation($quotation)
            ->create();

        $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

        $response->assertOk()
            ->assertJsonPath('data.quotation_type', 'multi_option')
            ->assertJsonPath('data.is_multi_option', true)
            ->assertJsonStructure([
                'data' => [
                    'variant_group',
                    'variant_options',
                    'variant_comparison',
                ],
            ]);
    });

    it('does not include variant data for single quotation', function () {
        $quotation = Quotation::factory()->singleOption()->create();

        $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

        $response->assertOk()
            ->assertJsonPath('data.quotation_type', 'single')
            ->assertJsonPath('data.is_multi_option', false)
            ->assertJsonMissing(['variant_comparison']);
    });
});

describe('Quotation Variant Resource', function () {

    it('includes profit calculations in variant option resource', function () {
        $quotation = Quotation::factory()->multiOption()->create();
        $bom = Bom::factory()->create(['total_cost' => 40000000]);

        QuotationVariantOption::factory()
            ->forQuotation($quotation)
            ->forBom($bom)
            ->create(['selling_price' => 50000000]);

        $response = $this->getJson("/api/v1/quotations/{$quotation->id}/variant-options");

        $response->assertOk();

        $data = $response->json('data.0');
        expect((float) $data['profit_margin'])->toBe(25.0); // (50M - 40M) / 40M * 100
        expect($data['profit_amount'])->toBe(10000000);
    });

    it('returns features and specifications in variant option', function () {
        $quotation = Quotation::factory()->multiOption()->create();
        $bom = Bom::factory()->create();

        QuotationVariantOption::factory()
            ->forQuotation($quotation)
            ->forBom($bom)
            ->create([
                'features' => ['Garansi 2 Tahun', 'Support 24/7'],
                'specifications' => ['efficiency' => 'High', 'material' => 'Import'],
            ]);

        $response = $this->getJson("/api/v1/quotations/{$quotation->id}/variant-options");

        $response->assertOk()
            ->assertJsonPath('data.0.features', ['Garansi 2 Tahun', 'Support 24/7'])
            ->assertJsonPath('data.0.specifications.efficiency', 'High');
    });
});
