<?php

use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;
use App\Models\Manufacturing\ComponentBrandMapping;
use App\Models\Manufacturing\ComponentStandard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);

    Storage::fake('public');
});

describe('BOM Template CRUD', function () {

    it('can list all templates', function () {
        BomTemplate::factory()->count(10)->create();

        $this->assertMaxQueries(15, function () {
            $response = $this->getJson('/api/v1/bom-templates');
            $response->assertOk();
        });

        $response = $this->getJson('/api/v1/bom-templates');
        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'category',
                        'is_active',
                    ]
                ]
            ]);
    });

    it('can paginate templates', function () {
        BomTemplate::factory()->count(30)->create();

        $response = $this->getJson('/api/v1/bom-templates?per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.per_page', 10);
    });

    it('can filter templates by category', function () {
        BomTemplate::factory()->count(3)->create(['category' => BomTemplate::CATEGORY_DISTRIBUTION]);
        BomTemplate::factory()->count(2)->create(['category' => BomTemplate::CATEGORY_SOLAR]);

        $response = $this->getJson('/api/v1/bom-templates?category='.BomTemplate::CATEGORY_DISTRIBUTION);

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter templates by active status', function () {
        BomTemplate::factory()->count(3)->create(['is_active' => true]);
        BomTemplate::factory()->count(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/bom-templates?is_active=true');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can search templates by code', function () {
        BomTemplate::factory()->create([
            'code' => 'TPL-DIST-001',
            'name' => 'Distribution Panel',
            'description' => null,
        ]);
        BomTemplate::factory()->create([
            'code' => 'TPL-SOLAR-001',
            'name' => 'Solar Panel',
            'description' => null,
        ]);

        $response = $this->getJson('/api/v1/bom-templates?search=TPL-DIST');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'TPL-DIST-001');
    });

    it('can search templates by name', function () {
        BomTemplate::factory()->create([
            'name' => 'Distribution Panel Standard',
            'description' => null,
        ]);
        BomTemplate::factory()->create([
            'name' => 'Motor Control Center',
            'description' => null,
        ]);

        $response = $this->getJson('/api/v1/bom-templates?search=distribution');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Distribution Panel Standard');
    });

    it('can create a template', function () {
        $response = $this->postJson('/api/v1/bom-templates', [
            'code' => 'TPL-NEW-001',
            'name' => 'New Template',
            'description' => 'A test template',
            'category' => BomTemplate::CATEGORY_DISTRIBUTION,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'TPL-NEW-001')
            ->assertJsonPath('data.name', 'New Template')
            ->assertJsonPath('data.category', BomTemplate::CATEGORY_DISTRIBUTION);

        $this->assertDatabaseHas('bom_templates', ['code' => 'TPL-NEW-001']);
    });

    it('can create a template with thumbnail', function () {
        $file = UploadedFile::fake()->image('thumbnail.jpg', 200, 200);

        $response = $this->postJson('/api/v1/bom-templates', [
            'code' => 'TPL-THUMB-001',
            'name' => 'Template with Thumbnail',
            'category' => BomTemplate::CATEGORY_DISTRIBUTION,
            'thumbnail' => $file,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'TPL-THUMB-001');

        // Verify file was stored
        $template = BomTemplate::where('code', 'TPL-THUMB-001')->first();
        Storage::disk('public')->assertExists($template->thumbnail_path);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson('/api/v1/bom-templates', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name']);
    });

    it('prevents duplicate template code', function () {
        BomTemplate::factory()->create(['code' => 'TPL-DUP-001']);

        $response = $this->postJson('/api/v1/bom-templates', [
            'code' => 'TPL-DUP-001',
            'name' => 'Duplicate',
            'category' => BomTemplate::CATEGORY_DISTRIBUTION,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    });

    it('can show a template with items', function () {
        $template = BomTemplate::factory()->create();
        BomTemplateItem::factory()->count(3)->create(['template_id' => $template->id]);

        $response = $this->getJson("/api/v1/bom-templates/{$template->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $template->id)
            ->assertJsonPath('data.code', $template->code)
            ->assertJsonCount(3, 'data.items');
    });

    it('returns 404 when showing non-existent template', function () {
        $response = $this->getJson('/api/v1/bom-templates/99999');

        $response->assertNotFound();
    });

    it('can update a template', function () {
        $template = BomTemplate::factory()->create();

        $response = $this->putJson("/api/v1/bom-templates/{$template->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.description', 'Updated description')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('bom_templates', [
            'id' => $template->id,
            'name' => 'Updated Name',
            'is_active' => false,
        ]);
    });

    it('can update template thumbnail', function () {
        $template = BomTemplate::factory()->create();
        $file = UploadedFile::fake()->image('new-thumbnail.jpg', 200, 200);

        $response = $this->putJson("/api/v1/bom-templates/{$template->id}", [
            'thumbnail' => $file,
        ]);

        $response->assertOk();

        $updatedTemplate = $template->fresh();
        Storage::disk('public')->assertExists($updatedTemplate->thumbnail_path);
    });

    it('can delete a template', function () {
        $template = BomTemplate::factory()->create();

        $response = $this->deleteJson("/api/v1/bom-templates/{$template->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Template berhasil dihapus.');

        // Uses soft deletes
        expect(BomTemplate::find($template->id))->toBeNull();
        expect(BomTemplate::withTrashed()->find($template->id)->trashed())->toBeTrue();
    });

    it('deletes thumbnail when deleting template', function () {
        $file = UploadedFile::fake()->image('thumbnail.jpg');
        $path = $file->store('bom-templates', 'public');

        $template = BomTemplate::factory()->create(['thumbnail_path' => $path]);

        Storage::disk('public')->assertExists($path);

        $this->deleteJson("/api/v1/bom-templates/{$template->id}");

        Storage::disk('public')->assertMissing($path);
    });

});

describe('BOM Template Metadata', function () {

    it('can get template metadata', function () {
        $response = $this->getJson('/api/v1/bom-templates/metadata');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'categories',
                    'item_types',
                ],
            ]);

        $categories = $response->json('data.categories');
        expect($categories)->toHaveKey(BomTemplate::CATEGORY_DISTRIBUTION);
        expect($categories)->toHaveKey(BomTemplate::CATEGORY_SOLAR);
    });

});

describe('BOM Template Items', function () {

    it('can add item to template', function () {
        $template = BomTemplate::factory()->create();

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/items", [
            'type' => BomTemplateItem::TYPE_MATERIAL,
            'description' => 'Test Item',
            'default_quantity' => 10,
            'unit' => 'pcs',
            'is_required' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.description', 'Test Item')
            ->assertJsonPath('data.type', BomTemplateItem::TYPE_MATERIAL);

        $this->assertDatabaseHas('bom_template_items', [
            'template_id' => $template->id,
            'description' => 'Test Item',
        ]);
    });

    it('can add item with component standard', function () {
        $template = BomTemplate::factory()->create();
        $standard = ComponentStandard::factory()->create();

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/items", [
            'type' => BomTemplateItem::TYPE_MATERIAL,
            'component_standard_id' => $standard->id,
            'description' => 'MCB 16A',
            'default_quantity' => 1,
            'unit' => 'pcs',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.component_standard_id', $standard->id)
            ->assertJsonPath('data.has_component_standard', true);
    });

    it('can add item with product', function () {
        $template = BomTemplate::factory()->create();
        $product = Product::factory()->create();

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/items", [
            'type' => BomTemplateItem::TYPE_MATERIAL,
            'product_id' => $product->id,
            'description' => 'Specific Product',
            'default_quantity' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.has_product', true);
    });

    it('auto-assigns sort order when adding items', function () {
        $template = BomTemplate::factory()->create();
        BomTemplateItem::factory()->create(['template_id' => $template->id, 'sort_order' => 5]);

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/items", [
            'type' => BomTemplateItem::TYPE_MATERIAL,
            'description' => 'Auto Sorted Item',
            'default_quantity' => 1,
            'unit' => 'pcs',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sort_order', 6);
    });

    it('validates item type', function () {
        $template = BomTemplate::factory()->create();

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/items", [
            'type' => 'invalid_type',
            'description' => 'Test',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    });

    it('can update item', function () {
        $template = BomTemplate::factory()->create();
        $item = BomTemplateItem::factory()->create(['template_id' => $template->id]);

        $response = $this->putJson("/api/v1/bom-templates/{$template->id}/items/{$item->id}", [
            'description' => 'Updated Description',
            'default_quantity' => 20,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.description', 'Updated Description')
            ->assertJsonPath('data.default_quantity', '20.00');

        $this->assertDatabaseHas('bom_template_items', [
            'id' => $item->id,
            'description' => 'Updated Description',
        ]);
    });

    it('cannot update item from different template', function () {
        $template1 = BomTemplate::factory()->create();
        $template2 = BomTemplate::factory()->create();
        $item = BomTemplateItem::factory()->create(['template_id' => $template2->id]);

        $response = $this->putJson("/api/v1/bom-templates/{$template1->id}/items/{$item->id}", [
            'description' => 'Should Fail',
        ]);

        $response->assertNotFound();
    });

    it('can delete item', function () {
        $template = BomTemplate::factory()->create();
        $item = BomTemplateItem::factory()->create(['template_id' => $template->id]);

        $response = $this->deleteJson("/api/v1/bom-templates/{$template->id}/items/{$item->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Item berhasil dihapus.');

        $this->assertDatabaseMissing('bom_template_items', ['id' => $item->id]);
    });

    it('cannot delete item from different template', function () {
        $template1 = BomTemplate::factory()->create();
        $template2 = BomTemplate::factory()->create();
        $item = BomTemplateItem::factory()->create(['template_id' => $template2->id]);

        $response = $this->deleteJson("/api/v1/bom-templates/{$template1->id}/items/{$item->id}");

        $response->assertNotFound();

        $this->assertDatabaseHas('bom_template_items', ['id' => $item->id]);
    });

    it('can reorder items', function () {
        $template = BomTemplate::factory()->create();
        $item1 = BomTemplateItem::factory()->create(['template_id' => $template->id, 'sort_order' => 0]);
        $item2 = BomTemplateItem::factory()->create(['template_id' => $template->id, 'sort_order' => 1]);
        $item3 = BomTemplateItem::factory()->create(['template_id' => $template->id, 'sort_order' => 2]);

        // Reorder: item3 first, then item1, then item2
        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/items/reorder", [
            'item_ids' => [$item3->id, $item1->id, $item2->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Urutan item berhasil diubah.');

        expect($item3->fresh()->sort_order)->toBe(0);
        expect($item1->fresh()->sort_order)->toBe(1);
        expect($item2->fresh()->sort_order)->toBe(2);
    });

});

describe('BOM Template Duplicate', function () {

    it('can duplicate a template', function () {
        $template = BomTemplate::factory()->create();
        BomTemplateItem::factory()->count(3)->create(['template_id' => $template->id]);

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/duplicate", [
            'code' => 'TPL-COPY-001',
            'name' => 'Copy of Template',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'TPL-COPY-001')
            ->assertJsonPath('data.name', 'Copy of Template')
            ->assertJsonCount(3, 'data.items');

        // Usage count should be 0 for the copy
        expect($response->json('data.usage_count'))->toBe(0);
    });

    it('generates default name when duplicating without name', function () {
        $template = BomTemplate::factory()->create(['name' => 'Original']);

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/duplicate", [
            'code' => 'TPL-COPY-002',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Original (Copy)');
    });

    it('requires unique code when duplicating', function () {
        $template = BomTemplate::factory()->create(['code' => 'TPL-ORIG']);

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/duplicate", [
            'code' => 'TPL-ORIG',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    });

    it('duplicates thumbnail when duplicating template', function () {
        $file = UploadedFile::fake()->image('thumbnail.jpg');
        $path = $file->store('bom-templates', 'public');

        $template = BomTemplate::factory()->create(['thumbnail_path' => $path]);

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/duplicate", [
            'code' => 'TPL-COPY-003',
        ]);

        $response->assertCreated();

        $newPath = $response->json('data.thumbnail_path');
        expect($newPath)->not->toBe($path); // Should be different path
        Storage::disk('public')->assertExists($newPath);
    });

});

describe('BOM Template Toggle Active', function () {

    it('can toggle template active status', function () {
        $template = BomTemplate::factory()->create(['is_active' => true]);

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/toggle-active");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('message', 'Template berhasil dinonaktifkan.');

        // Toggle again
        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/toggle-active");

        $response->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('message', 'Template berhasil diaktifkan.');
    });

});

describe('Create BOM from Template', function () {

    it('can get available brands for a template', function () {
        $template = BomTemplate::factory()->create();
        $standard1 = ComponentStandard::factory()->create();
        $standard2 = ComponentStandard::factory()->create();

        // Create items with component standards
        BomTemplateItem::factory()->create([
            'template_id' => $template->id,
            'component_standard_id' => $standard1->id,
        ]);
        BomTemplateItem::factory()->create([
            'template_id' => $template->id,
            'component_standard_id' => $standard2->id,
        ]);

        // Create brand mappings
        $product1 = Product::factory()->create(['brand' => 'Schneider']);
        $product2 = Product::factory()->create(['brand' => 'ABB']);
        $product3 = Product::factory()->create(['brand' => 'ABB']);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard1->id,
            'brand' => 'schneider',
            'product_id' => $product1->id,
        ]);
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard1->id,
            'brand' => 'abb',
            'product_id' => $product2->id,
        ]);
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard2->id,
            'brand' => 'abb',
            'product_id' => $product3->id,
        ]);

        $response = $this->getJson("/api/v1/bom-templates/{$template->id}/available-brands");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['code', 'name', 'coverage', 'coverage_percent'],
                ],
                'meta' => ['template_id', 'template_code', 'items_with_standard'],
            ]);

        $brands = $response->json('data');
        $abbBrand = collect($brands)->firstWhere('code', 'abb');
        $schneiderBrand = collect($brands)->firstWhere('code', 'schneider');

        expect($abbBrand['coverage'])->toBe(2);
        expect($schneiderBrand['coverage'])->toBe(1);
    });

    it('can preview creating a BOM from template', function () {
        $template = BomTemplate::factory()->create();
        $standard = ComponentStandard::factory()->create();
        $product = Product::factory()->create(['brand' => 'Schneider', 'purchase_price' => 100000]);

        BomTemplateItem::factory()->create([
            'template_id' => $template->id,
            'component_standard_id' => $standard->id,
            'description' => 'MCB 16A',
            'default_quantity' => 5,
        ]);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $product->id,
        ]);

        $this->assertMaxQueries(15, function () use ($template) {
            $response = $this->postJson("/api/v1/bom-templates/{$template->id}/preview-bom", [
                'target_brand' => 'schneider',
            ]);
            $response->assertOk();
        });

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/preview-bom", [
            'target_brand' => 'schneider',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'template_item_id',
                        'type',
                        'description',
                        'quantity',
                        'unit_cost',
                        'product',
                        'status',
                    ],
                ],
                'report' => [
                    'total_items',
                    'resolved',
                    'no_mapping',
                ],
            ]);

        expect($response->json('report.resolved'))->toBe(1);
        expect($response->json('data.0.product.id'))->toBe($product->id);
    });

    it('can preview with quantity overrides', function () {
        $template = BomTemplate::factory()->create();
        $item = BomTemplateItem::factory()->create([
            'template_id' => $template->id,
            'default_quantity' => 5,
        ]);

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/preview-bom", [
            'quantity_overrides' => [$item->id => 10],
        ]);

        $response->assertOk();
        expect((float) $response->json('data.0.quantity'))->toEqual(10.0);
    });

    it('can create a BOM from template', function () {
        $template = BomTemplate::factory()->create();
        $standard = ComponentStandard::factory()->create();
        $outputProduct = Product::factory()->create();
        $componentProduct = Product::factory()->create([
            'brand' => 'Schneider',
            'purchase_price' => 100000,
        ]);

        BomTemplateItem::factory()->create([
            'template_id' => $template->id,
            'component_standard_id' => $standard->id,
            'description' => 'MCB 16A',
            'default_quantity' => 5,
            'unit' => 'pcs',
        ]);

        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $componentProduct->id,
        ]);

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/create-bom", [
            'product_id' => $outputProduct->id,
            'target_brand' => 'schneider',
            'name' => 'Test BOM',
            'output_quantity' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'BOM berhasil dibuat dari template.')
            ->assertJsonStructure([
                'data' => ['id', 'bom_number', 'items'],
                'report' => ['resolved', 'no_mapping'],
            ]);

        // Verify BOM was created
        $bomId = $response->json('data.id');
        $this->assertDatabaseHas('boms', ['id' => $bomId]);
        $this->assertDatabaseHas('bom_items', [
            'bom_id' => $bomId,
            'product_id' => $componentProduct->id,
        ]);

        // Verify template usage count incremented
        expect($template->fresh()->usage_count)->toBe(1);
    });

    it('creates BOM with items that have no brand mapping', function () {
        $template = BomTemplate::factory()->create();
        $standard = ComponentStandard::factory()->create();
        $outputProduct = Product::factory()->create();

        // Item with no brand mapping
        BomTemplateItem::factory()->create([
            'template_id' => $template->id,
            'component_standard_id' => $standard->id,
            'description' => 'Custom Item',
            'default_quantity' => 1,
        ]);

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/create-bom", [
            'product_id' => $outputProduct->id,
            'target_brand' => 'schneider',
        ]);

        $response->assertCreated()
            ->assertJsonPath('report.no_mapping', 1);

        // BOM item should be created without product
        $bomId = $response->json('data.id');
        $this->assertDatabaseHas('bom_items', [
            'bom_id' => $bomId,
            'product_id' => null,
            'component_standard_id' => $standard->id,
        ]);
    });

    it('creates BOM with direct product items', function () {
        $template = BomTemplate::factory()->create();
        $outputProduct = Product::factory()->create();
        $directProduct = Product::factory()->create(['purchase_price' => 50000]);

        // Item with direct product (no component standard)
        BomTemplateItem::factory()->create([
            'template_id' => $template->id,
            'product_id' => $directProduct->id,
            'description' => 'Direct Product',
            'default_quantity' => 2,
        ]);

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/create-bom", [
            'product_id' => $outputProduct->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('report.using_product', 1);

        // BOM item should use the direct product
        $bomId = $response->json('data.id');
        $this->assertDatabaseHas('bom_items', [
            'bom_id' => $bomId,
            'product_id' => $directProduct->id,
        ]);
    });

    it('validates required fields when creating BOM', function () {
        $template = BomTemplate::factory()->create();

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/create-bom", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    });

    it('validates product exists when creating BOM', function () {
        $template = BomTemplate::factory()->create();

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/create-bom", [
            'product_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    });

    it('uses preferred brand mapping when no target brand specified', function () {
        $template = BomTemplate::factory()->create();
        $standard = ComponentStandard::factory()->create();
        $outputProduct = Product::factory()->create();
        $preferredProduct = Product::factory()->create(['purchase_price' => 150000]);
        $otherProduct = Product::factory()->create(['purchase_price' => 100000]);

        BomTemplateItem::factory()->create([
            'template_id' => $template->id,
            'component_standard_id' => $standard->id,
            'default_quantity' => 1,
        ]);

        // Create mappings, one preferred
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'abb',
            'product_id' => $otherProduct->id,
            'is_preferred' => false,
        ]);
        ComponentBrandMapping::factory()->create([
            'component_standard_id' => $standard->id,
            'brand' => 'schneider',
            'product_id' => $preferredProduct->id,
            'is_preferred' => true,
        ]);

        $response = $this->postJson("/api/v1/bom-templates/{$template->id}/create-bom", [
            'product_id' => $outputProduct->id,
            // No target_brand specified
        ]);

        $response->assertCreated();

        // Should use the preferred product
        $bomId = $response->json('data.id');
        $this->assertDatabaseHas('bom_items', [
            'bom_id' => $bomId,
            'product_id' => $preferredProduct->id,
        ]);
    });

});
