<?php

use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\Product;
use App\Models\Accounting\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
});

describe('Product API', function () {

    it('can list all products', function () {
        Product::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('can filter products by type', function () {
        Product::factory()->count(3)->create(['type' => Product::TYPE_PRODUCT]);
        Product::factory()->service()->count(2)->create();

        $response = $this->getJson('/api/v1/products?type=product');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter products by category', function () {
        $category = ProductCategory::factory()->create();
        Product::factory()->count(3)->create(['category_id' => $category->id]);
        Product::factory()->count(2)->create(['category_id' => null]);

        $response = $this->getJson("/api/v1/products?category_id={$category->id}");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter products by is_active', function () {
        Product::factory()->count(3)->create(['is_active' => true]);
        Product::factory()->inactive()->count(2)->create();

        $response = $this->getJson('/api/v1/products?is_active=true');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter sellable products', function () {
        Product::factory()->count(3)->create(['is_sellable' => true]);
        Product::factory()->notSellable()->count(2)->create();

        $response = $this->getJson('/api/v1/products?is_sellable=true');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter low stock products', function () {
        Product::factory()->count(2)->create([
            'track_inventory' => true,
            'min_stock' => 10,
            'current_stock' => 5,
        ]);
        Product::factory()->count(3)->create([
            'track_inventory' => true,
            'min_stock' => 10,
            'current_stock' => 50,
        ]);

        $response = $this->getJson('/api/v1/products?low_stock=true');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can search products by name', function () {
        Product::factory()->create(['name' => 'iPhone 15 Pro']);
        Product::factory()->create(['name' => 'Samsung Galaxy']);
        Product::factory()->create(['name' => 'iPhone Case']);

        $response = $this->getJson('/api/v1/products?search=iphone');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can search products by SKU', function () {
        Product::factory()->create(['sku' => 'PRD-00001']);
        Product::factory()->create(['sku' => 'PRD-00002']);

        $response = $this->getJson('/api/v1/products?search=PRD-00001');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can create a product', function () {
        $response = $this->postJson('/api/v1/products', [
            'name' => 'Test Product',
            'type' => 'product',
            'unit' => 'pcs',
            'purchase_price' => 100000,
            'selling_price' => 150000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Test Product')
            ->assertJsonPath('data.type', 'product')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('products', [
            'name' => 'Test Product',
        ]);
    });

    it('auto-generates SKU when not provided', function () {
        $response = $this->postJson('/api/v1/products', [
            'name' => 'Auto SKU Product',
            'type' => 'product',
            'unit' => 'pcs',
            'purchase_price' => 100000,
            'selling_price' => 150000,
        ]);

        $response->assertCreated();
        expect($response->json('data.sku'))->toStartWith('PRD-');
    });

    it('generates SVC prefix for services', function () {
        $response = $this->postJson('/api/v1/products', [
            'name' => 'Consulting Service',
            'type' => 'service',
            'unit' => 'jam',
            'purchase_price' => 0,
            'selling_price' => 500000,
        ]);

        $response->assertCreated();
        expect($response->json('data.sku'))->toStartWith('SVC-');
        expect($response->json('data.track_inventory'))->toBeFalse();
    });

    it('can create a product with category', function () {
        $category = ProductCategory::factory()->create();

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Categorized Product',
            'type' => 'product',
            'unit' => 'pcs',
            'purchase_price' => 100000,
            'selling_price' => 150000,
            'category_id' => $category->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.category_id', $category->id);
    });

    it('validates required fields when creating product', function () {
        $response = $this->postJson('/api/v1/products', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type', 'unit', 'purchase_price', 'selling_price']);
    });

    it('validates type value', function () {
        $response = $this->postJson('/api/v1/products', [
            'name' => 'Test',
            'type' => 'invalid',
            'unit' => 'pcs',
            'purchase_price' => 100000,
            'selling_price' => 150000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    });

    it('prevents duplicate SKU', function () {
        Product::factory()->create(['sku' => 'PRD-00001']);

        $response = $this->postJson('/api/v1/products', [
            'sku' => 'PRD-00001',
            'name' => 'Test',
            'type' => 'product',
            'unit' => 'pcs',
            'purchase_price' => 100000,
            'selling_price' => 150000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    });

    it('can show a product', function () {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', $product->name);
    });

    it('returns calculated fields', function () {
        $product = Product::factory()->create([
            'purchase_price' => 100000,
            'selling_price' => 150000,
            'tax_rate' => 11.00,
            'is_taxable' => true,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.selling_price_with_tax', 166500)
            ->assertJsonPath('data.selling_tax_amount', 16500);

        expect((float) $response->json('data.profit_margin'))->toBe(33.33);
        expect((float) $response->json('data.markup'))->toBe(50.0);
    });

    it('can update a product', function () {
        $product = Product::factory()->create();

        $response = $this->putJson("/api/v1/products/{$product->id}", [
            'name' => 'Updated Product',
            'selling_price' => 200000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Product')
            ->assertJsonPath('data.selling_price', 200000);
    });

    it('can delete a product without transactions', function () {
        $product = Product::factory()->create();

        $response = $this->deleteJson("/api/v1/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Produk berhasil dihapus.');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    });

    it('deactivates product with transactions instead of deleting', function () {
        $product = Product::factory()->create();

        // Create an invoice item linked to this product
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
        ]);

        $response = $this->deleteJson("/api/v1/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Produk dinonaktifkan karena sudah memiliki transaksi.');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => false,
        ]);
    });

    it('can adjust product stock', function () {
        $product = Product::factory()->create([
            'track_inventory' => true,
            'current_stock' => 50,
        ]);

        $response = $this->postJson("/api/v1/products/{$product->id}/adjust-stock", [
            'quantity' => 10,
            'reason' => 'Received shipment',
        ]);

        $response->assertOk()
            ->assertJsonPath('current_stock', 60)
            ->assertJsonPath('adjustment', 10);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'current_stock' => 60,
        ]);
    });

    it('can decrease product stock', function () {
        $product = Product::factory()->create([
            'track_inventory' => true,
            'current_stock' => 50,
        ]);

        $response = $this->postJson("/api/v1/products/{$product->id}/adjust-stock", [
            'quantity' => -20,
        ]);

        $response->assertOk()
            ->assertJsonPath('current_stock', 30);
    });

    it('prevents negative stock', function () {
        $product = Product::factory()->create([
            'track_inventory' => true,
            'current_stock' => 10,
        ]);

        $response = $this->postJson("/api/v1/products/{$product->id}/adjust-stock", [
            'quantity' => -20,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Stok tidak bisa negatif.');
    });

    it('cannot adjust stock for non-inventory product', function () {
        $product = Product::factory()->service()->create();

        $response = $this->postJson("/api/v1/products/{$product->id}/adjust-stock", [
            'quantity' => 10,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Produk ini tidak melacak inventori.');
    });

    it('can get low stock products', function () {
        Product::factory()->lowStock()->count(2)->create();
        Product::factory()->create([
            'track_inventory' => true,
            'min_stock' => 10,
            'current_stock' => 100,
        ]);

        $response = $this->getJson('/api/v1/products-low-stock');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can get price list', function () {
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/products-price-list');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'sku',
                        'name',
                        'unit',
                        'purchase_price',
                        'selling_price',
                        'selling_price_with_tax',
                        'tax_rate',
                        'is_taxable',
                    ],
                ],
            ]);
    });

    it('can lookup product by SKU', function () {
        $product = Product::factory()->create(['sku' => 'PRD-12345']);

        $response = $this->getJson('/api/v1/products-lookup?code=PRD-12345');

        $response->assertOk()
            ->assertJsonPath('data.sku', 'PRD-12345');
    });

    it('can lookup product by barcode', function () {
        $product = Product::factory()->create(['barcode' => '1234567890123']);

        $response = $this->getJson('/api/v1/products-lookup?code=1234567890123');

        $response->assertOk()
            ->assertJsonPath('data.barcode', '1234567890123');
    });

    it('returns 404 for unknown product lookup', function () {
        $response = $this->getJson('/api/v1/products-lookup?code=UNKNOWN');

        $response->assertNotFound();
    });

    it('can duplicate a product', function () {
        $product = Product::factory()->create([
            'name' => 'Original Product',
            'selling_price' => 100000,
        ]);

        $response = $this->postJson("/api/v1/products/{$product->id}/duplicate");

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Original Product (Copy)')
            ->assertJsonPath('data.selling_price', 100000)
            ->assertJsonPath('data.current_stock', 0);

        expect($response->json('data.sku'))->not->toBe($product->sku);
    });
});
