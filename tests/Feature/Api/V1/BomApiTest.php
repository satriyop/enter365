<?php

use App\Enums\DocumentStatus;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    // Authenticate as admin (has all permissions)
    authenticatedAdmin();
});

describe('BOM CRUD', function () {

    it('can list all boms', function () {
        Bom::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/boms');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter boms by status', function () {
        Bom::factory()->draft()->count(2)->create();
        Bom::factory()->active()->count(3)->create();

        $response = $this->getJson('/api/v1/boms?status=active');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter boms by product', function () {
        $product = Product::factory()->create();
        Bom::factory()->forProduct($product)->count(2)->create();
        Bom::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/boms?product_id={$product->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can search boms', function () {
        $product1 = Product::factory()->create(['name' => 'Product A', 'sku' => 'SKU-A001']);
        $product2 = Product::factory()->create(['name' => 'Product B', 'sku' => 'SKU-B001']);
        $product3 = Product::factory()->create(['name' => 'Product C', 'sku' => 'SKU-C001']);

        Bom::factory()->forProduct($product1)->create(['name' => 'Panel Assembly 100A']);
        Bom::factory()->forProduct($product2)->create(['name' => 'Solar System Assembly']);
        Bom::factory()->forProduct($product3)->create(['bom_number' => 'BOM-PANEL-001', 'name' => 'Other Assembly']);

        $response = $this->getJson('/api/v1/boms?search=panel');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can create a bom with items', function () {
        $product = Product::factory()->create(['name' => 'Panel Listrik 100A']);
        $material = Product::factory()->create(['name' => 'MCB 100A']);

        $response = $this->postJson('/api/v1/boms', [
            'name' => 'Panel Assembly 100A',
            'description' => 'Assembly instruction for 100A panel',
            'product_id' => $product->id,
            'output_quantity' => 1,
            'output_unit' => 'unit',
            'items' => [
                [
                    'type' => 'material',
                    'product_id' => $material->id,
                    'description' => 'MCB 100A',
                    'quantity' => 1,
                    'unit' => 'pcs',
                    'unit_cost' => 2500000,
                ],
                [
                    'type' => 'labor',
                    'description' => 'Assembly Labor',
                    'quantity' => 2,
                    'unit' => 'jam',
                    'unit_cost' => 100000,
                ],
                [
                    'type' => 'overhead',
                    'description' => 'Workshop Overhead',
                    'quantity' => 1,
                    'unit' => 'lot',
                    'unit_cost' => 50000,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonPath('data.name', 'Panel Assembly 100A')
            ->assertJsonCount(3, 'data.items');

        // Verify cost calculations:
        // Material: 2,500,000
        // Labor: 200,000
        // Overhead: 50,000
        // Total: 2,750,000
        $response->assertJsonPath('data.total_material_cost', 2500000)
            ->assertJsonPath('data.total_labor_cost', 200000)
            ->assertJsonPath('data.total_overhead_cost', 50000)
            ->assertJsonPath('data.total_cost', 2750000)
            ->assertJsonPath('data.unit_cost', 2750000);
    });

    it('validates required fields when creating bom', function () {
        $response = $this->postJson('/api/v1/boms', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'product_id', 'items']);
    });

    it('validates items have required fields', function () {
        $product = Product::factory()->create();

        $response = $this->postJson('/api/v1/boms', [
            'name' => 'Test BOM',
            'product_id' => $product->id,
            'items' => [
                ['type' => 'material'],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.description', 'items.0.quantity', 'items.0.unit_cost']);
    });

    it('can show a single bom with items', function () {
        $bom = Bom::factory()->create();
        BomItem::factory()->forBom($bom)->count(3)->create();

        $response = $this->getJson("/api/v1/boms/{$bom->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $bom->id)
            ->assertJsonCount(3, 'data.items');
    });

    it('can update a draft bom', function () {
        $bom = Bom::factory()->draft()->create();
        BomItem::factory()->forBom($bom)->create();

        $response = $this->putJson("/api/v1/boms/{$bom->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.description', 'Updated description');
    });

    it('cannot update non-draft bom', function () {
        $bom = Bom::factory()->active()->create();

        $response = $this->putJson("/api/v1/boms/{$bom->id}", [
            'name' => 'Should fail',
        ]);

        $response->assertUnprocessable();
    });

    it('can update bom items', function () {
        $bom = Bom::factory()->draft()->create();
        BomItem::factory()->forBom($bom)->create();

        $response = $this->putJson("/api/v1/boms/{$bom->id}", [
            'items' => [
                ['type' => 'material', 'description' => 'New Material 1', 'quantity' => 2, 'unit_cost' => 300000],
                ['type' => 'labor', 'description' => 'New Labor', 'quantity' => 1, 'unit_cost' => 150000],
            ],
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.total_material_cost', 600000)
            ->assertJsonPath('data.total_labor_cost', 150000);
    });

    it('can delete a draft bom', function () {
        $bom = Bom::factory()->draft()->create();

        $response = $this->deleteJson("/api/v1/boms/{$bom->id}");

        $response->assertOk();
        $this->assertSoftDeleted('boms', ['id' => $bom->id]);
    });

    it('cannot delete non-draft bom', function () {
        $bom = Bom::factory()->active()->create();

        $response = $this->deleteJson("/api/v1/boms/{$bom->id}");

        $response->assertUnprocessable();
    });
});

describe('BOM Workflow', function () {

    it('can activate a draft bom with items', function () {
        $bom = Bom::factory()->draft()->create();
        BomItem::factory()->forBom($bom)->count(2)->create();

        $response = $this->postJson("/api/v1/boms/{$bom->id}/activate");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'active');

        $bom->refresh();
        expect($bom->approved_at)->not->toBeNull();
    });

    it('cannot activate bom without items', function () {
        $bom = Bom::factory()->draft()->create();

        $response = $this->postJson("/api/v1/boms/{$bom->id}/activate");

        $response->assertUnprocessable();
    });

    it('cannot activate non-draft bom', function () {
        $bom = Bom::factory()->active()->create();

        $response = $this->postJson("/api/v1/boms/{$bom->id}/activate");

        $response->assertUnprocessable();
    });

    it('deactivates existing active bom when activating new one for same product', function () {
        $product = Product::factory()->create();

        $oldBom = Bom::factory()->forProduct($product)->active()->create();
        BomItem::factory()->forBom($oldBom)->create();

        $newBom = Bom::factory()->forProduct($product)->draft()->create();
        BomItem::factory()->forBom($newBom)->create();

        $response = $this->postJson("/api/v1/boms/{$newBom->id}/activate");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'active');

        $oldBom->refresh();
        expect($oldBom->status)->toBe(DocumentStatus::Inactive);
    });

    it('can deactivate an active bom', function () {
        $bom = Bom::factory()->active()->create();

        $response = $this->postJson("/api/v1/boms/{$bom->id}/deactivate");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'inactive');
    });

    it('cannot deactivate non-active bom', function () {
        $bom = Bom::factory()->draft()->create();

        $response = $this->postJson("/api/v1/boms/{$bom->id}/deactivate");

        $response->assertUnprocessable();
    });
});

describe('BOM Duplicate', function () {

    it('can duplicate a bom', function () {
        $bom = Bom::factory()->active()->create([
            'name' => 'Original BOM',
            'version' => '1.0',
        ]);
        BomItem::factory()->forBom($bom)->count(3)->create();

        $response = $this->postJson("/api/v1/boms/{$bom->id}/duplicate");

        $response->assertCreated()
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonPath('data.name', 'Original BOM')
            ->assertJsonPath('data.version', '1.1')
            ->assertJsonPath('data.parent_bom_id', $bom->id)
            ->assertJsonCount(3, 'data.items');

        // New BOM number
        expect($response->json('data.bom_number'))->not->toBe($bom->bom_number);
    });

    it('increments version correctly on multiple duplicates', function () {
        $bom = Bom::factory()->create(['version' => '2.3']);
        BomItem::factory()->forBom($bom)->create();

        $response = $this->postJson("/api/v1/boms/{$bom->id}/duplicate");

        $response->assertCreated()
            ->assertJsonPath('data.version', '2.4');
    });
});

describe('BOM for Product', function () {

    it('can get active bom for product', function () {
        $product = Product::factory()->create();
        $bom = Bom::factory()->forProduct($product)->active()->create();
        BomItem::factory()->forBom($bom)->count(2)->create();

        $response = $this->getJson("/api/v1/boms-for-product/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $bom->id)
            ->assertJsonCount(2, 'data.items');
    });

    it('returns 404 when product has no active bom', function () {
        $product = Product::factory()->create();
        Bom::factory()->forProduct($product)->draft()->create();

        $response = $this->getJson("/api/v1/boms-for-product/{$product->id}");

        $response->assertNotFound();
    });
});

describe('BOM Cost Calculation', function () {

    it('can calculate production cost for quantity', function () {
        $bom = Bom::factory()->active()->create([
            'output_quantity' => 1,
            'total_material_cost' => 2000000,
            'total_labor_cost' => 500000,
            'total_overhead_cost' => 200000,
            'total_cost' => 2700000,
        ]);
        BomItem::factory()->forBom($bom)->material()->create([
            'quantity' => 1,
            'unit_cost' => 2000000,
            'total_cost' => 2000000,
            'waste_percentage' => 0,
        ]);

        $response = $this->postJson('/api/v1/boms-calculate-cost', [
            'bom_id' => $bom->id,
            'quantity' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.bom_id', $bom->id)
            ->assertJsonPath('data.quantity_to_produce', 10);

        // Material cost should be multiplied by 10
        expect($response->json('data.cost_breakdown.material'))->toBe(20000000);
        expect($response->json('data.cost_breakdown.labor'))->toBe(5000000);
        expect($response->json('data.cost_breakdown.overhead'))->toBe(2000000);
        expect($response->json('data.total_cost'))->toBe(27000000);
    });

    it('validates required fields for cost calculation', function () {
        $response = $this->postJson('/api/v1/boms-calculate-cost', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['bom_id', 'quantity']);
    });

    it('cannot calculate cost for non-active bom', function () {
        $bom = Bom::factory()->draft()->create();
        BomItem::factory()->forBom($bom)->create();

        $response = $this->postJson('/api/v1/boms-calculate-cost', [
            'bom_id' => $bom->id,
            'quantity' => 5,
        ]);

        $response->assertUnprocessable();
    });
});

describe('BOM Statistics', function () {

    it('returns bom statistics', function () {
        Bom::factory()->draft()->count(2)->create();
        Bom::factory()->active()->count(3)->create();
        Bom::factory()->inactive()->count(1)->create();

        $response = $this->getJson('/api/v1/boms-statistics');

        $response->assertOk()
            ->assertJsonPath('data.total_count', 6)
            ->assertJsonPath('data.draft_count', 2)
            ->assertJsonPath('data.active_count', 3)
            ->assertJsonPath('data.inactive_count', 1);
    });
});

describe('BOM with Waste Percentage', function () {

    it('calculates cost with waste percentage', function () {
        $product = Product::factory()->create();

        $response = $this->postJson('/api/v1/boms', [
            'name' => 'BOM with Waste',
            'product_id' => $product->id,
            'items' => [
                [
                    'type' => 'material',
                    'description' => 'Material with waste',
                    'quantity' => 10,
                    'unit' => 'kg',
                    'unit_cost' => 100000,
                    'waste_percentage' => 10,
                ],
            ],
        ]);

        $response->assertCreated();

        // Base: 10 * 100,000 = 1,000,000
        // With 10% waste: 1,000,000 * 1.1 = 1,100,000
        $response->assertJsonPath('data.total_material_cost', 1100000);
    });
});
