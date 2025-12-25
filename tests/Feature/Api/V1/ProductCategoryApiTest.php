<?php

use App\Models\Accounting\Product;
use App\Models\Accounting\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Product Category API', function () {

    it('can list all product categories', function () {
        ProductCategory::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/product-categories');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('can filter categories by is_active', function () {
        ProductCategory::factory()->count(3)->create(['is_active' => true]);
        ProductCategory::factory()->count(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/product-categories?is_active=true');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter categories by parent_id', function () {
        $parent = ProductCategory::factory()->create();
        ProductCategory::factory()->count(3)->create(['parent_id' => $parent->id]);
        ProductCategory::factory()->count(2)->create(['parent_id' => null]);

        $response = $this->getJson("/api/v1/product-categories?parent_id={$parent->id}");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter root categories', function () {
        $parent = ProductCategory::factory()->create(['parent_id' => null]);
        ProductCategory::factory()->count(2)->create(['parent_id' => $parent->id]);

        $response = $this->getJson('/api/v1/product-categories?parent_id=null');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can search categories by name', function () {
        ProductCategory::factory()->create(['name' => 'Electronics']);
        ProductCategory::factory()->create(['name' => 'Clothing']);
        ProductCategory::factory()->create(['name' => 'Electronic Parts']);

        $response = $this->getJson('/api/v1/product-categories?search=electro');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can create a product category', function () {
        $response = $this->postJson('/api/v1/product-categories', [
            'name' => 'Electronics',
            'description' => 'Electronic products',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Electronics')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('product_categories', [
            'name' => 'Electronics',
        ]);
    });

    it('auto-generates code when not provided', function () {
        $response = $this->postJson('/api/v1/product-categories', [
            'name' => 'New Category',
        ]);

        $response->assertCreated();
        expect($response->json('data.code'))->toStartWith('CAT-');
    });

    it('can create a child category', function () {
        $parent = ProductCategory::factory()->create(['code' => 'CAT-001']);

        $response = $this->postJson('/api/v1/product-categories', [
            'name' => 'Sub Category',
            'parent_id' => $parent->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.parent_id', $parent->id);
    });

    it('validates required fields when creating category', function () {
        $response = $this->postJson('/api/v1/product-categories', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    it('prevents duplicate codes', function () {
        ProductCategory::factory()->create(['code' => 'CAT-001']);

        $response = $this->postJson('/api/v1/product-categories', [
            'code' => 'CAT-001',
            'name' => 'Another Category',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    });

    it('can show a category with children', function () {
        $parent = ProductCategory::factory()->create();
        ProductCategory::factory()->count(2)->create(['parent_id' => $parent->id]);

        $response = $this->getJson("/api/v1/product-categories/{$parent->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $parent->id)
            ->assertJsonCount(2, 'data.children');
    });

    it('can update a category', function () {
        $category = ProductCategory::factory()->create();

        $response = $this->putJson("/api/v1/product-categories/{$category->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    });

    it('can delete a category without children or products', function () {
        $category = ProductCategory::factory()->create();

        $response = $this->deleteJson("/api/v1/product-categories/{$category->id}");

        $response->assertOk();
        $this->assertSoftDeleted('product_categories', ['id' => $category->id]);
    });

    it('cannot delete a category with children', function () {
        $parent = ProductCategory::factory()->create();
        ProductCategory::factory()->create(['parent_id' => $parent->id]);

        $response = $this->deleteJson("/api/v1/product-categories/{$parent->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Kategori tidak bisa dihapus karena memiliki sub-kategori.');
    });

    it('cannot delete a category with products', function () {
        $category = ProductCategory::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $response = $this->deleteJson("/api/v1/product-categories/{$category->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Kategori tidak bisa dihapus karena memiliki produk.');
    });

    it('can get category tree', function () {
        $parent1 = ProductCategory::factory()->create(['parent_id' => null]);
        $parent2 = ProductCategory::factory()->create(['parent_id' => null]);
        ProductCategory::factory()->count(2)->create(['parent_id' => $parent1->id]);

        $response = $this->getJson('/api/v1/product-categories-tree');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('returns full path for nested categories', function () {
        $parent = ProductCategory::factory()->create(['name' => 'Electronics']);
        $child = ProductCategory::factory()->create([
            'name' => 'Phones',
            'parent_id' => $parent->id,
        ]);

        $response = $this->getJson("/api/v1/product-categories/{$child->id}");

        $response->assertOk()
            ->assertJsonPath('data.full_path', 'Electronics > Phones');
    });
});
