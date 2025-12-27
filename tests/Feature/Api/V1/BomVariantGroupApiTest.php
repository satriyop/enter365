<?php

use App\Models\Accounting\Bom;
use App\Models\Accounting\BomItem;
use App\Models\Accounting\BomVariantGroup;
use App\Models\Accounting\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    // Authenticate user
    $user = User::factory()->create();
    Sanctum::actingAs($user);
});

describe('BOM Variant Group CRUD', function () {

    it('can list all variant groups', function () {
        BomVariantGroup::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/bom-variant-groups');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter variant groups by product', function () {
        $product = Product::factory()->create();
        BomVariantGroup::factory()->forProduct($product)->count(2)->create();
        BomVariantGroup::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/bom-variant-groups?product_id={$product->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter variant groups by status', function () {
        BomVariantGroup::factory()->draft()->count(2)->create();
        BomVariantGroup::factory()->active()->count(3)->create();

        $response = $this->getJson('/api/v1/bom-variant-groups?status=active');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can search variant groups', function () {
        BomVariantGroup::factory()->create(['name' => 'PLTS Material Options']);
        BomVariantGroup::factory()->create(['name' => 'Panel Electrical Options']);
        BomVariantGroup::factory()->create(['name' => 'Inverter Comparison']);

        $response = $this->getJson('/api/v1/bom-variant-groups?search=options');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can create a variant group', function () {
        $product = Product::factory()->create(['name' => 'PLTS 50 kWp']);

        $response = $this->postJson('/api/v1/bom-variant-groups', [
            'product_id' => $product->id,
            'name' => 'Material Options for PLTS 50kWp',
            'description' => 'Compare different material configurations',
            'comparison_notes' => 'Budget uses Growatt, Premium uses SMA',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Material Options for PLTS 50kWp')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.product.name', 'PLTS 50 kWp');
    });

    it('can create variant group with existing BOMs', function () {
        $product = Product::factory()->create();
        $bom1 = Bom::factory()->forProduct($product)->create();
        $bom2 = Bom::factory()->forProduct($product)->create();

        $response = $this->postJson('/api/v1/bom-variant-groups', [
            'product_id' => $product->id,
            'name' => 'Comparison Group',
            'bom_ids' => [$bom1->id, $bom2->id],
        ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'data.boms');

        $bom1->refresh();
        $bom2->refresh();

        expect($bom1->variant_group_id)->not->toBeNull();
        expect($bom2->variant_group_id)->not->toBeNull();
    });

    it('validates required fields when creating variant group', function () {
        $response = $this->postJson('/api/v1/bom-variant-groups', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'product_id']);
    });

    it('can show a single variant group with boms', function () {
        $group = BomVariantGroup::factory()->create();
        Bom::factory()->forVariantGroup($group, 'Budget')->withTotals(500000, 100000, 50000)->create();
        Bom::factory()->forVariantGroup($group, 'Premium')->withTotals(800000, 150000, 80000)->create();

        $response = $this->getJson("/api/v1/bom-variant-groups/{$group->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $group->id)
            ->assertJsonCount(2, 'data.boms');
    });

    it('can update a variant group', function () {
        $group = BomVariantGroup::factory()->create();

        $response = $this->putJson("/api/v1/bom-variant-groups/{$group->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'status' => 'active',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.status', 'active');
    });

    it('can delete a variant group', function () {
        $group = BomVariantGroup::factory()->create();
        $bom = Bom::factory()->forVariantGroup($group)->create();

        $response = $this->deleteJson("/api/v1/bom-variant-groups/{$group->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('bom_variant_groups', ['id' => $group->id]);

        // BOM should still exist but unlinked
        $bom->refresh();
        expect($bom->variant_group_id)->toBeNull();
        expect($bom->variant_name)->toBeNull();
    });
});

describe('BOM Variant Group - BOM Management', function () {

    it('can add a bom to variant group', function () {
        $product = Product::factory()->create();
        $group = BomVariantGroup::factory()->forProduct($product)->create();
        $bom = Bom::factory()->forProduct($product)->create();

        $response = $this->postJson("/api/v1/bom-variant-groups/{$group->id}/boms", [
            'bom_id' => $bom->id,
            'variant_name' => 'Budget',
            'variant_label' => 'Growatt + NUSA mounting',
            'is_primary_variant' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.variant_name', 'Budget')
            ->assertJsonPath('data.variant_label', 'Growatt + NUSA mounting')
            ->assertJsonPath('data.is_primary_variant', true);
    });

    it('cannot add bom from different product to variant group', function () {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        $group = BomVariantGroup::factory()->forProduct($product1)->create();
        $bom = Bom::factory()->forProduct($product2)->create();

        $response = $this->postJson("/api/v1/bom-variant-groups/{$group->id}/boms", [
            'bom_id' => $bom->id,
            'variant_name' => 'Budget',
        ]);

        $response->assertUnprocessable();
    });

    it('cannot add bom already in another variant group', function () {
        $product = Product::factory()->create();
        $group1 = BomVariantGroup::factory()->forProduct($product)->create();
        $group2 = BomVariantGroup::factory()->forProduct($product)->create();
        $bom = Bom::factory()->forVariantGroup($group1)->create();

        $response = $this->postJson("/api/v1/bom-variant-groups/{$group2->id}/boms", [
            'bom_id' => $bom->id,
            'variant_name' => 'Budget',
        ]);

        $response->assertUnprocessable();
    });

    it('can remove bom from variant group', function () {
        $group = BomVariantGroup::factory()->create();
        $bom = Bom::factory()->forVariantGroup($group, 'Budget')->create();

        $response = $this->deleteJson("/api/v1/bom-variant-groups/{$group->id}/boms/{$bom->id}");

        $response->assertOk();

        $bom->refresh();
        expect($bom->variant_group_id)->toBeNull();
        expect($bom->variant_name)->toBeNull();
    });

    it('can set primary variant', function () {
        $group = BomVariantGroup::factory()->create();
        $bom1 = Bom::factory()->forVariantGroup($group, 'Budget')->asPrimaryVariant()->create();
        $bom2 = Bom::factory()->forVariantGroup($group, 'Premium')->create();

        $response = $this->postJson("/api/v1/bom-variant-groups/{$group->id}/boms/{$bom2->id}/set-primary");

        $response->assertOk()
            ->assertJsonPath('data.is_primary_variant', true);

        $bom1->refresh();
        expect($bom1->is_primary_variant)->toBeFalse();
    });

    it('can reorder variants', function () {
        $group = BomVariantGroup::factory()->create();
        $bom1 = Bom::factory()->forVariantGroup($group)->withVariantSortOrder(0)->create();
        $bom2 = Bom::factory()->forVariantGroup($group)->withVariantSortOrder(1)->create();
        $bom3 = Bom::factory()->forVariantGroup($group)->withVariantSortOrder(2)->create();

        $response = $this->postJson("/api/v1/bom-variant-groups/{$group->id}/reorder", [
            'order' => [
                ['bom_id' => $bom3->id, 'sort_order' => 0],
                ['bom_id' => $bom1->id, 'sort_order' => 1],
                ['bom_id' => $bom2->id, 'sort_order' => 2],
            ],
        ]);

        $response->assertOk();

        $bom1->refresh();
        $bom2->refresh();
        $bom3->refresh();

        expect($bom3->variant_sort_order)->toBe(0);
        expect($bom1->variant_sort_order)->toBe(1);
        expect($bom2->variant_sort_order)->toBe(2);
    });

    it('can create variant from existing bom', function () {
        $product = Product::factory()->create();
        $group = BomVariantGroup::factory()->forProduct($product)->create();
        $sourceBom = Bom::factory()->forProduct($product)->create(['name' => 'Original BOM']);
        BomItem::factory()->forBom($sourceBom)->material()->count(3)->create();
        BomItem::factory()->forBom($sourceBom)->labor()->create();

        $response = $this->postJson("/api/v1/bom-variant-groups/{$group->id}/create-variant", [
            'source_bom_id' => $sourceBom->id,
            'variant_name' => 'Premium',
            'variant_label' => 'SMA + LONGi',
            'name' => 'Premium Version BOM',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.variant_name', 'Premium')
            ->assertJsonPath('data.name', 'Premium Version BOM')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonCount(4, 'data.items');

        // Source BOM should remain unchanged
        $sourceBom->refresh();
        expect($sourceBom->variant_group_id)->toBeNull();
    });
});

describe('BOM Variant Group Comparison', function () {

    it('can get side-by-side comparison data', function () {
        $group = BomVariantGroup::factory()->create();

        $budgetBom = Bom::factory()
            ->forVariantGroup($group, 'Budget')
            ->withTotals(500000000, 50000000, 25000000)
            ->create();

        $premiumBom = Bom::factory()
            ->forVariantGroup($group, 'Premium')
            ->withTotals(800000000, 80000000, 40000000)
            ->create();

        $response = $this->getJson("/api/v1/bom-variant-groups/{$group->id}/compare");

        $response->assertOk()
            ->assertJsonCount(2, 'data.variants')
            ->assertJsonPath('data.summary.total_variants', 2)
            ->assertJsonPath('data.summary.cheapest_variant', 'Budget')
            ->assertJsonPath('data.summary.most_expensive_variant', 'Premium');

        // Verify cost range
        $costRange = $response->json('data.summary.cost_range');
        expect($costRange['min'])->toBe(575000000);  // 500M + 50M + 25M
        expect($costRange['max'])->toBe(920000000);  // 800M + 80M + 40M
    });

    it('can get detailed item-level comparison', function () {
        $product = Product::factory()->create();
        $group = BomVariantGroup::factory()->forProduct($product)->create();

        $bom1 = Bom::factory()->forVariantGroup($group, 'Budget')->create();
        $material1 = Product::factory()->create(['name' => 'Inverter Growatt']);
        BomItem::factory()->forBom($bom1)->material()->create([
            'product_id' => $material1->id,
            'quantity' => 1,
            'unit_cost' => 50000000,
        ]);

        $bom2 = Bom::factory()->forVariantGroup($group, 'Premium')->create();
        $material2 = Product::factory()->create(['name' => 'Inverter SMA']);
        BomItem::factory()->forBom($bom2)->material()->create([
            'product_id' => $material2->id,
            'quantity' => 1,
            'unit_cost' => 120000000,
        ]);

        $response = $this->getJson("/api/v1/bom-variant-groups/{$group->id}/compare-detailed");

        $response->assertOk()
            ->assertJsonCount(2, 'data.variants')
            ->assertJsonStructure([
                'data' => [
                    'product',
                    'variants',
                    'item_comparison',
                    'summary',
                ],
            ]);
    });

    it('returns empty comparison for group without boms', function () {
        $group = BomVariantGroup::factory()->create();

        $response = $this->getJson("/api/v1/bom-variant-groups/{$group->id}/compare");

        $response->assertOk()
            ->assertJsonCount(0, 'data.variants')
            ->assertJsonPath('data.summary', []);
    });
});

describe('BOM Variant Group - Response Structure', function () {

    it('includes cost summary in list response', function () {
        $group = BomVariantGroup::factory()->create();
        Bom::factory()->forVariantGroup($group)->withTotals(100000, 20000, 10000)->create();
        Bom::factory()->forVariantGroup($group)->withTotals(200000, 40000, 20000)->create();

        $response = $this->getJson('/api/v1/bom-variant-groups');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'product_id',
                        'name',
                        'status',
                        'variants_count',
                        'cost_summary' => [
                            'min',
                            'max',
                            'difference',
                        ],
                    ],
                ],
            ]);

        $costSummary = $response->json('data.0.cost_summary');
        expect($costSummary['min'])->toBe(130000);
        expect($costSummary['max'])->toBe(260000);
        expect($costSummary['difference'])->toBe(130000);
    });

    it('includes bom cost breakdown in show response', function () {
        $group = BomVariantGroup::factory()->create();
        Bom::factory()->forVariantGroup($group, 'Budget')
            ->withTotals(500000, 100000, 50000)
            ->create();

        $response = $this->getJson("/api/v1/bom-variant-groups/{$group->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'boms' => [
                        '*' => [
                            'id',
                            'bom_number',
                            'variant_name',
                            'variant_label',
                            'is_primary_variant',
                            'total_cost',
                            'unit_cost',
                            'cost_breakdown' => [
                                'material',
                                'labor',
                                'overhead',
                                'total',
                                'unit_cost',
                            ],
                        ],
                    ],
                ],
            ]);
    });
});
