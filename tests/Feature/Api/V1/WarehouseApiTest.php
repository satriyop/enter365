<?php

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
});

describe('Warehouse API', function () {

    it('can list all warehouses', function () {
        Warehouse::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/warehouses');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('can filter warehouses by is_active', function () {
        Warehouse::factory()->count(3)->create(['is_active' => true]);
        Warehouse::factory()->count(2)->inactive()->create();

        $response = $this->getJson('/api/v1/warehouses?is_active=true');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can search warehouses by name or code', function () {
        Warehouse::factory()->create(['name' => 'Gudang Utama', 'code' => 'WH-001']);
        Warehouse::factory()->create(['name' => 'Gudang Cabang', 'code' => 'WH-002']);
        Warehouse::factory()->create(['name' => 'Toko Jakarta', 'code' => 'WH-003']);

        $response = $this->getJson('/api/v1/warehouses?search=gudang');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can create a warehouse', function () {
        $response = $this->postJson('/api/v1/warehouses', [
            'name' => 'Gudang Baru',
            'address' => 'Jl. Industri No. 1',
            'phone' => '021-12345678',
            'contact_person' => 'Budi',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Gudang Baru')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('warehouses', [
            'name' => 'Gudang Baru',
        ]);
    });

    it('auto-generates code when not provided', function () {
        $response = $this->postJson('/api/v1/warehouses', [
            'name' => 'New Warehouse',
        ]);

        $response->assertCreated();
        expect($response->json('data.code'))->toStartWith('WH-');
    });

    it('makes first warehouse default automatically', function () {
        $response = $this->postJson('/api/v1/warehouses', [
            'name' => 'First Warehouse',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_default', true);
    });

    it('validates required fields when creating warehouse', function () {
        $response = $this->postJson('/api/v1/warehouses', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    it('prevents duplicate codes', function () {
        Warehouse::factory()->create(['code' => 'WH-001']);

        $response = $this->postJson('/api/v1/warehouses', [
            'code' => 'WH-001',
            'name' => 'Another Warehouse',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    });

    it('can show a warehouse', function () {
        $warehouse = Warehouse::factory()->create();

        $response = $this->getJson("/api/v1/warehouses/{$warehouse->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $warehouse->id)
            ->assertJsonPath('data.name', $warehouse->name);
    });

    it('can update a warehouse', function () {
        $warehouse = Warehouse::factory()->create();

        $response = $this->putJson("/api/v1/warehouses/{$warehouse->id}", [
            'name' => 'Updated Name',
            'phone' => '08123456789',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.phone', '08123456789');
    });

    it('can delete a warehouse without stock', function () {
        $warehouse = Warehouse::factory()->create(['is_default' => false]);

        $response = $this->deleteJson("/api/v1/warehouses/{$warehouse->id}");

        $response->assertOk();
        $this->assertSoftDeleted('warehouses', ['id' => $warehouse->id]);
    });

    it('cannot delete a warehouse with stock', function () {
        $warehouse = Warehouse::factory()->create(['is_default' => false]);
        $product = Product::factory()->create(['track_inventory' => true]);
        ProductStock::factory()
            ->forProduct($product)
            ->inWarehouse($warehouse)
            ->withQuantity(10)
            ->create();

        $response = $this->deleteJson("/api/v1/warehouses/{$warehouse->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Gudang tidak bisa dihapus karena masih memiliki stok.');
    });

    it('cannot delete default warehouse', function () {
        $warehouse = Warehouse::factory()->default()->create();

        $response = $this->deleteJson("/api/v1/warehouses/{$warehouse->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Gudang default tidak bisa dihapus. Tetapkan gudang lain sebagai default terlebih dahulu.');
    });

    it('can set warehouse as default', function () {
        $warehouse1 = Warehouse::factory()->default()->create();
        $warehouse2 = Warehouse::factory()->create(['is_default' => false]);

        $response = $this->postJson("/api/v1/warehouses/{$warehouse2->id}/set-default");

        $response->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse1->id,
            'is_default' => false,
        ]);
        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse2->id,
            'is_default' => true,
        ]);
    });

    it('cannot set inactive warehouse as default', function () {
        Warehouse::factory()->default()->create();
        $warehouse = Warehouse::factory()->inactive()->create();

        $response = $this->postJson("/api/v1/warehouses/{$warehouse->id}/set-default");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Gudang tidak aktif tidak bisa dijadikan default.');
    });

    it('can get stock summary for warehouse', function () {
        $warehouse = Warehouse::factory()->create();
        $product1 = Product::factory()->create(['track_inventory' => true]);
        $product2 = Product::factory()->create(['track_inventory' => true]);

        ProductStock::factory()
            ->forProduct($product1)
            ->inWarehouse($warehouse)
            ->withQuantity(10)
            ->create(['average_cost' => 100000, 'total_value' => 1000000]);
        ProductStock::factory()
            ->forProduct($product2)
            ->inWarehouse($warehouse)
            ->withQuantity(20)
            ->create(['average_cost' => 50000, 'total_value' => 1000000]);

        $response = $this->getJson("/api/v1/warehouses/{$warehouse->id}/stock-summary");

        $response->assertOk()
            ->assertJsonPath('summary.total_items', 2)
            ->assertJsonPath('summary.total_quantity', 30)
            ->assertJsonPath('summary.total_value', 2000000);
    });

    it('orders warehouses with default first', function () {
        $warehouse1 = Warehouse::factory()->create(['name' => 'A Warehouse', 'is_default' => false]);
        $warehouse2 = Warehouse::factory()->default()->create(['name' => 'Z Warehouse']);
        $warehouse3 = Warehouse::factory()->create(['name' => 'B Warehouse', 'is_default' => false]);

        $response = $this->getJson('/api/v1/warehouses');

        $response->assertOk();
        expect($response->json('data.0.id'))->toBe($warehouse2->id); // Default first
    });
});
